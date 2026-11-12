<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\GatewayCurrency;
use App\Services\GatewayReturn;
use App\Services\SessionGatewayPayment;
use App\Services\VerifiedPayment;
use App\Support\StorePaymentGateways;
use Auth;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AamarpayController extends Controller
{
    private function baseUrl(): string
    {
        return get_setting('aamarpay_sandbox') == 1 ? 'https://sandbox.aamarpay.com/' : 'https://secure.aamarpay.com/';
    }

    /**
     * Start the payment this session is making. The amount due comes from the database (an
     * order of the signed-in customer, a package's price, the top-up asked for), is converted to
     * aamarpay's currency and kept to the cent. A currency the store has no active rate for, or
     * an aamarpay that does not answer with a payment page, is refused with a message and nothing
     * is kept. Otherwise the payment is recorded as pending under the transaction id sent to
     * aamarpay (GatewayReturn), which is all success() reads from the return.
     */
    public function pay()
    {
        if (Auth::user()->phone == null) {
            flash(__('storefront.auth.please_add_phone_number_to_your_profile'))->warning();

            return redirect()->route('profile');
        }

        $email = Auth::user()->email ?: config('mail.from.address');

        $payment = app(SessionGatewayPayment::class)->pending();
        $currency = GatewayCurrency::forGateway('aamarpay');
        $converted = $payment === null ? null : $currency->fromStore($payment['amount']);

        if ($payment === null || $converted === null) {
            Log::error('aamarpay refused: nothing due, or the store has no active rate for the gateway currency.', ['currency' => $currency->chargeCode()]);

            return $this->failed();
        }

        // The transaction id success() looks the payment up by, and checks against aamarpay's search API.
        $tran_id = GatewayReturn::newReference();

        $fields = [
            'store_id' => StorePaymentGateways::get('aamarpay', 'store_id'), //store id will be aamarpay,  contact integration@aamarpay.com for test/live id
            'amount' => GatewayCurrency::format($converted), //transaction amount
            'payment_type' => 'VISA', //no need to change
            'currency' => $currency->chargeCode(),
            'tran_id' => $tran_id, //transaction id must be unique from your end
            'cus_name' => Auth::user()->name,  //customer name
            'cus_email' => $email, //customer email address
            'cus_add1' => '',  //customer address
            'cus_add2' => '', //customer address
            'cus_city' => '',  //customer city
            'cus_state' => '',  //state
            'cus_postcode' => '', //postcode or zipcode
            'cus_country' => 'Bangladesh',  //country
            'cus_phone' => Auth::user()->phone, //customer phone number
            'cus_fax' => 'Not¬Applicable',  //fax
            'ship_name' => '', //ship name
            'ship_add1' => '',  //ship address
            'ship_add2' => '',
            'ship_city' => '',
            'ship_state' => '',
            'ship_postcode' => '',
            'ship_country' => 'Bangladesh',
            'desc' => config('app.name').' payment',
            'success_url' => route('aamarpay.success'), //your success route
            'fail_url' => route('aamarpay.fail'), //your fail route
            'cancel_url' => route('cart'), //your cancel url
            'opt_a' => $payment['payment_type'],  //optional paramter (not read back)
            'opt_b' => '',
            'opt_c' => '',
            'opt_d' => '',
            'signature_key' => StorePaymentGateways::get('aamarpay', 'signature_key'), //signature key will provided aamarpay, contact integration@aamarpay.com for test/live signature
        ];

        try {
            $response = Http::asForm()->timeout(30)->post($this->baseUrl().'request.php', $fields);
        } catch (ConnectionException) {
            $response = null;
        }

        // aamarpay answers with the payment page's path (quoted, with slashes escaped); anything else is an error.
        $path = $response === null ? '' : str_replace('"', '', stripslashes((string) $response->body()));

        if ($response === null || ! $response->successful() || ! preg_match('#^[A-Za-z0-9_./?=&%-]+$#', $path) || str_contains($path, '..')) {
            Log::error('aamarpay gave no payment page.', ['status' => $response?->status()]);

            return $this->failed();
        }

        app(GatewayReturn::class)->begin('aamarpay', $tran_id, $payment, $converted, $currency->chargeCode());

        return redirect()->away($this->baseUrl().ltrim($path, '/'));
    }

    private function failed()
    {
        flash(__('storefront.checkout.payment_failed'))->error();

        return redirect()->route('cart');
    }

    /**
     * aamarpay posts the customer back here, cross-site, so the post carries no session (this
     * route runs without one: routes/shop-gateway-returns.php), and the post used to be trusted
     * for everything: the payment type, the order (`opt_b`) and the top-up amount (`opt_c`).
     * Only `mer_txnid` is read now: it must name a pending aamarpay payment pay() recorded,
     * aamarpay's search API must report it paid, and the reported amount and currency must be
     * the recorded converted amount to the cent. The recorded payer is credited, once.
     */
    public function success(Request $request){
        $returns = app(GatewayReturn::class);
        $tran_id = (string) $request->input('mer_txnid');
        $pending = $returns->find('aamarpay', $tran_id);

        if (is_string($pending)) {
            return $returns->refuse($pending, 'aamarpay', $tran_id);
        }

        if ($pending->completed_at !== null) {
            return $returns->finished($pending);
        }

        $transaction = $this->searchTransaction($tran_id);

        $isPaid = is_array($transaction)
            && ($transaction['pay_status'] ?? null) === 'Successful'
            && hash_equals($tran_id, (string) ($transaction['mer_txnid'] ?? ''));

        if (! $isPaid) {
            return $returns->refuse('not_verified', 'aamarpay', $tran_id, $pending);
        }

        // The search API names the charge currency `currency` (`currency_merchant` on some accounts).
        $reportedCurrency = (string) ($transaction['currency'] ?? $transaction['currency_merchant'] ?? '');

        return $returns->complete($pending, (float) ($transaction['amount'] ?? 0), $reportedCurrency, json_encode($transaction));
    }

    /**
     * The transaction as aamarpay's own server reports it.
     *
     * @return array<string, mixed>|null
     */
    private function searchTransaction(string $tran_id): ?array
    {
        $response = Http::timeout(30)->get($this->baseUrl().'api/v1/trxcheck/request.php', [
            'request_id' => $tran_id,
            'store_id' => StorePaymentGateways::get('aamarpay', 'store_id'),
            'signature_key' => StorePaymentGateways::get('aamarpay', 'signature_key'),
            'type' => 'json',
        ]);

        return $response->successful() && is_array($response->json()) ? $response->json() : null;
    }

    public function fail(Request $request){
        return app(GatewayReturn::class)->outcome('failed');
    }
}
