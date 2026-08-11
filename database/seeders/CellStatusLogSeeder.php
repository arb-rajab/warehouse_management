<?php

namespace Database\Seeders;

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class CellStatusLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cells = Cell::all();
        $products = Product::all();
        $users = User::all();

        if ($cells->isEmpty() || $products->isEmpty() || $users->isEmpty() || Pallet::query()->doesntExist()) {
            return;
        }

        foreach (range(1, 30) as $i) {
            $this->log($cells->random(), CellLogAction::Stored, CellState::Empty, CellState::Full, $products, $users);
        }

        foreach (range(1, 30) as $i) {
            $this->log($cells->random(), CellLogAction::Opened, CellState::Full, CellState::Opened, $products, $users);
        }

        foreach (range(1, 20) as $i) {
            $fromState = fake()->randomElement([CellState::Full, CellState::Opened]);
            $this->log($cells->random(), CellLogAction::Emptied, $fromState, CellState::Empty, $products, $users);
        }

        foreach (range(1, 20) as $i) {
            $this->logTransfer($cells, $products, $users);
        }
    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, User>  $users
     */
    private function log(Cell $cell, CellLogAction $action, CellState $from, CellState $to, Collection $products, Collection $users): void
    {
        CellStatusLog::factory()->create([
            'cell_id' => $cell->id,
            'action' => $action,
            'from_state' => $from,
            'to_state' => $to,
            'product_id' => $products->random()->id,
            'user_id' => $users->random()->id,
        ]);
    }

    /**
     * @param  Collection<int, Cell>  $cells
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, User>  $users
     */
    private function logTransfer(Collection $cells, Collection $products, Collection $users): void
    {
        $sourceCell = $cells->random();
        $destinationCell = $cells->where('id', '!=', $sourceCell->id)->random();
        $carryOverState = fake()->randomElement([CellState::Full, CellState::Opened]);
        $product = $products->random();
        $user = $users->random();

        CellStatusLog::factory()->create([
            'cell_id' => $sourceCell->id,
            'related_cell_id' => $destinationCell->id,
            'action' => CellLogAction::TransferredOut,
            'from_state' => $carryOverState,
            'to_state' => CellState::Empty,
            'product_id' => $product->id,
            'user_id' => $user->id,
        ]);

        CellStatusLog::factory()->create([
            'cell_id' => $destinationCell->id,
            'related_cell_id' => $sourceCell->id,
            'action' => CellLogAction::TransferredIn,
            'from_state' => CellState::Empty,
            'to_state' => $carryOverState,
            'product_id' => $product->id,
            'user_id' => $user->id,
        ]);
    }
}
