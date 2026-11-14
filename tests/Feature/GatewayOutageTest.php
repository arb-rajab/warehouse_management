<?php

use App\Models\CombinedOrder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * A payment provider that does not answer used to surface as a 500 in the middle
 * of checkout. Nothing was charged, so the customer goes back to the cart. The
 * handler is global (bootstrap/app.php); VoguePay's verification call is one that
 * does not catch the failure itself.
 */
test('a provider that does not answer returns the customer to the cart, not a 500', function () {
    config(['store.enabled_payment_gateways' => ['voguepay']]);
    $this->withoutVite();
    $customer = createStoreUser('customer');
    actingAsStoreUser($customer);
    $combined = CombinedOrder::create(['user_id' => $customer->id, 'shipping_address' => '{}', 'grand_total' => 30]);
    createStoreOrder($customer, ['combined_order_id' => $combined->id, 'grand_total' => 30]);
    Http::preventStrayRequests();
    Http::fake(fn () => throw new ConnectionException('provider down'));

    $this->withSession(['payment_type' => 'cart_payment', 'combined_order_id' => $combined->id, 'voguepay_merchant_ref' => 'REF-1'])
        ->get(route('voguepay.success', ['id' => 'TX-1']))
        ->assertRedirect(route('cart'));
});
