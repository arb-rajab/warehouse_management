<?php


namespace App\Http\Controllers\Api\V2;

use App\Services\ApiGatewayPayment;
use App\Services\GatewayPaymentService;
use App\Services\VerifiedPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

class BkashController extends Controller
{
    /**
     * Resolved on use rather than in a constructor: the sandbox flag lives in
     * `business_settings`, and building a controller (route:list, wayfinder:generate)
     * must not need that table to exist yet.
     */
    private function baseUrl(): string
    {
        return get_setting('bkash_sandbox', 1)
            ? 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/'
            : 'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/';
    }

    /**
     * The amount is the signed-in customer's own order total or the package's
     * price, from the database; the request's `user_id` and (except for a
     * top-up) `amount` are not read.
     */
    public function begin(Request $request)
    {
        $payment = app(ApiGatewayPayment::class)->resolve($request);

        if ($payment === null) {
            return response()->json(['token' => '', 'result' => false, 'url' => '', 'message' => __('api.payment.nothing_due')]);
        }

        try {
            $token = $this->getToken();

            return response()->json([
                'token' => $token,
                'result' => true,
                'url' => route('api.bkash.webpage', ["token" => $token, "amount" => $payment['amount']]),
                'message' => __('api.payment.page_found')
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'token' => '',
                'result' => false,
                'url' => '',
                'message' => $exception->getMessage()
            ]);
        }
    }

    public function webpage($token, $amount)
    {
        return view('shop.frontend.payment.bkash_app', compact('token', 'amount'));
    }

    public function checkout($token, $amount)
    {
        $auth = $token;

        $callbackURL = route('home');
        $requestbody = [
            'mode' => '0011',
            'payerReference' => ' ',
            'callbackURL' => route('api.bkash.callback'),
            'amount' => $amount,
            'currency' => 'BDT',
            'intent' => 'sale',
            'merchantInvoiceNumber' => "Inv" . Date('YmdH') . rand(1000, 10000),
        ];
        $requestbodyJson = json_encode($requestbody);

        $header = [
            'Content-Type:application/json',
            'Authorization:' . $auth,
            'X-APP-Key:' . env('BKASH_CHECKOUT_APP_KEY'),
        ];

        $url = curl_init($this->baseUrl() . 'checkout/create');
        curl_setopt($url, CURLOPT_HTTPHEADER, $header);
        curl_setopt($url, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($url, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($url, CURLOPT_POSTFIELDS, $requestbodyJson);
        curl_setopt($url, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($url, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $resultdata = curl_exec($url);

        return redirect(json_decode($resultdata)->bkashURL);
    }

    public function callback(Request $request)
    {
        $allRequest = $request->all();
        if (isset($allRequest['status']) && $allRequest['status'] == 'failure') {
            return $this->failed(__('storefront.checkout.payment_failed'));
        } else if (isset($allRequest['status']) && $allRequest['status'] == 'cancel') {
            return $this->failed(__('storefront.checkout.payment_cancelled'));
        } else {
            return response()->json([
                "result" => true,
                "paymentID" => $allRequest['paymentID']
            ]);
        }
    }



    /**
     * The app reports a payment here once bKash has sent it back. The report
     * used to be credited as it came (order, account and amount from the
     * request) by process(), which was also its own unauthenticated route, and
     * a missing status code counted as success. Now bKash's server must report
     * the payment completed (execute, or its status query when execute does not
     * say so) with status code 0000, for at least what the signed-in customer
     * owes; a payment id is credited once.
     */
    public function payment_success(Request $request)
    {
        $payment = app(ApiGatewayPayment::class)->resolve($request);
        $paymentID = (string) $request->input('payment_id');

        if ($payment === null || $paymentID === '') {
            return $this->failed(__('storefront.checkout.payment_could_not_be_verified'));
        }

        $token = $this->getToken();
        $result = $this->execute($token, $paymentID);
        if (! self::isCompleted($result)) {
            // execute did not answer with a completed payment: ask bKash for its status
            Sleep::for(1)->second();
            $result = $this->query($token, $paymentID);
        }

        if (! self::isCompleted($result) || ! GatewayPaymentService::covers((float) ($result['amount'] ?? 0), $payment['amount'])) {
            return $this->failed($result['statusMessage'] ?? __('storefront.checkout.payment_could_not_be_verified'));
        }

        $verified = new VerifiedPayment('bkash', $paymentID, (float) $result['amount'], json_encode($result));
        if (! app(GatewayPaymentService::class)->complete($verified, $payment['user_id'], $payment['payment_type'], $payment['combined_order_id'], $payment['package_id'])) {
            return $this->failed(__('storefront.checkout.payment_could_not_be_verified'));
        }

        return response()->json(['result' => true, 'message' => __('api.payment.successful')]);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private static function isCompleted(array $result): bool
    {
        return ($result['statusCode'] ?? null) === '0000' && ($result['transactionStatus'] ?? null) === 'Completed';
    }

    public function getToken()
    {
        $response = Http::timeout(30)
            ->withHeaders(['username' => env('BKASH_CHECKOUT_USER_NAME'), 'password' => env('BKASH_CHECKOUT_PASSWORD')])
            ->post($this->baseUrl() . 'checkout/token/grant', ['app_key' => env('BKASH_CHECKOUT_APP_KEY'), 'app_secret' => env('BKASH_CHECKOUT_APP_SECRET')]);

        return $response->json('id_token');
    }

    /**
     * @return array<string, mixed>
     */
    public function execute($token, $paymentID = null)
    {
        return $this->api('checkout/execute', ['paymentID' => $paymentID], $token);
    }

    /**
     * @return array<string, mixed>
     */
    public function query($token, $paymentID)
    {
        return $this->api('checkout/payment/status', ['paymentID' => $paymentID], $token);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function api(string $path, array $body, $token): array
    {
        $response = Http::timeout(30)
            ->withHeaders(['Authorization' => (string) $token, 'X-APP-Key' => (string) env('BKASH_CHECKOUT_APP_KEY')])
            ->post($this->baseUrl() . $path, $body);

        return is_array($response->json()) ? $response->json() : [];
    }

    public function fail(Request $request)
    {
        return response()->json([
            'result' => false,
            'message' => __('storefront.checkout.payment_failed'),
            'payment_details' => $request->payment_details
        ]);
    }
}
