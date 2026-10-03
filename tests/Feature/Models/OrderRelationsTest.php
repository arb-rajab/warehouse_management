<?php

use App\Models\Cart;
use App\Models\Order;
use App\Models\StoreUser;
use Illuminate\Support\Facades\DB;

test('the customer of an order a representative placed is the customer it was placed for', function () {
    $rep = createStoreUser('customer');
    $customer = createStoreUser('customer');
    $order = createStoreOrder($rep, ['by_rep' => true, 'for_customer' => $customer->id]);

    expect($order->customer->id)->toBe($customer->id)
        ->and($order->representative->id)->toBe($rep->id);
});

test('the customer of a direct order is the user who placed it, with no representative', function () {
    $customer = createStoreUser('customer');
    $order = createStoreOrder($customer);

    expect($order->customer->id)->toBe($customer->id)
        ->and($order->representative)->toBeNull();
});

test('customer and representative resolve per order across a mixed result set', function () {
    // With customer() as a relation, the model it was called on during eager
    // loading was blank, so by_rep was always null and every order resolved
    // through user_id.
    $rep = createStoreUser('customer');
    $customer = createStoreUser('customer');
    $direct = createStoreUser('customer');
    $forCustomer = createStoreOrder($rep, ['by_rep' => true, 'for_customer' => $customer->id]);
    $directOrder = createStoreOrder($direct);

    $orders = Order::query()
        ->with(['rep:id,name', 'user:id,name'])
        ->whereIn('id', [$forCustomer->id, $directOrder->id])
        ->get()
        ->keyBy('id');

    DB::enableQueryLog();

    expect($orders[$forCustomer->id]->customer->id)->toBe($customer->id)
        ->and($orders[$directOrder->id]->customer->id)->toBe($direct->id)
        ->and($orders[$forCustomer->id]->representative->id)->toBe($rep->id)
        ->and(DB::getQueryLog())->toBeEmpty();
});

test('proxy payment references stay attached to their own order when eager loaded', function () {
    $customer = createStoreUser('customer');
    $first = createStoreOrder($customer);
    $second = createStoreOrder($customer);
    $untouched = createStoreOrder($customer);

    foreach ([[$first, 'REF-A'], [$second, 'REF-B'], [$second, 'REF-C']] as [$order, $reference]) {
        DB::table('proxypay_payments')->insert([
            'payment_type' => 'cart_payment',
            'reference_id' => $reference,
            'order_id' => $order->id,
            'user_id' => $customer->id,
        ]);
    }

    $orders = Order::query()->select('id')->with('proxy_cart_reference_id')->get()->keyBy('id');

    expect($orders[$first->id]->proxy_cart_reference_id->pluck('reference_id')->all())->toBe(['REF-A'])
        ->and($orders[$second->id]->proxy_cart_reference_id->pluck('reference_id')->sort()->values()->all())->toBe(['REF-B', 'REF-C'])
        ->and($orders[$untouched->id]->proxy_cart_reference_id)->toHaveCount(0);
});

test('a cart has no always-true carts_offer relation any more', function () {
    expect(method_exists(Cart::class, 'carts_offer'))->toBeFalse()
        ->and(method_exists(StoreUser::class, 'carts_offered'))->toBeTrue();
});
