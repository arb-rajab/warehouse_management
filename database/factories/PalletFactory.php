<?php

namespace Database\Factories;

use App\Enums\CellState;
use App\Models\Cell;
use App\Models\Pallet;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pallet>
 */
class PalletFactory extends Factory
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
            'cell_id' => Cell::factory(),
            'expiration_date' => fake()->dateTimeBetween('now', '+1 year'),
            'remaining_boxes' => fn (array $attributes): int => Product::query()->findOrFail((int) $attributes['product_id'])->boxes_count,
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return parent::configure()->afterCreating(
            fn (Pallet $pallet) => $pallet->cell->update(['state' => CellState::Full])
        );
    }

    public function opened(): static
    {
        return $this->afterCreating(
            fn (Pallet $pallet) => $pallet->cell->update(['state' => CellState::Opened])
        );
    }

    /**
     * Backdate the pallet by a generous 30 days, so it reads as stale under any
     * reasonable caller-chosen day count passed to {@see Pallet::isStaleAfter()}.
     */
    public function stale(): static
    {
        return $this->afterCreating(
            fn (Pallet $pallet) => $pallet->forceFill([
                'created_at' => now()->subDays(30),
            ])->save()
        );
    }
}
