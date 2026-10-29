<?php
namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\CombinedOrder;
use App\Models\CustomerPackage;
use App\Models\SellerPackage;
use App\Services\GatewayPaymentService;
use App\Services\SessionGatewayPayment;
use App\Services\VerifiedPayment;
use App\Utility\NgeniusUtility;
use Session;

class NgeniusController extends Controller
{
    public function pay()
    {
        if (Session::get('payment_type') == 'cart_payment') {
            $combined_order = CombinedOrder::findOrFail(Session::get('combined_order_id'));
            $amount = round($combined_order->grand_total * 100);
            //will be redirected
            NgeniusUtility::make_payment(route('ngenius.cart_payment_callback'),"cart_payment",$amount);
        } elseif (Session::get('payment_type') == 'wallet_payment') {
            $amount = round(Session::get('payment_data')['amount'] * 100);
            //will be redirected
            NgeniusUtility::make_payment(route('ngenius.wallet_payment_callback'),"wallet_payment",$amount);
        } elseif (Session::get('payment_type') == 'customer_package_payment') {
            $customer_package = CustomerPackage::findOrFail(Session::get('payment_data')['customer_package_id']);
            $amount = round($customer_package->amount * 100);
            //will be redirected
            NgeniusUtility::make_payment(route('ngenius.customer_package_payment_callback'),"customer_package_payment",$amount);
        } elseif (Session::get('payment_type') == 'seller_package_payment') {
            $seller_package = SellerPackage::findOrFail(Session::get('payment_data')['seller_package_id']);
            $amount = round($seller_package->amount * 100);
            //will be redirected
            NgeniusUtility::make_payment(route('ngenius.seller_package_payment_callback'),"seller_package_payment",$amount);
        }


        $seller_package_id = Session::get('payment_data')['seller_package_id'];
        $seller_package  = SellerPackage::findOrFail($seller_package_id);

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
        $paid = (float) ($order['amount']['value'] ?? 0) / 100;
        $isCaptured = is_array($order)
            && hash_equals($ref, (string) ($order['reference'] ?? ''))
            && ($order['_embedded']['payment'][0]['state'] ?? null) === 'CAPTURED'
            && GatewayPaymentService::covers($paid, $amountDue);

        if (! $isCaptured) {
            Session::forget('payment_data');
            flash(__('storefront.checkout.payment_incomplete'))->error();
            return redirect()->route('home');
        }

        Session::forget('ngenius_order_ref');

        return app(SessionGatewayPayment::class)->complete(new VerifiedPayment('ngenius', $ref, $paid, json_encode($order)));
    }
}
