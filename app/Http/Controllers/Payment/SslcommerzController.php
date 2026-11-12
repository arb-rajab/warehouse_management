<?php
namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SSLCommerz;
use App\Services\GatewayCurrency;
use App\Services\GatewayReturn;
use App\Services\SessionGatewayPayment;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SslcommerzController extends Controller
{
    /**
     * Start the payment this session is making. The payer, what is paid for and the amount due
     * come from the database (SessionGatewayPayment::pending()); the amount is converted to
     * SSLCommerz's currency, and the payment is recorded as pending under the transaction id
     * sent to SSLCommerz (GatewayReturn), which is all success() reads from the return.
     */
    public function pay(Request $request)
    {
        $payment = app(SessionGatewayPayment::class)->pending();

        if ($payment === null) {
            flash(__('storefront.checkout.payment_failed'))->error();

            return redirect()->route('cart');
        }

        // The amount is in the store's currency; SSLCommerz is charged in its own.
        $currency = GatewayCurrency::sslcommerz();
        $amount = $currency->fromStore($payment['amount']);

        if ($amount === null) {
            Log::error('SSLCommerz refused: the store has no active rate for the gateway currency.', ['currency' => $currency->code]);
            flash(__('storefront.checkout.payment_failed'))->error();

            return redirect()->route('cart');
        }

        $tran_id = GatewayReturn::newReference();

        $post_data = [];
        $post_data['total_amount'] = $amount; # You cant not pay less than 10
        $post_data['currency'] = $currency->code;
        $post_data['tran_id'] = $tran_id;
        $post_data['value_a'] = $tran_id;
        $post_data['value_c'] = $payment['payment_type'];

        # CUSTOMER INFORMATION
        $user = Auth::user();
        $post_data['cus_name'] = $user->name;
        $post_data['cus_add1'] = $user->address;
        $post_data['cus_city'] = $user->city;
        $post_data['cus_postcode'] = $user->postal_code;
        $post_data['cus_country'] = $user->country;
        $post_data['cus_phone'] = $user->phone;
        $post_data['cus_email'] = $user->email;

        // route(), not the request root: the store lives under /temp, which the root does not carry.
        $post_data['success_url'] = route('sslcommerz.success');
        $post_data['fail_url'] = route('sslcommerz.fail');
        $post_data['cancel_url'] = route('sslcommerz.cancel');

        app(GatewayReturn::class)->begin('sslcommerz', $tran_id, $payment, $amount, $currency->chargeCode());

        $sslc = app(SSLCommerz::class);

        # initiate(Transaction Data , false: Redirect to SSLCOMMERZ gateway/ true: Show all the Payement gateway here )
        $payment_options = $sslc->initiate($post_data, false);
        if (!is_array($payment_options)) {
            print_r($payment_options);
            $payment_options = [];
        }

    }

    /**
     * SSLCommerz posts the customer back here, cross-site, so the post carries no session
     * (this route runs without one: routes/shop-gateway-returns.php). Only its `tran_id` is
     * read: it must name a pending SSLCommerz payment pay() recorded, SSLCommerz's validation
     * server must confirm it (orderValidate: the signature, `val_id`, the transaction), and
     * the confirmed amount and currency must be the recorded converted amount to the cent.
     * The recorded payer is credited, once (GatewayReturn).
     */
    public function success(Request $request)
    {
        $returns = app(GatewayReturn::class);
        $tran_id = (string) $request->input('tran_id');
        $pending = $returns->find('sslcommerz', $tran_id);

        if (is_string($pending)) {
            return $returns->refuse($pending, 'sslcommerz', $tran_id);
        }

        if ($pending->completed_at !== null) {
            return $returns->finished($pending);
        }

        $sslc = app(SSLCommerz::class);
        if ($sslc->orderValidate($tran_id, (float) $pending->gateway_amount, (string) $pending->gateway_currency, $request->all()) !== true) {
            return $returns->refuse('not_verified', 'sslcommerz', $tran_id, $pending);
        }

        // The validation server's own figure in the charge currency, not the post's.
        $result = $sslc->getResultData();
        $reported = (float) ($result->currency_amount ?? $result->amount ?? 0);

        return $returns->complete($pending, $reported, (string) ($result->currency_type ?? ''), json_encode($result));
    }

    public function fail(Request $request)
    {
        return app(GatewayReturn::class)->outcome('failed');
    }

    public function cancel(Request $request)
    {
        return app(GatewayReturn::class)->outcome('cancelled');
    }
}
