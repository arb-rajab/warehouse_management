<?php

use App\Models\Cart;
use App\Models\Order;
use App\Models\StoreUser;
use App\Models\Visit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The storefront checkout's coupon, club-point, customer-picker and manual-payment
 * steps took whatever the browser sent: a coupon on an empty cart divided by
 * zero, a rep could "choose" any account (or none, crashing), a negative point
 * count was stored, and a manual payment's proof was stored unchecked.
 */
function createRepresentative(): StoreUser
{
    $rep = createStoreUser('customer');
    $rep->forceFill(['is_rep' => true])->save();

    return $rep;
}

test('applying a coupon to an empty cart answers with a warning instead of failing', function () {
    $seller = createStoreUser('seller');
    $customer = createStoreUser('customer');
    $coupon = couponFor($seller);
    actingAsStoreUser($customer);

    $this->post(route('checkout.apply_coupon_code'), ['code' => $coupon->code])
        ->assertOk()
        ->assertJsonPath('response_message.response', 'warning');

    expect(Cart::count())->toBe(0);
});

test('a coupon from a shop the cart has no lines from is not applicable and leaves the cart alone', function () {
    seedStoreCurrency();
    $seller = createStoreUser('seller');
    $otherSeller = createStoreUser('seller');
    $customer = createStoreUser('customer');
    $line = orderableCartLine($customer, $otherSeller, 2);
    $coupon = couponFor($seller);
    actingAsStoreUser($customer);

    $this->post(route('checkout.apply_coupon_code'), ['code' => $coupon->code])
        ->assertOk()
        ->assertJsonPath('response_message.response', 'warning');

    expect((float) $line->fresh()->discount)->toBe(0.0);
});

test('a coupon request without a code is rejected', function () {
    actingAsStoreUser(createStoreUser('customer'));

    $this->postJson(route('checkout.apply_coupon_code'), [])->assertUnprocessable()->assertJsonValidationErrors('code');
});

test('a forged club point count is rejected and nothing is stored in the session', function (mixed $point) {
    DB::table('addons')->insert(['name' => 'Club point', 'unique_identifier' => 'club_point', 'activated' => 1]);
    Cache::forget('addons');
    actingAsStoreUser(createStoreUser('customer'));

    $this->post(route('checkout.apply_club_point'), ['point' => $point])->assertSessionHasErrors('point');

    expect(session()->has('club_point'))->toBeFalse();
})->with(['negative' => -5, 'zero' => 0, 'fraction' => 1.5, 'text' => 'many', 'missing' => null]);

test('a representative opens a visit for a customer and a stranger gets nothing', function () {
    $rep = createRepresentative();
    $customer = createStoreUser('customer');
    $noise = createStoreUser('customer');
    actingAsStoreUser($rep);

    $this->postJson(route('checkout.chooseCustomer'), ['id' => $customer->id])->assertOk()->assertJsonPath('Success', 'Success');

    expect(Visit::where('rep_id', $rep->id)->pluck('customer_id')->all())->toBe([$customer->id])
        ->and(Visit::where('customer_id', $noise->id)->count())->toBe(0);
});

test('a representative cannot choose an account that is not a customer', function (string $target) {
    $rep = createRepresentative();
    $other = createRepresentative();
    $seller = createStoreUser('seller');
    $ids = ['missing' => 9999, 'themselves' => $rep->id, 'another rep' => $other->id, 'a seller' => $seller->id];
    actingAsStoreUser($rep);

    $this->postJson(route('checkout.chooseCustomer'), ['id' => $ids[$target]])->assertNotFound();

    expect(Visit::count())->toBe(0);
})->with(['missing', 'themselves', 'another rep', 'a seller']);

test('choosing a customer needs an id', function () {
    actingAsStoreUser(createRepresentative());

    $this->postJson(route('checkout.chooseCustomer'), [])->assertUnprocessable()->assertJsonValidationErrors('id');

    expect(Visit::count())->toBe(0);
});

test('a customer who is not a representative cannot choose a customer', function () {
    $customer = createStoreUser('customer');
    actingAsStoreUser(createStoreUser('customer'));

    $this->postJson(route('checkout.chooseCustomer'), ['id' => $customer->id])->assertForbidden();

    expect(Visit::count())->toBe(0);
});

test('a manual payment without a transaction id places no order and keeps the cart', function (array $proof) {
    seedStoreCurrency();
    createStoreUser('admin');
    addManualPaymentColumnsToOrders();
    $product = createStoreProduct(['current_stock' => 50]);
    $customer = actingAsStoreUser(createStoreUser('customer'));
    Cart::create(['user_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 1, 'address_id' => addStoreAddress($customer->id)]);

    $this->from('/temp/checkout')->post('/temp/checkout/payment', ['payment_option' => 'manual_payment_1', ...$proof])
        ->assertRedirect('/temp/checkout')
        ->assertSessionHasErrors();

    expect(Order::count())->toBe(0)
        ->and(Cart::where('user_id', $customer->id)->count())->toBe(1)
        ->and(DB::table('product_stocks')->where('product_id', $product->id)->value('qty'))->toBe(50);
})->with([
    'no proof' => [[]],
    'a receipt that is not an upload id or file name' => [['trx_id' => 'T1', 'photo' => 'not a receipt!']],
    'an oversized transaction id' => [['trx_id' => str_repeat('x', 101)]],
]);
