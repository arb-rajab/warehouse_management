<?php
namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\GatewayCurrency;
use App\Services\GatewayPaymentService;
use App\Services\SessionGatewayPayment;
use App\Services\VerifiedPayment;
use App\Utility\NgeniusUtility;
use Illuminate\Support\Facades\Log;
use Session;

class NgeniusController extends Controller
{
    /**
     * Start the payment this session is making. The amount due comes from the database (an
     * order of the signed-in customer, a package's price, the top-up asked for), is converted
     * to the gateway currency and sent in minor units. A currency the store has no active rate
     * for, or an N-Genius that gives no payment page, is refused with a message and nothing is
     * recorded.
     */
    public function pay()
    {
        $callbacks = [
            'cart_payment' => 'ngenius.cart_payment_callback',
            'wallet_payment' => 'ngenius.wallet_payment_callback',
            'customer_package_payment' => 'ngenius.customer_package_payment_callback',
            'seller_package_payment' => 'ngenius.seller_package_payment_callback',
        ];
        $paymentType = (string) Session::get('payment_type');
        $due = app(SessionGatewayPayment::class)->amountDue();
        $currency = GatewayCurrency::forGateway('ngenius');
        $converted = $due === null ? null : $currency->fromStore($due);

        if (! isset($callbacks[$paymentType]) || $converted === null) {
            Log::error('N-Genius refused: nothing due, or the store has no active rate for the gateway currency.', ['type' => $paymentType, 'currency' => $currency->chargeCode()]);

            return $this->failed();
        }

        $redirect = NgeniusUtility::make_payment(route($callbacks[$paymentType]), $paymentType, (int) round($converted * 100), $currency->chargeCode());

        if ($redirect === null) {
            Log::error('N-Genius gave no payment page.', ['type' => $paymentType, 'mode' => NgeniusUtility::getMode()]);

            return $this->failed();
        }

        return $redirect;
    }

    private function failed()
    {
        flash(__('storefront.checkout.payment_failed'))->error();

        return redirect()->route('cart');
    }

    public function cart_payment_callback()
    {
        return $this->completeCallback('cart_payment');
    }

    public function wallet_payment_callback()
    {
        return $this->completeCallback('wallet_payment');
    }

    public function customer_package_payment_callback()
    {
        return $this->completeCallback('customer_package_payment');
    }

    public function seller_package_payment_callback()
    {
        return $this->completeCallback('seller_package_payment');
    }

    /**
     * N-Genius sends the customer back with `?ref=`, and any captured order's
     * ref used to pay whatever this session was paying for. The ref must be the
     * order this session created, for the payment type this session started,
     * N-Genius's server must report it captured for at least the amount due, and
     * a ref completes one payment only.
     */
    private function completeCallback(string $payment_type)
    {
        $ref = (string) request()->input('ref');
        $expected = (string) Session::get('ngenius_order_ref');
        $amountDue = app(SessionGatewayPayment::class)->amountDue();

        if ($ref === '' || $expected === '' || ! hash_equals($expected, $ref) || Session::get('payment_type') !== $payment_type || $amountDue === null) {
            return app(SessionGatewayPayment::class)->notVerified();
        }

        $order = NgeniusUtility::retrieve_order($ref);
        $currency = GatewayCurrency::forGateway('ngenius');
        $paid = (float) ($order['amount']['value'] ?? 0) / 100;
        $isCaptured = is_array($order)
            && hash_equals($ref, (string) ($order['reference'] ?? ''))
            && ($order['_embedded']['payment'][0]['state'] ?? null) === 'CAPTURED'
            && strtoupper((string) ($order['amount']['currencyCode'] ?? '')) === $currency->chargeCode()
            && ($currency->isStoreCurrency() ? GatewayPaymentService::covers($paid, $amountDue) : $currency->matchesDue($paid, $amountDue));

        if (! $isCaptured) {
            Session::forget('payment_data');
            flash(__('storefront.checkout.payment_incomplete'))->error();
            return redirect()->route('home');
        }

        Session::forget('ngenius_order_ref');

        return app(SessionGatewayPayment::class)->complete(new VerifiedPayment('ngenius', $ref, $currency->paidInStoreCurrency($paid, $amountDue), json_encode($order)));
    }
}
