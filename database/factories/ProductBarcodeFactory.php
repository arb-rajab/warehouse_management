<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductBarcode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductBarcode>
 */
class ProductBarcodeFactory extends Factory
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
            'barcode' => fake()->unique()->ean13(),
        ];
    }
}
