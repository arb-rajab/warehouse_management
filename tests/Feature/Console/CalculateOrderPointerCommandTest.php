<?php

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\StoreUser;

function orderWithLine(float $price, float $originalPrice, bool $isPriceChanged, float $profits, ?StoreUser $customer = null): Order
{
    $order = createStoreOrder($customer ?? createStoreUser('customer'));

    OrderDetail::create([
        'order_id' => $order->id,
        'product_id' => 1,
        'price' => $price,
        'original_price' => $originalPrice,
        'is_price_changed' => $isPriceChanged,
        'profits' => $profits,
    ]);

    return $order;
}

test('it recomputes the discount percent and indicator of every order', function () {
    $changed = orderWithLine(80, 100, true, 20);
    $unchanged = orderWithLine(50, 100, false, 10);

    $this->artisan('command:calculate-order-pointer')->assertSuccessful();

    // price 80 against original 100: a 20 percent discount, 20 profit on 80 is 25.
    expect((float) $changed->fresh()->discount_percent)->toEqualWithDelta(20.0, 0.001)
        ->and($changed->fresh()->indicator)->toEqualWithDelta(25.0, 0.001)
        // an unchanged price is its own original, so there is no discount.
        ->and((float) $unchanged->fresh()->discount_percent)->toEqualWithDelta(0.0, 0.001)
        ->and($unchanged->fresh()->indicator)->toEqualWithDelta(20.0, 0.001);
});

test('an order with nothing priced gets a full discount and a zero indicator instead of dividing by zero', function () {
    $free = orderWithLine(0, 0, false, 0);
    $empty = createStoreOrder(createStoreUser('customer'));

    $this->artisan('command:calculate-order-pointer')->assertSuccessful();

    expect((float) $free->fresh()->discount_percent)->toEqualWithDelta(100.0, 0.001)
        ->and($free->fresh()->indicator)->toEqualWithDelta(0.0, 0.001)
        ->and((float) $empty->fresh()->discount_percent)->toEqualWithDelta(100.0, 0.001)
        ->and($empty->fresh()->indicator)->toEqualWithDelta(0.0, 0.001);
});

test('a zero rep price against a priced original does not divide by zero', function () {
    $order = orderWithLine(0, 100, true, 5);

    $this->artisan('command:calculate-order-pointer')->assertSuccessful();

    expect((float) $order->fresh()->discount_percent)->toEqualWithDelta(100.0, 0.001)
        ->and($order->fresh()->indicator)->toEqualWithDelta(0.0, 0.001);
});

test('it walks more orders than one chunk', function () {
    $customer = createStoreUser('customer');
    $orders = collect(range(1, 205))->map(fn () => orderWithLine(80, 100, true, 20, $customer));

    $this->artisan('command:calculate-order-pointer')->assertSuccessful();

    expect($orders->every(fn ($order) => (float) $order->fresh()->discount_percent === 20.0))->toBeTrue();
});
