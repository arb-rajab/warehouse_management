<?php

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\StoreUser;
use Illuminate\Support\Facades\DB;

/**
 * Applying a coupon (the storefront's and the app's) took off the coupon's full
 * amount even when it was larger than what it applied to, so a 50-euro coupon on
 * a 5-euro cart made the order total negative; the app's copy also discounted
 * the customer's carts from other shops, crashed on a code that does not exist,
 * and ignored quantities. CouponService is the one calculation now.
 */
function couponFor(StoreUser $owner, array $attributes = []): Coupon
{
    return Coupon::forceCreate([
        'user_id' => $owner->id, 'type' => 'cart_base', 'code' => 'SAVE'.random_int(1000, 9999),
        'details' => json_encode(['min_buy' => 1, 'max_discount' => 1000]), 'discount' => 50, 'discount_type' => 'amount',
        'start_date' => strtotime('-1 day'), 'end_date' => strtotime('+1 day'),
        ...$attributes,
    ]);
}

function cartLine(StoreUser $customer, StoreUser $shopOwner, int $quantity, array $productAttributes = []): Cart
{
    $product = createStoreProduct(['user_id' => $shopOwner->id, ...$productAttributes]);

    return Cart::create(['user_id' => $customer->id, 'owner_id' => $shopOwner->id, 'product_id' => $product->id, 'quantity' => $quantity, 'variation' => '', 'price' => 2.5, 'tax' => 0, 'shipping_cost' => 0]);
}

test('a coupon larger than the cart takes off no more than the cart costs', function () {
    $seller = createStoreUser('seller');
    $customer = createStoreUser('customer');
    $line = cartLine($customer, $seller, 2); // 2 x 2.50
    $coupon = couponFor($seller);

    $this->withToken(storeApiToken($customer))->postJson('/temp/api/v2/coupon-apply', ['coupon_code' => $coupon->code])
        ->assertOk()->assertJsonPath('result', true);

    expect((float) $line->fresh()->discount)->toBe(5.0);
});

test('a percentage coupon is capped by its maximum discount and an amount coupon below the cart is paid in full', function () {
    $seller = createStoreUser('seller');
    $customer = createStoreUser('customer');
    $line = cartLine($customer, $seller, 10); // 25.00
    $capped = couponFor($seller, ['discount' => 50, 'discount_type' => 'percent', 'details' => json_encode(['min_buy' => 1, 'max_discount' => 4])]);

    $this->withToken(storeApiToken($customer))->postJson('/temp/api/v2/coupon-apply', ['coupon_code' => $capped->code])->assertJsonPath('result', true);
    expect((float) $line->fresh()->discount)->toBe(4.0);

    app('auth')->forgetGuards();
    $amount = couponFor($seller, ['discount' => 3]);
    $this->withToken(storeApiToken($customer))->postJson('/temp/api/v2/coupon-apply', ['coupon_code' => $amount->code])->assertJsonPath('result', true);
    expect((float) $line->fresh()->discount)->toBe(3.0);
});

test('a product coupon counts quantities and never exceeds the price of the lines it names', function () {
    $seller = createStoreUser('seller');
    $customer = createStoreUser('customer');
    $line = cartLine($customer, $seller, 3); // 3 x 2.50 = 7.50
    $coupon = couponFor($seller, ['type' => 'product_base', 'discount' => 4, 'details' => json_encode([['product_id' => $line->product_id]])]);

    $this->withToken(storeApiToken($customer))->postJson('/temp/api/v2/coupon-apply', ['coupon_code' => $coupon->code])->assertJsonPath('result', true);

    // 4 per unit x 3 = 12, held to what the line costs.
    expect((float) $line->fresh()->discount)->toBe(7.5);
});

test('a coupon discounts only the lines from its own shop', function () {
    $seller = createStoreUser('seller');
    $otherSeller = createStoreUser('seller');
    $customer = createStoreUser('customer');
    $mine = cartLine($customer, $seller, 2);
    $other = cartLine($customer, $otherSeller, 2);
    $coupon = couponFor($seller);

    $this->withToken(storeApiToken($customer))->postJson('/temp/api/v2/coupon-apply', ['coupon_code' => $coupon->code])->assertJsonPath('result', true);

    expect((float) $mine->fresh()->discount)->toBe(5.0)
        ->and((float) $other->fresh()->discount)->toBe(0.0)
        ->and($other->fresh()->coupon_code)->toBeNull();
});

test('a code that does not exist, one that expired and one already used are refused', function () {
    $seller = createStoreUser('seller');
    $customer = createStoreUser('customer');
    $line = cartLine($customer, $seller, 2);
    $expired = couponFor($seller, ['start_date' => strtotime('-10 days'), 'end_date' => strtotime('-5 days')]);
    $used = couponFor($seller);
    DB::table('coupon_usages')->insert(['user_id' => $customer->id, 'coupon_id' => $used->id]);
    $token = storeApiToken($customer);

    foreach (['NOPE-123', $expired->code, $used->code] as $code) {
        app('auth')->forgetGuards();
        $this->withToken($token)->postJson('/temp/api/v2/coupon-apply', ['coupon_code' => $code])->assertOk()->assertJsonPath('result', false);
    }

    expect((float) $line->fresh()->discount)->toBe(0.0);
});

test('the storefront applies the same capped discount', function () {
    seedStoreCurrency();
    $seller = createStoreUser('seller');
    $customer = createStoreUser('customer');
    $line = cartLine($customer, $seller, 2);
    $coupon = couponFor($seller);
    actingAsStoreUser($customer);

    $this->post(route('checkout.apply_coupon_code'), ['code' => $coupon->code]);

    expect((float) $line->fresh()->discount)->toBe(5.0);
});
