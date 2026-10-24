<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImageReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductImageReview> */
class ProductImageReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
            'status' => 'image_missing',
            'image_source' => '',
            'image_url' => null,
            'note' => null,
        ];
    }
}
