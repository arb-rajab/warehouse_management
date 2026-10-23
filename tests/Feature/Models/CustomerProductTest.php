<?php

use App\Models\CustomerProduct;
use App\Models\Upload;

test('a classified thumbnail resolves only its referenced upload and permits missing references', function () {
    $image = Upload::factory()->create();
    Upload::factory()->create();
    $product = CustomerProduct::forceCreate(['thumbnail_img' => (string) $image->id]);
    CustomerProduct::forceCreate(['thumbnail_img' => null]);
    expect($product->thumbnailUpload?->id)->toBe($image->id);
    $product->forceFill(['thumbnail_img' => '999999'])->save();
    expect($product->fresh()->thumbnailUpload)->toBeNull();
    $product->forceFill(['thumbnail_img' => null])->save();
    expect($product->fresh()->thumbnailUpload)->toBeNull();
});
