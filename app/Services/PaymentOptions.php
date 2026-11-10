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

    /**
     * The online gateways a customer can be offered: the back office setting that
     * switches each on, its logo under `assets/img/cards`, and its brand name.
     *
     * @var array<string, array{setting: string, image: string, name: string}>
     */
    public const GATEWAYS = [
        'sslcommerz' => ['setting' => 'sslcommerz_payment', 'image' => 'sslcommerz.png', 'name' => 'SSLCommerz'],
        'voguepay' => ['setting' => 'voguepay', 'image' => 'vogue.png', 'name' => 'VoguePay'],
        'payhere' => ['setting' => 'payhere', 'image' => 'payhere.png', 'name' => 'PayHere'],
        'ngenius' => ['setting' => 'ngenius', 'image' => 'ngenius.png', 'name' => 'N-Genius'],
        'iyzico' => ['setting' => 'iyzico', 'image' => 'iyzico.png', 'name' => 'Iyzico'],
        'nagad' => ['setting' => 'nagad', 'image' => 'nagad.png', 'name' => 'Nagad'],
        'bkash' => ['setting' => 'bkash', 'image' => 'bkash.png', 'name' => 'bKash'],
        'aamarpay' => ['setting' => 'aamarpay', 'image' => 'aamarpay.png', 'name' => 'AamarPay'],
    ];

    /**
     * The gateways to show: enabled in `STORE_PAYMENT_GATEWAYS` (the deploy-level
     * switch the routes and `isAllowed()` enforce) and switched on in the back
     * office. Empty while no gateway is enabled, so the pages show nothing extra.
     *
     * @return array<string, array{setting: string, image: string, name: string}>
     */
    public function available(): array
    {
        $enabled = config('store.enabled_payment_gateways', []);

        return array_filter(
            self::GATEWAYS,
            fn (array $gateway, string $key): bool => in_array($key, $enabled, true) && get_setting($gateway['setting']) == 1,
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * The proof a customer attaches to a manual payment: the transaction id
     * and the uploaded receipt. Receipts are stored upload ids or file names,
     * never markup.
     *
     * @return array<string, list<string>>
     */
    public function proofRules(): array
    {
        return [
            'trx_id' => ['required', 'string', 'max:100'],
            'photo' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_,.\/-]+$/'],
        ];
    }

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
