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
     * Backdate the pallet past {@see Pallet::STALE_AFTER_DAYS} so it reads as stale.
     */
    public function stale(): static
    {
        return $this->afterCreating(
            fn (Pallet $pallet) => $pallet->forceFill([
                'created_at' => now()->subDays(Pallet::STALE_AFTER_DAYS)->subMinute(),
            ])->save()
        );
    }
}
