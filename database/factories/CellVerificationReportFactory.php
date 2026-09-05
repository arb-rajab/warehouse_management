<?php

namespace Database\Factories;

use App\Enums\CellState;
use App\Models\Cell;
use App\Models\CellVerificationReport;
use App\Models\CellVerificationRound;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CellVerificationReport>
 */
class CellVerificationReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cell_verification_round_id' => CellVerificationRound::factory(),
            'cell_id' => Cell::factory(),
            'user_id' => User::factory(),
            'is_correct' => true,
            'expected_cell_state' => CellState::Empty,
            'expected_product_id' => null,
            'expected_boxes_count' => null,
            'expected_expiration_date' => null,
            'reported_cell_state' => null,
            'reported_product_id' => null,
            'reported_boxes_count' => null,
            'reported_expiration_date' => null,
            'note' => null,
        ];
    }

    public function incorrect(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_correct' => false,
            'expected_cell_state' => CellState::Full,
            'expected_product_id' => Product::factory(),
            'expected_boxes_count' => 10,
            'expected_expiration_date' => now()->addMonth(),
            'reported_cell_state' => CellState::Empty,
            'reported_product_id' => null,
            'reported_boxes_count' => null,
            'reported_expiration_date' => null,
        ]);
    }
}
