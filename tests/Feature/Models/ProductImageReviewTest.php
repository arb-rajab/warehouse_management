<?php

use App\Models\Product;
use App\Models\ProductImageReview;
use App\Models\User;

test('a product image review resolves only its product and retains a deleted reviewer', function () {
    $product = Product::factory()->create();
    $user = User::factory()->create();
    $review = ProductImageReview::factory()->create(['product_id' => $product->id, 'user_id' => $user->id]);
    $noise = ProductImageReview::factory()->create();
    expect($review->product->id)->toBe($product->id)->not->toBe($noise->product_id)
        ->and($review->user->id)->toBe($user->id)->not->toBe($noise->user_id);
    $user->delete();
    expect($review->fresh()->user->id)->toBe($user->id);
});

test('current status uses only the latest review of the current product image', function () {
    $product = Product::factory()->imageUrl(null)->create();
    $noise = Product::factory()->imageUrl(null)->create();
    ProductImageReview::factory()->create(['product_id' => $noise->id, 'status' => 'matches']);
    ProductImageReview::factory()->create(['product_id' => $product->id, 'status' => 'doesnt_match']);
    ProductImageReview::factory()->create(['product_id' => $product->id, 'status' => 'image_missing']);
    $read = fn () => Product::query()->select('id')->addSelect(['status' => ProductImageReview::currentStatusQuery()])->findOrFail($product->id)->getAttribute('status');
    expect($read())->toBe('image_missing');
    $product->forceFill(['warehouse_image_path' => 'product-reference-images/new.jpg'])->save();
    expect($read())->toBeNull();
});
