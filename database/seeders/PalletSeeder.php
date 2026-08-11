<?php

namespace Database\Seeders;

use App\Enums\CellState;
use App\Models\Cell;
use App\Models\Pallet;
use App\Models\Product;
use Illuminate\Database\Seeder;

class PalletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = Product::all();
        $availableCells = Cell::all();

        if ($products->isEmpty() || $availableCells->isEmpty()) {
            return;
        }

        $palletCount = min(150, $availableCells->count());

        foreach (range(1, $palletCount) as $i) {
            $cell = $availableCells->random();

            $state = match ($i % 3) {
                0 => 'opened',
                1 => 'full',
                default => 'empty',
            };

            if ($state === 'empty') {
                $cell->update(['state' => CellState::Empty]);
            } else {
                Pallet::factory()
                    ->when($state === 'opened', fn ($factory) => $factory->opened())
                    ->create([
                        'product_id' => $products->random()->id,
                        'cell_id' => $cell->id,
                    ]);
            }

            $availableCells = $availableCells->where('id', '!=', $cell->id);
        }
    }
}
