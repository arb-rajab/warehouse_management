<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductSetting>
 */
class ProductSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'boxes_count' => fake()->numberBetween(1, 50),
        ];
    }
}
