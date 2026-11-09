<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptPallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceiptPallet>
 */
class ReceiptPalletFactory extends Factory
{
    /**
     * Define the model's default state: one pallet as written on the bill of
     * a draft receipt, not yet turned into a real pallet.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receipt_id' => Receipt::factory(),
            'product_id' => Product::factory(),
            'boxes' => fake()->numberBetween(1, 60),
            // A bare date, not a DateTime: see PalletFactory.
            'expiration_date' => fake()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'pallet_id' => null,
            'position' => fake()->numberBetween(0, 50),
        ];
    }
}
