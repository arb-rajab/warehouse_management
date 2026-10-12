<?php

use App\Policies\ProductPolicy;

test('a seller may change their own product and not another seller\'s', function () {
    $seller = createStoreUser('seller');
    $otherSeller = createStoreUser('seller');
    $product = createStoreProduct(['user_id' => $seller->id]);

    $policy = new ProductPolicy;

    expect($policy->updateAsSeller($seller, $product))->toBeTrue()
        ->and($policy->updateAsSeller($otherSeller, $product))->toBeFalse();
});

test('no seller may change a product the store itself sells', function () {
    $storeAdmin = createStoreUser('admin');
    $product = createStoreProduct(['user_id' => $storeAdmin->id]);

    expect((new ProductPolicy)->updateAsSeller(createStoreUser('seller'), $product))->toBeFalse();
});

test('a customer with a paid order for a digital product may download it', function () {
    $product = createStoreProduct(['user_id' => createStoreUser('seller')->id, 'digital' => 1]);
    $buyer = createStoreUser('customer');
    orderStoreProduct($buyer, $product);

    expect((new ProductPolicy)->download($buyer, $product))->toBeTrue();
});

test('the seller who uploaded a digital product and a store admin may download it', function () {
    $seller = createStoreUser('seller');
    $product = createStoreProduct(['user_id' => $seller->id, 'digital' => 1]);

    $policy = new ProductPolicy;

    expect($policy->download($seller, $product))->toBeTrue()
        ->and($policy->download(createStoreUser('admin'), $product))->toBeTrue();
});

test('nobody else may download a digital product: not an unpaid buyer, another product\'s buyer, another customer or another seller', function () {
    $product = createStoreProduct(['user_id' => createStoreUser('seller')->id, 'digital' => 1]);
    $otherProduct = createStoreProduct(['user_id' => createStoreUser('seller')->id, 'digital' => 1]);
    $unpaidBuyer = createStoreUser('customer');
    $otherProductBuyer = createStoreUser('customer');
    orderStoreProduct($unpaidBuyer, $product, 'unpaid');
    orderStoreProduct($otherProductBuyer, $otherProduct);
    // Someone else's paid order for this product must not count for anyone else.
    orderStoreProduct(createStoreUser('customer'), $product);

    $policy = new ProductPolicy;

    expect($policy->download($unpaidBuyer, $product))->toBeFalse()
        ->and($policy->download($otherProductBuyer, $product))->toBeFalse()
        ->and($policy->download(createStoreUser('customer'), $product))->toBeFalse()
        ->and($policy->download(createStoreUser('seller'), $product))->toBeFalse();
});
