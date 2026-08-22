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
use Illuminate\Support\Carbon;

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

        $this->seedSuspiciousActivity($cells, $products);
    }

    /**
     * Seed a handful of logs deliberately shaped to trip each of the three
     * rule-based auto-flagging rules (config/cell_status_log_flags.php),
     * through the real CellStatusLog::create() -> CellStatusLogObserver path
     * (no flag row is ever created directly) — so the Cell Log and per-user
     * action pages have something to show without waiting for real
     * suspicious activity to occur. Each scenario gets its own clearly-named
     * mobile user so it's easy to find in the admin UI.
     *
     * @param  Collection<int, Cell>  $cells
     * @param  Collection<int, Product>  $products
     */
    private function seedSuspiciousActivity(Collection $cells, Collection $products): void
    {
        try {
            $this->seedRapidActions(
                User::factory()->mobileUser()->create(['name' => 'Rapid Rita']),
                $cells,
                $products,
            );

            $this->seedOffHoursActivity(
                User::factory()->mobileUser()->create(['name' => 'Nightowl Nick']),
                $cells,
                $products,
            );

            $this->seedQuickFlip(
                User::factory()->mobileUser()->create(['name' => 'Flippy Fred']),
                $cells,
                $products,
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * More actions within the configured rolling window than
     * `rapid_actions.threshold` allows, all by the same user.
     *
     * @param  Collection<int, Cell>  $cells
     * @param  Collection<int, Product>  $products
     */
    private function seedRapidActions(User $user, Collection $cells, Collection $products): void
    {
        $count = (int) config('cell_status_log_flags.rapid_actions.threshold') + 3;

        Carbon::setTestNow(now()->subMinutes(3));

        foreach (range(1, $count) as $i) {
            CellStatusLog::factory()->create([
                'cell_id' => $cells->random()->id,
                'action' => CellLogAction::Opened,
                'from_state' => CellState::Full,
                'to_state' => CellState::Opened,
                'product_id' => $products->random()->id,
                'user_id' => $user->id,
                'note' => "Demo: rapid action {$i} of {$count}.",
            ]);

            Carbon::setTestNow(now()->addSeconds(15));
        }
    }

    /**
     * A few actions timestamped well outside the configured working-hours window.
     *
     * @param  Collection<int, Cell>  $cells
     * @param  Collection<int, Product>  $products
     */
    private function seedOffHoursActivity(User $user, Collection $cells, Collection $products): void
    {
        Carbon::setTestNow(today()->setTime(2, 30));

        foreach (range(1, 4) as $i) {
            CellStatusLog::factory()->create([
                'cell_id' => $cells->random()->id,
                'action' => CellLogAction::Stored,
                'from_state' => CellState::Empty,
                'to_state' => CellState::Full,
                'product_id' => $products->random()->id,
                'user_id' => $user->id,
                'note' => 'Demo: off-hours activity.',
            ]);

            Carbon::setTestNow(now()->addMinutes(20));
        }
    }

    /**
     * A Stored action immediately followed by an Emptied action on the same
     * cell, well within the configured quick-flip window.
     *
     * @param  Collection<int, Cell>  $cells
     * @param  Collection<int, Product>  $products
     */
    private function seedQuickFlip(User $user, Collection $cells, Collection $products): void
    {
        $cell = $cells->random();
        $product = $products->random();

        Carbon::setTestNow(today()->setTime(14, 0));

        CellStatusLog::factory()->create([
            'cell_id' => $cell->id,
            'action' => CellLogAction::Stored,
            'from_state' => CellState::Empty,
            'to_state' => CellState::Full,
            'product_id' => $product->id,
            'user_id' => $user->id,
            'note' => 'Demo: quick store, about to be emptied right after.',
        ]);

        Carbon::setTestNow(now()->addSeconds(45));

        CellStatusLog::factory()->create([
            'cell_id' => $cell->id,
            'action' => CellLogAction::Emptied,
            'from_state' => CellState::Full,
            'to_state' => CellState::Empty,
            'product_id' => $product->id,
            'user_id' => $user->id,
            'note' => 'Demo: quick store/empty flip.',
        ]);
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
