<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Exceptions;

/**
 * The store puts encrypted ids in its URLs and decrypt()s them in many
 * controllers. A mistyped or tampered id is a missing record: a 404, not the
 * 500 an uncaught DecryptException used to give.
 */
beforeEach(function () {
    $this->withoutVite();
    seedStoreCurrency();
});

dataset('seller pages with an encrypted id', [
    'order' => '/temp/seller/orders/not-an-encrypted-id',
    'conversation' => '/temp/seller/conversations/show/not-an-encrypted-id',
    'support ticket' => '/temp/seller/support_ticket/show/not-an-encrypted-id',
    'coupon edit' => '/temp/seller/coupon/not-an-encrypted-id/edit',
]);

test('a seller page given a malformed encrypted id is not found', function (string $uri) {
    sellerAccount();

    $this->get($uri)->assertNotFound();
})->with('seller pages with an encrypted id');

test('a customer page given a malformed encrypted id is not found', function () {
    actingAsStoreUser(createStoreUser('customer'));

    $this->get('/temp/purchase_history/details/not-an-encrypted-id')->assertNotFound();
});

test('an API call given a malformed encrypted id gets a JSON 404', function () {
    $this->withToken(storeApiToken(createStoreUser('seller')));

    $this->getJson('/api/v1/store/seller/conversations/show/not-an-encrypted-id')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});

test('a malformed encrypted id is still reported, so a rotated key stays visible in the logs', function () {
    Exceptions::fake();
    sellerAccount();

    $this->get('/temp/seller/orders/not-an-encrypted-id')->assertNotFound();

    Exceptions::assertReported(DecryptException::class);
});

test('a valid encrypted id still opens its record, and one that decrypts to a missing record is also not found', function () {
    $seller = sellerAccount();
    $coupon = sellerCoupon($seller);

    $this->get(route('seller.coupon.edit', encrypt($coupon->id)))->assertOk();
    $this->get(route('seller.coupon.edit', encrypt($coupon->id + 999)))->assertNotFound();
});
