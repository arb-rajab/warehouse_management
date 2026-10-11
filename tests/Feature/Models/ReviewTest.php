<?php

use App\Models\Review;

test('a review belongs to its product and store customer without crossing sibling records', function () {
    $customer = createStoreUser('customer');
    $product = createStoreProduct();
    $otherCustomer = createStoreUser('customer');
    $otherProduct = createStoreProduct();
    $review = Review::forceCreate(['product_id' => $product->id, 'user_id' => $customer->id, 'rating' => 4, 'status' => 1, 'comment' => 'Good']);
    Review::forceCreate(['product_id' => $otherProduct->id, 'user_id' => $otherCustomer->id, 'rating' => 5, 'status' => 1, 'comment' => 'Other']);
    expect($review->product->id)->toBe($product->id)->not->toBe($otherProduct->id)
        ->and($review->user->id)->toBe($customer->id)->not->toBe($otherCustomer->id);
});
