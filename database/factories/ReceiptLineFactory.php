<?php

namespace Database\Factories;

use App\Enums\ReceiptLineStatus;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceiptLine>
 */
class ReceiptLineFactory extends Factory
{
    /**
     * Define the model's default state: a pending line of the bill.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receipt_id' => Receipt::factory(),
            'product_id' => Product::factory(),
            'is_extra' => false,
            'expected_pallets_count' => fake()->numberBetween(1, 5),
            'expected_boxes' => fake()->numberBetween(1, 60),
            // A bare date, not a DateTime: see PalletFactory.
            'expected_expiration_date' => fake()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'status' => ReceiptLineStatus::Pending,
            'note' => null,
        ];
    }

    /**
     * A pallet group that is not on the bill: nothing is expected of it.
     */
    public function extra(): static
    {
        return $this->state(fn (): array => [
            'is_extra' => true,
            'expected_pallets_count' => null,
            'expected_boxes' => null,
            'expected_expiration_date' => null,
            'status' => ReceiptLineStatus::Extra,
        ]);
    }

    public function matched(): static
    {
        return $this->state(fn (): array => ['status' => ReceiptLineStatus::Matched]);
    }

    public function mismatched(): static
    {
        return $this->state(fn (): array => ['status' => ReceiptLineStatus::Mismatched]);
    }
}
