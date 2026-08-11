<?php

namespace Database\Factories;

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CellStatusLog>
 */
class CellStatusLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cell_id' => Cell::factory(),
            'related_cell_id' => null,
            'action' => CellLogAction::Stored,
            'from_state' => CellState::Empty,
            'to_state' => CellState::Full,
            'product_id' => Product::factory(),
            'pallet_id' => null,
            'user_id' => User::factory(),
            'note' => null,
        ];
    }
}
