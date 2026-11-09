<?php

namespace Database\Factories;

use App\Models\ReceiptLine;
use App\Models\ReceiptLinePallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceiptLinePallet>
 */
class ReceiptLinePalletFactory extends Factory
{
    /**
     * Define the model's default state: a draft entry with no real pallet yet.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receipt_line_id' => ReceiptLine::factory(),
            'boxes' => fake()->numberBetween(1, 60),
            // A bare date, not a DateTime: see PalletFactory.
            'expiration_date' => fake()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'pallet_id' => null,
        ];
    }
}
