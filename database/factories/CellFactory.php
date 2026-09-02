<?php

namespace Database\Factories;

use App\Enums\CellState;
use App\Models\Cell;
use App\Models\Row;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cell>
 */
class CellFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Row::withoutEvents() suppresses RowObserver here, since this factory is
        // responsible for creating this row's only cell itself — letting the observer
        // also run would generate a full grid that this cell's own coordinates could collide with.
        return [
            'row_id' => Row::withoutEvents(fn () => Row::factory()->create())->id,
            'cell_number' => fake()->unique()->numberBetween(1, 999),
            'flat_number' => fake()->numberBetween(1, 10),
            'state' => CellState::Empty,
            'is_active' => true,
        ];
    }

    /**
     * Mark the cell as inactive (corrupted / out of service).
     *
     * @return static
     */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
