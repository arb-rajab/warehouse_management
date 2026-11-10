<?php

use App\Models\Cart;

/**
 * Resource routes registered for actions their controller never had
 * (`Route::resource()` without `only()`) answered 500 with a
 * BadMethodCallException, and `checkout.payment_info` (where a failed wallet
 * payment sends the customer back) pointed at a method that did not exist.
 */
beforeEach(function () {
    $this->withoutVite();
    seedStoreCurrency();
});

test('a failed wallet payment sends the customer back to a payment page that renders their cart', function () {
    $customer = createStoreUser('customer');
    $shopOwner = createStoreUser('admin');
    orderableCartLine($customer, $shopOwner, 2);
    actingAsStoreUser($customer);

    $this->get(route('checkout.payment_info'))
        ->assertOk()
        ->assertViewIs('shop.frontend.payment_select')
        ->assertViewHas('total', 5.0);
});

test('the payment page of a customer with an empty cart sends them home with a warning', function () {
    $customer = createStoreUser('customer');
    $other = createStoreUser('customer');
    orderableCartLine($other, createStoreUser('admin'), 1);
    actingAsStoreUser($customer);

    $this->get(route('checkout.payment_info'))->assertRedirect(route('home'));

    expect(Cart::query()->where('user_id', $customer->id)->count())->toBe(0)
        ->and(Cart::query()->where('user_id', $other->id)->count())->toBe(1);
});

test('the payment page is closed to a signed-out visitor', function () {
    $this->get(route('checkout.payment_info'))->assertRedirect();
});

dataset('customer actions their controller does not have', [
    'new purchase' => ['get', '/temp/purchase_history/create'],
    'store purchase' => ['post', '/temp/purchase_history'],
    'show purchase' => ['get', '/temp/purchase_history/1'],
    'edit purchase' => ['get', '/temp/purchase_history/1/edit'],
    'update purchase' => ['put', '/temp/purchase_history/1'],
    'delete purchase' => ['delete', '/temp/purchase_history/1'],
    'new product query' => ['get', '/temp/product-queries/create'],
    'edit product query' => ['get', '/temp/product-queries/1/edit'],
    'update product query' => ['put', '/temp/product-queries/1'],
    'delete product query' => ['delete', '/temp/product-queries/1'],
    'update shop' => ['put', '/temp/shops/1'],
]);

test('a customer action its controller does not have is refused as not found, not a server error', function (string $method, string $uri) {
    actingAsStoreUser(createStoreUser('customer'));

    // `product-queries/create` now reads as the show route of a query called "create", which refuses the caller.
    expect($this->call($method, $uri)->getStatusCode())->toBeIn([403, 404, 405]);
})->with('customer actions their controller does not have');

dataset('seller actions their controller does not have', [
    'address list' => ['get', '/temp/seller/addresses'],
    'new address' => ['get', '/temp/seller/addresses/create'],
    'show address' => ['get', '/temp/seller/addresses/1'],
    'store order' => ['post', '/temp/seller/orders'],
    'edit order' => ['get', '/temp/seller/orders/1/edit'],
    'update order' => ['put', '/temp/seller/orders/1'],
    'delete order' => ['delete', '/temp/seller/orders/1'],
    'new payment' => ['get', '/temp/seller/payments/create'],
    'store payment' => ['post', '/temp/seller/payments'],
    'show payment' => ['get', '/temp/seller/payments/1'],
    'edit payment' => ['get', '/temp/seller/payments/1/edit'],
    'update payment' => ['put', '/temp/seller/payments/1'],
    'delete payment' => ['delete', '/temp/seller/payments/1'],
]);

test('a seller action its controller does not have is refused as not found, not a server error', function (string $method, string $uri) {
    sellerAccount();

    expect($this->call($method, $uri)->getStatusCode())->toBeIn([404, 405]);
})->with('seller actions their controller does not have');

test('the seller order list, seller payment list and the actions the seller address modals post to still resolve', function () {
    foreach (['seller.orders.index', 'seller.orders.show', 'seller.payments.index', 'seller.addresses.store', 'seller.addresses.edit', 'seller.addresses.update', 'seller.addresses.destroy'] as $name) {
        expect(Route::has($name))->toBeTrue($name);
    }

    foreach (['purchase_history.index', 'purchase_history.destroy', 'product-queries.index', 'product-queries.show', 'product-queries.store', 'shops.index', 'shops.store', 'shops.destroy'] as $name) {
        expect(Route::has($name))->toBeTrue($name);
    }
});

test('the classified listing and product pages send visitors to the storefront home, not the site root, while the feature is off', function (string $uri) {
    storeSetting('classified_product', 0);

    $this->get($uri)->assertRedirect(route('home'));
})->with([
    'listing' => '/temp/customer-products',
    'listing search' => '/temp/customer-products?q=olive',
    'product' => '/temp/customer-product/olive-oil',
]);
