<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\GatewayCurrency;
use App\Services\GatewayPaymentService;
use App\Services\SessionGatewayPayment;
use App\Services\VerifiedPayment;
use Auth;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Session;

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
     * is kept.
     */
    public function pay()
    {
        if (Auth::user()->phone == null) {
            flash(__('storefront.auth.please_add_phone_number_to_your_profile'))->warning();

            return redirect()->route('profile');
        }

        $email = Auth::user()->email ?: config('mail.from.address');

        $due = app(SessionGatewayPayment::class)->amountDue();
        $currency = GatewayCurrency::forGateway('aamarpay');
        $converted = $due === null ? null : $currency->fromStore($due);

        if ($converted === null) {
            Log::error('aamarpay refused: nothing due, or the store has no active rate for the gateway currency.', ['currency' => $currency->chargeCode()]);

            return $this->failed();
        }

        // The transaction id success() checks against aamarpay's search API.
        $tran_id = Str::random(20);

        $fields = [
            'store_id' => config('services.aamarpay.store_id'), //store id will be aamarpay,  contact integration@aamarpay.com for test/live id
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
            'opt_a' => Session::get('payment_type'),  //optional paramter
            'opt_b' => Session::get('combined_order_id'),
            'opt_c' => json_encode(Session::get('payment_data')),
            'opt_d' => '',
            'signature_key' => config('services.aamarpay.signature_key'), //signature key will provided aamarpay, contact integration@aamarpay.com for test/live signature
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

        Session::put('aamarpay_tran_id', $tran_id);

        return redirect()->away($this->baseUrl().ltrim($path, '/'));
    }

    private function failed()
    {
        flash(__('storefront.checkout.payment_failed'))->error();

        return redirect()->route('cart');
    }

    /**
     * aamarpay posts the customer back here, and the post used to be trusted for
     * everything: the payment type, the order (`opt_b`) and the top-up amount
     * (`opt_c`). The transaction must be the one this session started, and
     * aamarpay's search API (by `mer_txnid`) must report it paid for at least the
     * amount due; what it reports is credited to the signed-in customer, once.
     */
    public function success(Request $request){
        $tran_id = (string) Session::get('aamarpay_tran_id');
        $amountDue = app(SessionGatewayPayment::class)->amountDue();

        if ($tran_id === '' || $amountDue === null || ! hash_equals($tran_id, (string) $request->input('mer_txnid'))) {
            return app(SessionGatewayPayment::class)->notVerified();
        }

        $transaction = $this->searchTransaction($tran_id);
        $currency = GatewayCurrency::forGateway('aamarpay');
        $reported = is_array($transaction) ? (float) ($transaction['amount'] ?? 0) : 0.0;

        // Paid, for the amount due: exactly in the store's own currency, else the converted amount to the cent.
        $isPaid = is_array($transaction)
            && ($transaction['pay_status'] ?? null) === 'Successful'
            && hash_equals($tran_id, (string) ($transaction['mer_txnid'] ?? ''))
            && ($currency->isStoreCurrency() ? GatewayPaymentService::covers($reported, $amountDue) : $currency->matchesDue($reported, $amountDue));

        if (! $isPaid) {
            return app(SessionGatewayPayment::class)->notVerified();
        }

        Session::forget('aamarpay_tran_id');

        return app(SessionGatewayPayment::class)->complete(new VerifiedPayment('aamarpay', $tran_id, $currency->paidInStoreCurrency($reported, $amountDue), json_encode($transaction)));
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
            'store_id' => config('services.aamarpay.store_id'),
            'signature_key' => config('services.aamarpay.signature_key'),
            'type' => 'json',
        ]);

        return $response->successful() && is_array($response->json()) ? $response->json() : null;
    }

    public function fail(Request $request){
        flash(__('storefront.checkout.payment_failed'))->error();
    	return redirect()->route('cart');
    }
}
