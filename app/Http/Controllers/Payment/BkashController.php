<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\CombinedOrder;
use App\Models\CustomerPackage;
use App\Models\SellerPackage;
use App\Services\GatewayPaymentService;
use App\Services\SessionGatewayPayment;
use App\Services\VerifiedPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Session;

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

    public function pay(){
        $amount = 0;
        if(Session::has('payment_type')){
            if(Session::get('payment_type') == 'cart_payment'){
                $combined_order = CombinedOrder::findOrFail(Session::get('combined_order_id'));
                $amount = round($combined_order->grand_total);
            }
            elseif (Session::get('payment_type') == 'wallet_payment') {
                $amount = round(Session::get('payment_data')['amount']);
            }
            elseif (Session::get('payment_type') == 'customer_package_payment') {
                $customer_package = CustomerPackage::findOrFail(Session::get('payment_data')['customer_package_id']);
                $amount = round($customer_package->amount);
            }
            elseif (Session::get('payment_type') == 'seller_package_payment') {
                $seller_package = SellerPackage::findOrFail(Session::get('payment_data')['seller_package_id']);
                $amount = round($seller_package->amount);
            }
        }

        Session::forget('bkash_token');
        Session::put('bkash_token', $this->getToken());
        Session::put('amount', $amount);
        return redirect()->route('bkash.create_payment');
    }

    public function create_payment(){

        $requestbody = [
            'mode' => '0011',
            'payerReference' => ' ',
            'callbackURL' => route('bkash.callback'),
            'amount' => Session::get('amount'),
            'currency' => 'BDT',
            'intent' => 'sale',
            'merchantInvoiceNumber' => "Inv".Date('YmdH').rand(1000, 10000),
        ];

        $result = $this->api('checkout/create', $requestbody, Session::get('bkash_token'));

        // The payment callback() may verify: only this one, for this session.
        Session::put('bkash_payment_id', $result['paymentID'] ?? null);

        return redirect($result['bkashURL'] ?? route('cart'));
    }

    public function getToken(){
        $response = Http::timeout(30)
            ->withHeaders(['username' => env('BKASH_CHECKOUT_USER_NAME'), 'password' => env('BKASH_CHECKOUT_PASSWORD')])
            ->post($this->baseUrl().'checkout/token/grant', ['app_key' => env('BKASH_CHECKOUT_APP_KEY'), 'app_secret' => env('BKASH_CHECKOUT_APP_SECRET')]);

        return $response->json('id_token');
    }

    /**
     * bKash sends the customer back here with the payment's id and a status, all
     * of which the customer controls. The payment must be the one this session
     * created, and bKash's own execute (or, when that does not answer, its
     * status query) must report it completed with status code 0000 and for at
     * least the amount due; a response without a status code is not a success.
     * Only then is the one-shot flag set that success() requires.
     */
    public function callback(Request $request)
    {
        $allRequest = $request->all();
        if(isset($allRequest['status']) && $allRequest['status'] == 'failure'){
            return view('shop.frontend.bkash.fail')->with([
                'errorMessage' => 'Payment Failure'
            ]);

        }else if(isset($allRequest['status']) && $allRequest['status'] == 'cancel'){
            return view('shop.frontend.bkash.fail')->with([
                'errorMessage' => 'Payment Cancelled'
            ]);

        }

        $paymentID = (string) $request->input('paymentID');
        $expected = (string) Session::get('bkash_payment_id');
        $amountDue = app(SessionGatewayPayment::class)->amountDue();

        if ($paymentID === '' || $expected === '' || ! hash_equals($expected, $paymentID) || $amountDue === null) {
            return view('shop.frontend.bkash.fail')->with(['errorMessage' => 'Payment could not be verified']);
        }

        $result = $this->execute($paymentID);
        if (! self::isCompleted($result)) {
            // execute did not answer with a completed payment: ask bKash for its status
            Sleep::for(1)->second();
            $result = $this->query($paymentID);
        }

        if (! self::isCompleted($result) || ! GatewayPaymentService::covers((float) ($result['amount'] ?? 0), $amountDue)) {
            return view('shop.frontend.bkash.fail')->with([
                'errorMessage' => $result['statusMessage'] ?? 'Payment could not be verified',
            ]);
        }

        Session::forget('bkash_payment_id');
        Session::put('bkash_verified_payment', ['payment_id' => $paymentID, 'amount' => (float) $result['amount'], 'details' => json_encode($result)]);

        return redirect()->route('bkash.success');
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private static function isCompleted(array $result): bool
    {
        return ($result['statusCode'] ?? null) === '0000' && ($result['transactionStatus'] ?? null) === 'Completed';
    }

    /**
     * @return array<string, mixed>
     */
    public function execute($paymentID) {
        return $this->api('checkout/execute', ['paymentID' => $paymentID], Session::get('bkash_token'));
    }

    /**
     * @return array<string, mixed>
     */
    public function query($paymentID){
        return $this->api('checkout/payment/status', ['paymentID' => $paymentID], Session::get('bkash_token'));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function api(string $path, array $body, ?string $token): array
    {
        $response = Http::timeout(30)
            ->withHeaders(['Authorization' => (string) $token, 'X-APP-Key' => (string) env('BKASH_CHECKOUT_APP_KEY')])
            ->post($this->baseUrl().$path, $body);

        return is_array($response->json()) ? $response->json() : [];
    }

    /**
     * Finishes the payment callback() verified for this session, once: the flag
     * is taken out of the session as it is read, and without it nothing is paid.
     */
    public function success(Request $request){
        $verified = Session::pull('bkash_verified_payment');

        if (! is_array($verified) || ! isset($verified['payment_id'], $verified['amount'])) {
            return app(SessionGatewayPayment::class)->notVerified();
        }

        return app(SessionGatewayPayment::class)->complete(new VerifiedPayment('bkash', (string) $verified['payment_id'], (float) $verified['amount'], $verified['details'] ?? null));
    }
}
