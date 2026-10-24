<?php

use App\Models\Product;
use App\Models\ProductImageReview;
use App\Models\User;
use Illuminate\Database\QueryException;

test('deleting a product removes only its image reviews', function () {
    $product = Product::factory()->create();
    $review = ProductImageReview::factory()->create(['product_id' => $product->id]);
    $noise = ProductImageReview::factory()->create();
    $product->delete();
    expect($review->fresh())->toBeNull()->and($noise->fresh())->not->toBeNull();
});

test('a reviewer cannot be hard deleted while their image review history exists', function () {
    $user = User::factory()->create();
    ProductImageReview::factory()->create(['user_id' => $user->id]);
    expect(fn () => $user->forceDelete())->toThrow(QueryException::class);
    expect(User::withTrashed()->find($user->id))->not->toBeNull();
});
