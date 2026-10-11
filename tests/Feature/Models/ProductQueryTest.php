<?php

use App\Models\ProductQuery;

test('a store product query resolves only its product and customer', function () {
    $customer = createStoreUser();
    $noiseUser = createStoreUser();
    $product = createStoreProduct();
    $noiseProduct = createStoreProduct();
    $query = ProductQuery::forceCreate(['customer_id' => $customer->id, 'seller_id' => $noiseUser->id, 'product_id' => $product->id, 'question' => 'mine']);
    ProductQuery::forceCreate(['customer_id' => $noiseUser->id, 'seller_id' => $customer->id, 'product_id' => $noiseProduct->id, 'question' => 'noise']);

    expect($query->product->id)->toBe($product->id)
        ->and($query->user->id)->toBe($customer->id);
});
