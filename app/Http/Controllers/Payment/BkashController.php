<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\GatewayCurrency;
use App\Services\GatewayPaymentService;
use App\Services\SessionGatewayPayment;
use App\Services\VerifiedPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

    /**
     * Start the payment this session is making. The amount due comes from the database (an
     * order of the signed-in customer, a package's price, the top-up asked for), is converted
     * to bKash's currency and kept to the cent. A currency the store has no active rate for is
     * refused with a message and nothing is recorded.
     */
    public function pay()
    {
        $due = app(SessionGatewayPayment::class)->amountDue();
        $currency = GatewayCurrency::forGateway('bkash');
        $converted = $due === null ? null : $currency->fromStore($due);

        if ($converted === null) {
            Log::error('bKash refused: nothing due, or the store has no active rate for the gateway currency.', ['currency' => $currency->chargeCode()]);

            return $this->failed();
        }

        Session::forget('bkash_token');
        Session::put('bkash_token', $this->getToken());
        Session::put('amount', GatewayCurrency::format($converted));

        return redirect()->route('bkash.create_payment');
    }

    private function failed()
    {
        flash(__('storefront.checkout.payment_failed'))->error();

        return redirect()->route('cart');
    }

    public function create_payment()
    {
        $amount = (string) Session::get('amount');

        if ($amount === '') {
            return $this->failed();
        }

        $requestbody = [
            'mode' => '0011',
            'payerReference' => ' ',
            'callbackURL' => route('bkash.callback'),
            'amount' => $amount,
            'currency' => GatewayCurrency::forGateway('bkash')->chargeCode(),
            'intent' => 'sale',
            'merchantInvoiceNumber' => 'Inv'.Date('YmdH').rand(1000, 10000),
        ];

        $result = $this->api('checkout/create', $requestbody, Session::get('bkash_token'));

        if (! is_string($result['bkashURL'] ?? null) || $result['bkashURL'] === '') {
            Log::error('bKash gave no payment page.', ['status' => $result['statusCode'] ?? null, 'message' => $result['statusMessage'] ?? null]);
            Session::forget('bkash_payment_id');

            return $this->failed();
        }

        // The payment callback() may verify: only this one, for this session.
        Session::put('bkash_payment_id', $result['paymentID'] ?? null);

        return redirect($result['bkashURL']);
    }

    public function getToken()
    {
        $response = Http::timeout(30)
            ->withHeaders(['username' => config('services.bkash.username'), 'password' => config('services.bkash.password')])
            ->post($this->baseUrl().'checkout/token/grant', ['app_key' => config('services.bkash.app_key'), 'app_secret' => config('services.bkash.app_secret')]);

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

        $currency = GatewayCurrency::forGateway('bkash');
        $reported = (float) ($result['amount'] ?? 0);

        // Completed, in the currency this store charges, for the amount due: exactly in the store's
        // own currency, else the converted amount to the cent.
        if (! self::isCompleted($result)
            || strtoupper((string) ($result['currency'] ?? '')) !== $currency->chargeCode()
            || ! ($currency->isStoreCurrency() ? GatewayPaymentService::covers($reported, $amountDue) : $currency->matchesDue($reported, $amountDue))) {
            return view('shop.frontend.bkash.fail')->with([
                'errorMessage' => $result['statusMessage'] ?? 'Payment could not be verified',
            ]);
        }

        Session::forget('bkash_payment_id');
        Session::put('bkash_verified_payment', ['payment_id' => $paymentID, 'amount' => $currency->paidInStoreCurrency($reported, $amountDue), 'details' => json_encode($result)]);

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
            ->withHeaders(['Authorization' => (string) $token, 'X-APP-Key' => (string) config('services.bkash.app_key')])
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
