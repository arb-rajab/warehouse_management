<?php

namespace App\Services;

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\WalletController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * The storefront gateways' callbacks finish the payment the signed-in
 * customer's session started, never one the callback names: the order and the
 * package come from the session, the amount due from the database, and the
 * amount paid from the gateway's own server. Every web gateway used to take the
 * order, the package or the amount from its callback's query string or form.
 */
class SessionGatewayPayment
{
    /**
     * What the signed-in customer owes for the payment their session started,
     * read from the database (for a wallet top-up, the amount they asked for).
     */
    public function amountDue(): ?float
    {
        $payerId = Auth::id();
        $paymentData = (array) Session::get('payment_data', []);

        if ($payerId === null) {
            return null;
        }

        return app(GatewayPaymentService::class)->amountDue(
            (string) Session::get('payment_type'),
            (int) $payerId,
            Session::has('combined_order_id') ? (int) Session::get('combined_order_id') : null,
            self::sessionPackageId($paymentData),
            isset($paymentData['amount']) ? (float) $paymentData['amount'] : null,
        );
    }

    /**
     * Credit `$payment` to whatever the signed-in customer's session is paying
     * for, once.
     */
    public function complete(VerifiedPayment $payment): RedirectResponse
    {
        $paymentType = Session::get('payment_type');
        $paymentData = (array) Session::get('payment_data', []);
        $payerId = Auth::id();
        $packageId = self::sessionPackageId($paymentData);

        if ($paymentType === GatewayPaymentService::CART) {
            return (new CheckoutController)->checkout_done((int) Session::get('combined_order_id'), $payment);
        }

        if ($paymentType === GatewayPaymentService::WALLET) {
            return (new WalletController)->wallet_payment_done($payment);
        }

        $isPackage = in_array($paymentType, [GatewayPaymentService::CUSTOMER_PACKAGE, GatewayPaymentService::SELLER_PACKAGE], true);

        if ($isPackage && $payerId !== null && $packageId !== null
            && app(GatewayPaymentService::class)->complete($payment, (int) $payerId, (string) $paymentType, null, $packageId)) {
            Session::forget('payment_data');
            Session::forget('payment_type');
            flash(__('storefront.common.package_purchasing_successful'))->success();

            return redirect()->route('dashboard');
        }

        return $this->notVerified();
    }

    public function notVerified(): RedirectResponse
    {
        flash(__('storefront.checkout.payment_could_not_be_verified'))->error();

        return redirect()->route('home');
    }

    /**
     * @param  array<mixed>  $paymentData
     */
    private static function sessionPackageId(array $paymentData): ?int
    {
        $packageId = $paymentData['customer_package_id'] ?? $paymentData['seller_package_id'] ?? null;

        return is_numeric($packageId) ? (int) $packageId : null;
    }
}
