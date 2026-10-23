<?php

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\StoreUser;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

/**
 * The seller screens and the seller API found an order, a ticket or a query by an
 * id from the request, whoever it belonged to: any seller could cancel another
 * seller's order (refunding a wallet order's total to the customer, again on
 * every repeat, and restocking again each time), mark it paid or unpaid, and
 * reply to any ticket as any user.
 */
function walletOrderOf(StoreUser $seller, StoreUser $customer, Product $product): Order
{
    $order = createStoreOrder($customer, [
        'seller_id' => $seller->id, 'payment_type' => 'wallet', 'payment_status' => 'paid', 'delivery_status' => 'pending', 'grand_total' => 20,
        'shipping_address' => json_encode(['city' => 'Amsterdam', 'phone' => '612345678']),
    ]);
    OrderDetail::forceCreate([
        'order_id' => $order->id, 'seller_id' => $seller->id, 'product_id' => $product->id, 'price' => 5, 'tax' => 0,
        'quantity' => 2, 'variation' => '', 'payment_status' => 'paid', 'delivery_status' => 'pending',
    ]);

    return $order;
}

function stockOf(Product $product): int
{
    return (int) DB::table('product_stocks')->where('product_id', $product->id)->value('qty');
}

test('a seller cannot change another seller\'s order, on the web or through the app API', function () {
    $owner = createStoreUser('seller');
    $rival = createStoreUser('seller');
    $customer = createStoreUser('customer');
    $product = createStoreProduct(['user_id' => $owner->id, 'current_stock' => 10]);
    $order = walletOrderOf($owner, $customer, $product);

    actingAsStoreUser($rival);
    $this->post('/temp/seller/orders/update_delivery_status', ['order_id' => $order->id, 'status' => 'cancelled'])->assertNotFound();
    $this->post('/temp/seller/orders/update_payment_status', ['order_id' => $order->id, 'status' => 'unpaid'])->assertNotFound();

    app('auth')->forgetGuards();
    $token = storeApiToken($rival);
    $this->withToken($token)->postJson('/temp/api/v2/seller/orders/update-delivery-status', ['order_id' => $order->id, 'status' => 'cancelled'])->assertNotFound();
    app('auth')->forgetGuards();
    $this->withToken($token)->postJson('/temp/api/v2/seller/orders/update-payment-status', ['order_id' => $order->id, 'status' => 'unpaid'])->assertNotFound();

    expect($order->fresh()->only(['delivery_status', 'payment_status']))->toBe(['delivery_status' => 'pending', 'payment_status' => 'paid'])
        ->and((float) $customer->fresh()->balance)->toBe(0.0)
        ->and(stockOf($product))->toBe(10);
});

test('cancelling an order twice refunds and restocks once, whoever cancels it', function (string $channel) {
    $seller = createStoreUser('seller');
    $customer = createStoreUser('customer');
    $product = createStoreProduct(['user_id' => $seller->id, 'current_stock' => 10]);
    $order = walletOrderOf($seller, $customer, $product);
    $cancel = function () use ($channel, $seller, $order) {
        if ($channel === 'api') {
            // A token's user is cached on the guard for the rest of the test.
            app('auth')->forgetGuards();

            return test()->withToken(storeApiToken($seller))->postJson('/temp/api/v2/seller/orders/update-delivery-status', ['order_id' => $order->id, 'status' => 'cancelled']);
        }

        return test()->post('/temp/seller/orders/update_delivery_status', ['order_id' => $order->id, 'status' => 'cancelled']);
    };

    if ($channel === 'web') {
        actingAsStoreUser($seller);
    }

    $cancel()->assertOk();
    $cancel()->assertOk();

    expect((float) $customer->fresh()->balance)->toBe(20.0)
        ->and(stockOf($product))->toBe(12);
})->with(['the seller screen' => ['web'], 'the seller API' => ['api']]);

test('a seller\'s status change leaves another seller\'s lines of a shared order alone', function () {
    $mine = createStoreUser('seller');
    $theirs = createStoreUser('seller');
    $customer = createStoreUser('customer');
    $myProduct = createStoreProduct(['user_id' => $mine->id]);
    $theirProduct = createStoreProduct(['user_id' => $theirs->id]);
    $order = walletOrderOf($mine, $customer, $myProduct);
    $theirLine = OrderDetail::forceCreate([
        'order_id' => $order->id, 'seller_id' => $theirs->id, 'product_id' => $theirProduct->id, 'price' => 5, 'tax' => 0,
        'quantity' => 1, 'variation' => '', 'payment_status' => 'paid', 'delivery_status' => 'pending',
    ]);

    $this->withToken(storeApiToken($mine))->postJson('/temp/api/v2/seller/orders/update-delivery-status', ['order_id' => $order->id, 'status' => 'cancelled'])->assertOk();

    expect($theirLine->fresh()->delivery_status)->toBe('pending')
        ->and(stockOf($theirProduct))->toBe(50);
});

test('a seller edits their own shop, not the one named in the request', function () {
    $mine = createStoreUser('seller');
    $theirs = createStoreUser('seller');
    $myShop = DB::table('shops')->insertGetId(['user_id' => $mine->id, 'name' => 'Mine', 'slug' => 'mine-1']);
    $theirShop = DB::table('shops')->insertGetId(['user_id' => $theirs->id, 'name' => 'Theirs', 'slug' => 'theirs-1']);

    actingAsStoreUser($mine);
    $this->post(route('seller.shop.update'), ['shop_id' => $theirShop, 'name' => 'Hijacked', 'address' => 'x', 'phone' => '1'])->assertSessionHasErrorsIn('sellerShop', ['shop_id']);
    expect(DB::table('shops')->where('id', $myShop)->value('name'))->toBe('Mine');
    $this->post(route('seller.shop.update'), ['name' => 'Updated', 'address' => 'x', 'phone' => '1'])->assertSessionHasNoErrors();

    expect(DB::table('shops')->where('id', $theirShop)->value('name'))->toBe('Theirs')
        ->and(DB::table('shops')->where('id', $myShop)->value('name'))->toBe('Updated');
});

test('a ticket is replied to by the account that owns it, as that account', function () {
    $owner = createStoreUser('customer');
    $stranger = createStoreUser('customer');
    $ticket = Ticket::forceCreate(['code' => 777001, 'user_id' => $owner->id, 'subject' => 'Help', 'details' => 'x', 'status' => 'open']);

    actingAsStoreUser($stranger);
    $this->post(route('support_ticket.seller_store'), ['ticket_id' => $ticket->id, 'user_id' => $owner->id, 'reply' => 'forged'])->assertNotFound();
    expect(DB::table('ticket_replies')->count())->toBe(0);

    actingAsStoreUser($owner);
    $this->post(route('support_ticket.seller_store'), ['ticket_id' => $ticket->id, 'user_id' => $stranger->id, 'reply' => 'mine']);

    expect(DB::table('ticket_replies')->pluck('user_id')->all())->toEqual([$owner->id]);
});

test('a status outside the delivery statuses is rejected and the order is unchanged', function () {
    $seller = createStoreUser('seller');
    $customer = createStoreUser('customer');
    $product = createStoreProduct(['user_id' => $seller->id, 'current_stock' => 10]);
    $order = walletOrderOf($seller, $customer, $product);
    $noise = walletOrderOf($seller, $customer, $product);

    $this->withToken(storeApiToken($seller))
        ->postJson('/temp/api/v2/seller/orders/update-delivery-status', ['order_id' => $order->id, 'status' => 'shipped-to-mars'])
        ->assertUnprocessable();

    expect($order->fresh()->delivery_status)->toBe('pending')
        ->and($noise->fresh()->delivery_status)->toBe('pending')
        ->and((float) $customer->fresh()->balance)->toBe(0.0)
        ->and(stockOf($product))->toBe(10);
});

test('a customer\'s token cannot cancel or mark paid an order through the seller API', function (string $endpoint, string $status) {
    $seller = createStoreUser('seller');
    $customer = createStoreUser('customer');
    $product = createStoreProduct(['user_id' => $seller->id, 'current_stock' => 10]);
    $order = walletOrderOf($seller, $customer, $product);

    $this->withToken(storeApiToken($customer))
        ->postJson("/temp/api/v2/seller/orders/{$endpoint}", ['order_id' => $order->id, 'status' => $status])
        ->assertNotFound();

    expect($order->fresh()->only(['delivery_status', 'payment_status']))->toBe(['delivery_status' => 'pending', 'payment_status' => 'paid'])
        ->and((float) $customer->fresh()->balance)->toBe(0.0)
        ->and(stockOf($product))->toBe(10);
})->with([
    'delivery status' => ['update-delivery-status', 'cancelled'],
    'payment status' => ['update-payment-status', 'unpaid'],
]);
