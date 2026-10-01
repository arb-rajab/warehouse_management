<?php

namespace App\Services;

/**
 * Which payment options a customer may pick at the checkout, the wallet recharge
 * and the customer-package purchase.
 *
 * Those three build the payment controller's class name from the `payment_option`
 * the browser sends and run its `pay()`, so the gateway routes' 404 for a
 * disabled gateway never applied to them. An option that names a gateway the
 * store has not enabled (`STORE_PAYMENT_GATEWAYS`) is refused here; cash on
 * delivery, the wallet and the manual payment methods (names with no gateway
 * controller) pass.
 */
class PaymentOptions
{
    private const CONTROLLER_NAMESPACE = 'App\\Http\\Controllers\\Payment\\';

    public function isAllowed(string $option): bool
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $option)) {
            return false;
        }

        $controller = self::CONTROLLER_NAMESPACE.str_replace(' ', '', ucwords(str_replace('_', ' ', $option))).'Controller';

        foreach (config('store.payment_gateway_controllers', []) as $gateway => $controllers) {
            if (in_array($controller, $controllers, true)) {
                return in_array($gateway, config('store.enabled_payment_gateways', []), true);
            }
        }

        return true;
    }
}
