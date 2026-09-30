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
