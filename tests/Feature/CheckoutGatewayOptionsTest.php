<?php

use App\Services\PaymentOptions;
use Illuminate\Support\Facades\DB;

/**
 * The checkout, the wallet top-up and the customer packages offer an online
 * gateway only when it is enabled in `STORE_PAYMENT_GATEWAYS` and switched on in
 * the back office. With none enabled the pages are as they were: cash on delivery,
 * and the gateway card stays hidden.
 */
function goToPaymentStep(mixed $test): string
{
    seedStoreCurrency();
    createStoreUser('admin');
    $product = createStoreProduct(['unit_price' => 2.5, 'current_stock' => 50]);
    $customer = actingAsStoreUser(createStoreUser('customer'));
    $addressId = addStoreAddress($customer->id);

    $test->post('/temp/cart/addtocart', ['id' => $product->id, 'quantity' => 1])->assertOk();
    $test->post('/temp/checkout/delivery_info', ['address_id' => $addressId])->assertOk();

    return $test->post('/temp/checkout/payment_select')->assertOk()->getContent();
}

function switchOn(string ...$settings): void
{
    foreach ($settings as $setting) {
        DB::table('business_settings')->updateOrInsert(['type' => $setting], ['value' => '1']);
    }
}

beforeEach(function () {
    $this->withoutVite();
    switchOn('cash_payment');
});

test('with no gateway enabled the payment step offers only cash on delivery, and the card stays hidden', function () {
    switchOn('payhere', 'bkash');
    config(['store.enabled_payment_gateways' => []]);

    $html = goToPaymentStep($this);

    expect($html)->toContain('value="cash_on_delivery"')
        ->not->toContain('value="payhere"')
        ->not->toContain('value="bkash"')
        ->and($html)->toMatch('/card-body text-center px-4 pt-0\s+d-none/');
});

test('an enabled and switched-on gateway is offered, selected first, and the card is shown', function () {
    switchOn('payhere');
    config(['store.enabled_payment_gateways' => ['payhere']]);

    $html = goToPaymentStep($this);

    expect($html)->toContain('value="payhere"')
        ->toContain('value="cash_on_delivery"')
        ->and($html)->toMatch('/value="payhere"[^>]*checked/')
        ->and($html)->not->toMatch('/value="cash_on_delivery"[^>]*checked/')
        ->and($html)->not->toMatch('/card-body text-center px-4 pt-0\s+d-none/');
});

test('a gateway needs both switches', function (array $enabled, array $settings) {
    switchOn(...$settings);
    config(['store.enabled_payment_gateways' => $enabled]);

    expect(app(PaymentOptions::class)->available())->toBe([]);
})->with([
    'in the config only' => [['nagad'], []],
    'in the back office only' => [[], ['nagad']],
    'another gateway in the config' => [['bkash'], ['nagad']],
]);

test('every gateway the store can charge has a setting, a logo and a name', function () {
    foreach (PaymentOptions::GATEWAYS as $key => $gateway) {
        expect(config('store.payment_gateway_controllers'))->toHaveKey($key)
            ->and(public_path('assets/img/cards/'.$gateway['image']))->toBeFile()
            ->and($gateway['name'])->not->toBeEmpty();
    }
});

test('the wallet top-up and customer package pages list the same gateways', function () {
    switchOn('aamarpay', 'nagad');
    config(['store.enabled_payment_gateways' => ['aamarpay']]);
    actingAsStoreUser(createStoreUser('customer'));

    $wallet = view('shop.frontend.partials.wallet_modal')->render();
    $packages = file_get_contents(resource_path('views/shop/frontend/user/customer_packages_lists.blade.php'));

    expect($wallet)->toContain('<option value="aamarpay">AamarPay</option>')->not->toContain('value="nagad"')
        ->and($packages)->toContain('PaymentOptions::class)->available()')->not->toContain("get_setting('nagad')");
});
