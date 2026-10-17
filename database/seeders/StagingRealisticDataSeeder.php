<?php

namespace Database\Seeders;

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\CellVerificationReport;
use App\Models\CellVerificationRound;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\ProductSetting;
use App\Models\Row;
use App\Models\User;
use App\Services\DashboardStatsCache;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * A standalone, run-it-by-hand seeder that makes an already-seeded staging
 * database look lived-in: it is deliberately NOT registered in DatabaseSeeder
 * and does not depend on, call or re-run any other seeder, so the data that is
 * already on staging is never re-seeded or collided with.
 *
 *   php artisan db:seed --class=StagingRealisticDataSeeder
 *
 * What it does, in one transaction:
 *  1. Rows A through S exist, and every row — including rows that were already
 *     there under any other letter — is brought to exactly COLUMNS x LEVELS
 *     cells. This bypasses RowObserver::updated() (which wipes and regenerates
 *     cells, impossible once a row has pallets or history) and never discards
 *     history: a surplus cell that holds a pallet or is referenced by a log or
 *     verification report keeps its id and is moved into a free slot of the
 *     grid; only unused surplus cells are deleted.
 *  2. Unless a previous run already did so, it seeds pallets, cell status logs
 *     (including rule-flagged ones), deactivated cells, low-stock thresholds
 *     and verification rounds with reports, spread so that every dashboard
 *     card and page filter has matching and non-matching rows. The `products`
 *     table itself is never written (its synced columns belong to
 *     `products:sync`); only `wms_product_settings.minimum_pallets` is, see
 *     seedLowStockThresholds().
 *
 * Re-running is safe: step 1 is a no-op once every row is on the grid, and
 * step 2 is skipped once its marker user (see MARKER_EMAIL) exists. Everything
 * step 2 writes is attributable to the seeded users, whose emails all end in
 * "staging-seed.test".
 */
class StagingRealisticDataSeeder extends Seeder
{
    public const int COLUMNS = 44;

    public const int LEVELS = 3;

    private const string FIRST_ROW_LETTER = 'A';

    private const string LAST_ROW_LETTER = 'S';

    /**
     * Created first by step 2's user setup; its presence means step 2 already ran.
     */
    private const string MARKER_EMAIL = 'staging-worker-1@staging-seed.test';

    private const int COMPLETED_ROUNDS = 14;

    private const int UNFINISHED_ROUNDS = 3;

    private Randomizer $random;

    /** @var list<Product> */
    private array $products = [];

    /** @var list<User> */
    private array $workers = [];

    private User $rapidWorker;

    private User $nightWorker;

    private User $flipWorker;

    private User $admin;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Refusing to run database seeders in production.');
        }

        // Fixed seed: every run on a given database makes the same choices.
        $this->random = new Randomizer(new Mt19937(20261001));

        DB::transaction(function (): void {
            $this->normalizeRows();

            if (! User::query()->where('email', self::MARKER_EMAIL)->exists()) {
                $this->seedActivity();
            }
        });

        DashboardStatsCache::flush();
    }

    private function normalizeRows(): void
    {
        foreach (range(self::FIRST_ROW_LETTER, self::LAST_ROW_LETTER) as $letter) {
            Row::query()->firstOrCreate(
                ['letter' => $letter],
                ['cells_count' => self::COLUMNS, 'flats_count' => self::LEVELS],
            );
        }

        $expectedCellCount = self::COLUMNS * self::LEVELS;

        foreach (Row::query()->select(Row::SELECT_COLUMNS)->orderBy('letter')->get() as $row) {
            $cellsInGrid = $row->cells()
                ->where('cell_number', '<=', self::COLUMNS)
                ->where('flat_number', '<=', self::LEVELS)
                ->count();

            if (
                (int) $row->cells_count === self::COLUMNS
                && (int) $row->flats_count === self::LEVELS
                && $cellsInGrid === $expectedCellCount
                && $row->cells()->count() === $expectedCellCount
            ) {
                continue;
            }

            $this->resizeRow($row);
        }
    }

    /**
     * Bring one existing row to the COLUMNS x LEVELS grid without regenerating
     * its cells: keep every cell already inside the grid, delete unused cells
     * outside it, move used ones (a pallet, a log or a verification report
     * points at them) into free grid slots, and insert whatever slots are
     * still missing.
     */
    private function resizeRow(Row $row): void
    {
        $cells = $row->cells()->select(['id', 'cell_number', 'flat_number'])->get();

        $isInGrid = fn (Cell $cell): bool => $cell->cell_number <= self::COLUMNS && $cell->flat_number <= self::LEVELS;

        $takenSlots = $cells->filter($isInGrid)
            ->mapWithKeys(fn (Cell $cell): array => ["{$cell->cell_number}-{$cell->flat_number}" => true]);

        $freeSlots = [];

        for ($column = 1; $column <= self::COLUMNS; $column++) {
            for ($level = 1; $level <= self::LEVELS; $level++) {
                if (! $takenSlots->has("{$column}-{$level}")) {
                    $freeSlots[] = [$column, $level];
                }
            }
        }

        $surplusCells = $cells->reject($isInGrid);
        $inUseIds = $this->cellIdsInUse($surplusCells->modelKeys());
        $deletableIds = [];

        foreach ($surplusCells as $cell) {
            if (! isset($inUseIds[$cell->id])) {
                $deletableIds[] = $cell->id;

                continue;
            }

            $slot = array_shift($freeSlots)
                ?? throw new RuntimeException("Row {$row->letter} has more occupied or historic cells outside the grid than free slots inside it.");

            Cell::query()->whereKey($cell->id)->update([
                'cell_number' => $slot[0],
                'flat_number' => $slot[1],
                'updated_at' => now(),
            ]);
        }

        Cell::query()->whereKey($deletableIds)->delete();

        $now = now();

        Cell::insert(array_map(fn (array $slot): array => [
            'row_id' => $row->id,
            'cell_number' => $slot[0],
            'flat_number' => $slot[1],
            'state' => CellState::Empty->value,
            'created_at' => $now,
            'updated_at' => $now,
        ], $freeSlots));

        // Skipping RowObserver::updated() on purpose — it would delete every cell.
        Row::withoutEvents(fn (): bool => $row->update([
            'cells_count' => self::COLUMNS,
            'flats_count' => self::LEVELS,
        ]));
    }

    /**
     * @param  array<int, int|string>  $cellIds
     * @return array<int|string, true>
     */
    private function cellIdsInUse(array $cellIds): array
    {
        if ($cellIds === []) {
            return [];
        }

        return array_fill_keys([
            ...Pallet::query()->whereIn('cell_id', $cellIds)->pluck('cell_id')->all(),
            ...CellStatusLog::query()->whereIn('cell_id', $cellIds)->pluck('cell_id')->all(),
            ...CellStatusLog::query()->whereIn('related_cell_id', $cellIds)->pluck('related_cell_id')->all(),
            ...CellVerificationReport::query()->whereIn('cell_id', $cellIds)->pluck('cell_id')->all(),
        ], true);
    }

    /**
     * Remove and return the first `$count` cells of the list.
     *
     * @param  list<Cell>  $cells
     * @return list<Cell>
     */
    private function takeCells(array &$cells, int $count): array
    {
        $taken = array_slice($cells, 0, $count);
        $cells = array_slice($cells, $count);

        return $taken;
    }

    private function seedActivity(): void
    {
        $this->products = $this->loadProducts();

        if ($this->products === []) {
            return;
        }

        $this->createUsers();

        $cells = Cell::query()
            ->select(['id', 'row_id', 'cell_number', 'flat_number'])
            ->where('state', CellState::Empty->value)
            ->where('is_active', true)
            ->doesntHave('pallet')
            ->get()
            ->all();

        /** @var list<Cell> $cells */
        $cells = $this->random->shuffleArray($cells);

        $palletCells = $this->takeCells($cells, intdiv(count($cells) * 45, 100));
        $scenarioCells = $this->takeCells($palletCells, 19);
        $transferOriginCells = $this->takeCells($cells, intdiv(count($palletCells), 12));
        $emptiedCells = $this->takeCells($cells, intdiv(count($cells), 10));
        $quickFlipCells = $this->takeCells($emptiedCells, 3);
        $deactivatedEmptyCells = $this->takeCells($cells, 12);
        $reactivatedCells = $this->takeCells($cells, 5);

        $this->seedFlaggedActivity($scenarioCells, $quickFlipCells);

        $placed = [];

        foreach ($palletCells as $index => $cell) {
            $placed[] = $this->seedPallet($cell, $transferOriginCells[$index] ?? null, $this->pastWorkingTime($this->palletAgeInDays()));
        }

        foreach ($emptiedCells as $cell) {
            $this->seedEmptiedPallet($cell, $this->pastWorkingTime($this->random->getInt(2, 90)));
        }

        $this->seedCellDeactivations(array_slice($placed, 30, 10), $deactivatedEmptyCells, $reactivatedCells);
        $this->seedProductCorrections(array_slice($placed, 40, 3));
        $this->seedLowStockThresholds();
        $this->seedVerificationRounds();
    }

    /**
     * Products are only read: `name`, `ar_name` and `published` are owned by
     * `products:sync`, so staging's own unpublished products (if any) are what
     * the "inactive" filters get to show. They are placed first so the skewed
     * product choice in pickProduct() gives them pallets.
     *
     * @return list<Product>
     */
    private function loadProducts(): array
    {
        $products = Product::query()
            ->select(['id', 'published'])
            ->with('setting:product_id,boxes_count,minimum_pallets')
            ->orderBy('id')
            ->get();

        $unpublished = $products->where('published', false)->take(3);

        return [...$unpublished->all(), ...$products->diff($unpublished)->all()];
    }

    private function createUsers(): void
    {
        foreach (['Khalid Mansour', 'Sara Nasser', 'Youssef Karim', 'Layla Farouk', 'Tariq Aziz'] as $index => $name) {
            $this->workers[] = $this->createWorker($name, 'staging-worker-'.($index + 1).'@staging-seed.test');
        }

        $this->rapidWorker = $this->createWorker('Staging Rapid Worker', 'staging-rapid@staging-seed.test');
        $this->nightWorker = $this->createWorker('Staging Night Worker', 'staging-night@staging-seed.test');
        $this->flipWorker = $this->createWorker('Staging Flip Worker', 'staging-flip@staging-seed.test');

        $this->admin = User::query()->whereHas('roles', fn ($roles) => $roles->where('name', 'admin'))->first()
            ?? $this->workers[0];
    }

    /**
     * Mobile-app style users (no admin role) with a random, unknown password.
     */
    private function createWorker(string $name, string $email): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(Str::random(40)),
        ]);
    }

    /**
     * The three scenarios CellStatusLogObserver auto-flags, written through
     * the observer (events on) so the flags are real: a burst of stores past
     * the rapid-actions threshold, stores at 02:30, and a store emptied 45
     * seconds later. A few of the flags are then acknowledged by the admin so
     * both flagged states exist.
     *
     * @param  list<Cell>  $scenarioCells
     * @param  list<Cell>  $quickFlipCells
     */
    private function seedFlaggedActivity(array $scenarioCells, array $quickFlipCells): void
    {
        $burstStart = now()->subDay()->setTime(11, 0);
        $nightStart = now()->subDays(3)->setTime(2, 30);

        foreach (array_slice($scenarioCells, 0, 14) as $index => $cell) {
            $this->seedPallet($cell, null, $burstStart->copy()->addSeconds(12 * $index), $this->rapidWorker);
        }

        foreach (array_slice($scenarioCells, 14, 5) as $index => $cell) {
            $this->seedPallet($cell, null, $nightStart->copy()->addMinutes(20 * $index), $this->nightWorker);
        }

        foreach ($quickFlipCells as $index => $cell) {
            $this->seedEmptiedPallet(
                $cell,
                $this->pastWorkingTime(2 + $index),
                $this->flipWorker,
                emptiedAfterSeconds: 45,
            );
        }

        DB::table('cell_status_log_flags')
            ->whereIn('cell_status_log_id', CellStatusLog::query()->whereIn('user_id', [$this->flipWorker->id, $this->nightWorker->id])->select('id'))
            ->orderBy('id')
            ->limit(5)
            ->update(['acknowledged_at' => now()->subDay(), 'acknowledged_by' => $this->admin->id]);
    }

    /**
     * Store a pallet in `$cell` — or, when `$origin` is given, store it there
     * first and transfer it to `$cell` — then replay a plausible history of
     * openings and box removals, writing one CellStatusLog per step.
     *
     * With `$scenarioUser` only the single `stored` log is written, through
     * CellStatusLogObserver (events on) so its flagging rules can trip;
     * every other log is written without events, which is safe because the
     * seeded timestamps stay inside working hours and far apart.
     *
     * @return array{cell: Cell, pallet: Pallet, product: Product, last_at: CarbonInterface}
     */
    private function seedPallet(Cell $cell, ?Cell $origin, CarbonInterface $storedAt, ?User $scenarioUser = null): array
    {
        $product = $this->pickProduct();
        $fullBoxes = $product->boxes_count;
        $storeCell = $origin ?? $cell;
        $state = $storeCell->flat_number === 1 ? CellState::Opened : CellState::Full;
        $opened = $scenarioUser === null && ($state === CellState::Opened || $this->roll(20));
        $boxesRemoved = $opened && $fullBoxes > 1 && $this->roll(60) ? $this->random->getInt(1, $fullBoxes - 1) : 0;
        $remainingBoxes = $fullBoxes - $boxesRemoved;

        $pallet = $this->savePallet($cell, $product, $remainingBoxes, $storedAt);

        $log = fn (Cell $logCell, CellLogAction $action, CellState $from, CellState $to, CarbonInterface $at, int $boxes, ?Cell $related = null): CellStatusLog => $this->saveLog(
            $logCell, $action, $from, $to, $product, $pallet, $boxes, $scenarioUser ?? $this->regularWorker(), $at, $related, observed: $scenarioUser !== null,
        );

        $at = $storedAt;
        $log($storeCell, CellLogAction::Stored, CellState::Empty, $state, $at, $fullBoxes);

        if ($opened && $state === CellState::Full) {
            $at = $this->timeAfter($at);
            $log($storeCell, CellLogAction::Opened, CellState::Full, CellState::Opened, $at, $fullBoxes);
            $state = CellState::Opened;
        }

        if ($origin !== null) {
            $at = $this->timeAfter($at);
            $log($origin, CellLogAction::TransferredOut, $state, CellState::Empty, $at, $fullBoxes, $cell);
            $log($cell, CellLogAction::TransferredIn, CellState::Empty, $state, $at, $fullBoxes, $origin);
        }

        if ($boxesRemoved > 0) {
            $at = $this->timeAfter($at);
            $log($cell, CellLogAction::BoxesRemoved, CellState::Opened, CellState::Opened, $at, $remainingBoxes);
        }

        Cell::query()->whereKey($cell->id)->update(['state' => $state->value, 'updated_at' => $at]);

        return ['cell' => $cell, 'pallet' => $pallet, 'product' => $product, 'last_at' => $at];
    }

    /**
     * A pallet that was stored and later emptied: it leaves history (stored,
     * maybe opened, emptied) and a cell that is empty again. With `$emptiedAfterSeconds`
     * the emptying follows the store that quickly, written through the
     * observer so the quick-flip rule flags it.
     */
    private function seedEmptiedPallet(Cell $cell, CarbonInterface $storedAt, ?User $scenarioUser = null, ?int $emptiedAfterSeconds = null): void
    {
        $product = $this->pickProduct();
        $fullBoxes = $product->boxes_count;
        $state = $cell->flat_number === 1 ? CellState::Opened : CellState::Full;
        $actor = fn (): User => $scenarioUser ?? $this->regularWorker();

        $pallet = $this->savePallet($cell, $product, $fullBoxes, $storedAt);

        $this->saveLog($cell, CellLogAction::Stored, CellState::Empty, $state, $product, $pallet, $fullBoxes, $actor(), $storedAt, observed: $scenarioUser !== null);

        $at = $storedAt;

        if ($scenarioUser === null && $state === CellState::Full && $this->roll(40)) {
            $at = $this->timeAfter($at);
            $this->saveLog($cell, CellLogAction::Opened, CellState::Full, CellState::Opened, $product, $pallet, $fullBoxes, $actor(), $at);
            $state = CellState::Opened;
        }

        $at = $emptiedAfterSeconds !== null ? $at->copy()->addSeconds($emptiedAfterSeconds) : $this->timeAfter($at);

        $this->saveLog($cell, CellLogAction::Emptied, $state, CellState::Empty, $product, $pallet, $fullBoxes, $actor(), $at, observed: $scenarioUser !== null);

        Pallet::withoutEvents(fn (): ?bool => $pallet->delete());
    }

    /**
     * Deactivate some occupied and some empty cells (the inactive highlight
     * and the `deactivated` log action), and deactivate-then-
     * reactivate a few more so both actions exist without changing their state.
     *
     * @param  list<array{cell: Cell, pallet: Pallet, product: Product, last_at: CarbonInterface}>  $occupied
     * @param  list<Cell>  $emptyCells
     * @param  list<Cell>  $reactivatedCells
     */
    private function seedCellDeactivations(array $occupied, array $emptyCells, array $reactivatedCells): void
    {
        foreach ($occupied as $placed) {
            $cell = Cell::query()->select(['id', 'state'])->findOrFail($placed['cell']->id);

            $this->saveLog($cell, CellLogAction::Deactivated, $cell->state, $cell->state, $placed['product'], $placed['pallet'], null, $this->admin, $this->timeAfter($placed['last_at']));
            Cell::query()->whereKey($cell->id)->update(['is_active' => false]);
        }

        foreach ($emptyCells as $cell) {
            $this->saveLog($cell, CellLogAction::Deactivated, CellState::Empty, CellState::Empty, null, null, null, $this->admin, $this->pastWorkingTime($this->random->getInt(1, 30)));
            Cell::query()->whereKey($cell->id)->update(['is_active' => false]);
        }

        foreach ($reactivatedCells as $cell) {
            $deactivatedAt = $this->pastWorkingTime($this->random->getInt(10, 30));

            $this->saveLog($cell, CellLogAction::Deactivated, CellState::Empty, CellState::Empty, null, null, null, $this->admin, $deactivatedAt);
            $this->saveLog($cell, CellLogAction::Reactivated, CellState::Empty, CellState::Empty, null, null, null, $this->admin, $this->timeAfter($deactivatedAt));
        }
    }

    /**
     * Record an admin's product correction on a few stored pallets (the
     * `product_changed` log action). The pallets already hold the product the
     * log names, so this only adds the history entry PalletActionService::update()
     * would have written, without changing what is on the shelf.
     *
     * @param  list<array{cell: Cell, pallet: Pallet, product: Product, last_at: CarbonInterface}>  $occupied
     */
    private function seedProductCorrections(array $occupied): void
    {
        foreach ($occupied as $placed) {
            $cell = Cell::query()->select(['id', 'state'])->findOrFail($placed['cell']->id);

            $this->saveLog($cell, CellLogAction::ProductChanged, $cell->state, $cell->state, $placed['product'], $placed['pallet'], $placed['pallet']->remaining_boxes, $this->admin, $this->timeAfter($placed['last_at']));
        }
    }

    /**
     * Give every third product a minimum above its current pallet count (the
     * dashboard's low-stock card and the products page's `low_stock` filter),
     * every third a comfortably met one, and leave the rest unconfigured.
     * Only `wms_product_settings.minimum_pallets` is written; a settings row
     * created here carries the product's current effective box count so it
     * doesn't change.
     */
    private function seedLowStockThresholds(): void
    {
        $palletCounts = Pallet::query()->selectRaw('product_id, count(*) as pallet_count')->groupBy('product_id')->pluck('pallet_count', 'product_id');

        $products = collect($this->products)->sortBy('id')->values();

        foreach ($products as $index => $product) {
            $palletCount = (int) ($palletCounts[$product->id] ?? 0);

            $minimumPallets = match ($index % 3) {
                0 => $palletCount + 2 + ($index % 4),
                1 => intdiv($palletCount, 2),
                default => null,
            };

            if ($minimumPallets === null) {
                continue;
            }

            ProductSetting::query()->updateOrCreate(
                ['product_id' => $product->id],
                ['boxes_count' => $product->boxes_count, 'minimum_pallets' => $minimumPallets],
            );
        }
    }

    /**
     * Completed rounds spread over the last six weeks plus a few unfinished
     * ones — each unfinished round claims one row no other unfinished round
     * already holds, the same exclusivity CellVerificationService::startRound()
     * enforces — each with reports that are mostly correct and otherwise show
     * every kind of mismatch, some of them accepted by the admin.
     */
    private function seedVerificationRounds(): void
    {
        /** @var list<int> $rowIds */
        $rowIds = Row::query()
            ->whereIn('letter', range(self::FIRST_ROW_LETTER, self::LAST_ROW_LETTER))
            ->orderBy('letter')
            ->pluck('id')
            ->all();

        $claimedRowIds = Row::query()->underActiveVerification()->pluck('id')->all();
        /** @var list<int> $unclaimedRowIds */
        $unclaimedRowIds = $this->random->shuffleArray(array_values(array_diff($rowIds, $claimedRowIds)));

        for ($round = 0; $round < self::COMPLETED_ROUNDS; $round++) {
            $startedAt = $this->pastWorkingTime($this->random->getInt(1, 45));

            $this->seedRound(
                $this->pickRowIds($rowIds, $this->random->getInt(1, 3)),
                $startedAt,
                min($startedAt->copy()->addMinutes($this->random->getInt(20, 110)), now()->subMinute()),
            );
        }

        foreach (array_slice($unclaimedRowIds, 0, self::UNFINISHED_ROUNDS) as $rowId) {
            $this->seedRound([$rowId], $this->pastWorkingTime($this->random->getInt(0, 2)), null);
        }
    }

    /**
     * @param  list<int>  $rowIds
     */
    private function seedRound(array $rowIds, CarbonInterface $startedAt, ?CarbonInterface $completedAt): void
    {
        $user = $this->regularWorker();

        $round = new CellVerificationRound(['user_id' => $user->id, 'completed_at' => $completedAt]);
        $round->setAttribute('created_at', $startedAt);
        $round->setAttribute('updated_at', $completedAt ?? $startedAt);
        $round->save();
        $round->rows()->attach($rowIds);

        $cells = $this->random->shuffleArray(
            Cell::query()
                ->select(['id', 'row_id', 'state'])
                ->with('pallet:id,cell_id,product_id,remaining_boxes,expiration_date')
                ->whereIn('row_id', $rowIds)
                ->get()
                ->all(),
        );

        $reportCount = $completedAt === null ? $this->random->getInt(8, 15) : $this->random->getInt(20, 45);
        $walkEndedAt = $completedAt ?? min($startedAt->copy()->addMinutes(60), now()->subMinute());
        $walkSeconds = max(60, (int) $startedAt->diffInSeconds($walkEndedAt, true));

        foreach (array_slice($cells, 0, $reportCount) as $index => $cell) {
            $reportedAt = $startedAt->copy()->addSeconds(intdiv($walkSeconds * ($index + 1), $reportCount + 1));

            $this->seedReport($round, $user, $cell, $reportedAt, accept: $completedAt !== null && $this->roll(40));
        }
    }

    /**
     * ~70% correct (the snapshot mirrors the cell's real contents, nothing
     * "reported"); otherwise one of: the pallet is missing, an unexpected one
     * is there, or its product, box count or expiration date differ.
     */
    private function seedReport(CellVerificationRound $round, User $user, Cell $cell, CarbonInterface $reportedAt, bool $accept): void
    {
        $pallet = $cell->pallet;
        $expected = [
            'expected_cell_state' => $cell->state,
            'expected_product_id' => $pallet?->product_id,
            'expected_boxes_count' => $pallet?->remaining_boxes,
            'expected_expiration_date' => $pallet?->expiration_date,
        ];

        $attributes = [
            'cell_verification_round_id' => $round->id,
            'cell_id' => $cell->id,
            'user_id' => $user->id,
            'is_correct' => true,
            ...$expected,
        ];

        if (! $this->roll(30)) {
            $this->saveReport($attributes, $reportedAt);

            return;
        }

        $reported = match (true) {
            $pallet === null => [
                'reported_cell_state' => CellState::Full,
                'reported_product_id' => $this->pickProduct()->id,
                'reported_boxes_count' => $this->random->getInt(1, 48),
                'reported_expiration_date' => today()->addDays($this->random->getInt(30, 400)),
            ],
            default => $this->reportedMismatch($pallet, $cell),
        };

        $note = $this->roll(50) ? $this->random->getInt(0, 3) : null;

        $this->saveReport([
            ...$attributes,
            'is_correct' => false,
            ...$reported,
            'note' => $note === null ? null : ['Label unreadable.', 'Found in the neighbouring cell.', 'Pallet looks re-stacked.', 'Count done twice to be sure.'][$note],
            ...($accept ? ['accepted_at' => min($reportedAt->copy()->addDay(), now()->subMinute()), 'accepted_by' => $this->admin->id] : []),
        ], $reportedAt);
    }

    /**
     * @return array<string, mixed>
     */
    private function reportedMismatch(Pallet $pallet, Cell $cell): array
    {
        $unchanged = [
            'reported_cell_state' => $cell->state,
            'reported_product_id' => $pallet->product_id,
            'reported_boxes_count' => $pallet->remaining_boxes,
            'reported_expiration_date' => $pallet->expiration_date,
        ];

        return match ($this->random->getInt(0, 3)) {
            0 => ['reported_cell_state' => CellState::Empty, 'reported_product_id' => null, 'reported_boxes_count' => null, 'reported_expiration_date' => null],
            1 => [...$unchanged, 'reported_product_id' => $this->otherProduct($pallet->product_id)->id],
            2 => [...$unchanged, 'reported_boxes_count' => $pallet->remaining_boxes > 1 ? $pallet->remaining_boxes - $this->random->getInt(1, $pallet->remaining_boxes - 1) : $pallet->remaining_boxes + 3],
            default => [...$unchanged, 'reported_expiration_date' => ($pallet->expiration_date ?? today())->copy()->addDays($this->random->getInt(7, 60))],
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function saveReport(array $attributes, CarbonInterface $createdAt): void
    {
        $report = new CellVerificationReport($attributes);
        $report->setAttribute('created_at', $createdAt);
        $report->setAttribute('updated_at', $createdAt);
        $report->save();
    }

    private function savePallet(Cell $cell, Product $product, int $remainingBoxes, CarbonInterface $storedAt): Pallet
    {
        $pallet = new Pallet([
            'product_id' => $product->id,
            'cell_id' => $cell->id,
            'expiration_date' => $this->randomExpirationDate(),
            'remaining_boxes' => $remainingBoxes,
        ]);
        $pallet->created_at = $storedAt;
        $pallet->updated_at = $storedAt;

        Pallet::withoutEvents(fn (): bool => $pallet->save());

        return $pallet;
    }

    private function saveLog(
        Cell $cell,
        CellLogAction $action,
        CellState $from,
        CellState $to,
        ?Product $product,
        ?Pallet $pallet,
        ?int $boxesCount,
        User $user,
        CarbonInterface $at,
        ?Cell $related = null,
        bool $observed = false,
    ): CellStatusLog {
        $log = new CellStatusLog([
            'cell_id' => $cell->id,
            'related_cell_id' => $related?->id,
            'action' => $action,
            'from_state' => $from,
            'to_state' => $to,
            'product_id' => $product?->id,
            'pallet_id' => $pallet?->id,
            'boxes_count' => $boxesCount,
            'user_id' => $user->id,
        ]);
        $log->created_at = $at;
        $log->updated_at = $at;

        if ($observed) {
            $log->save();
        } else {
            CellStatusLog::withoutEvents(fn (): bool => $log->save());
        }

        return $log;
    }

    /**
     * Index-skewed so a few products hold most pallets and the tail holds few
     * or none (which is what gives the low-stock card something to count).
     */
    private function pickProduct(): Product
    {
        $unit = $this->random->getInt(0, 999_999) / 1_000_000;

        return $this->products[(int) floor($unit * $unit * count($this->products))];
    }

    private function otherProduct(int $excludedId): Product
    {
        $candidates = array_values(array_filter($this->products, fn (Product $product): bool => $product->id !== $excludedId));

        return $candidates[$this->random->getInt(0, count($candidates) - 1)] ?? $this->products[0];
    }

    private function regularWorker(): User
    {
        return $this->workers[$this->random->getInt(0, count($this->workers) - 1)];
    }

    /**
     * Expiry spread across every dashboard window: none, already expired, and
     * 1/2/4/6-month-and-beyond horizons.
     */
    private function randomExpirationDate(): ?string
    {
        $roll = $this->random->getInt(1, 100);

        $days = match (true) {
            $roll <= 6 => null,
            $roll <= 16 => -$this->random->getInt(1, 120),
            $roll <= 30 => $this->random->getInt(0, 30),
            $roll <= 42 => $this->random->getInt(31, 60),
            $roll <= 56 => $this->random->getInt(61, 120),
            $roll <= 70 => $this->random->getInt(121, 180),
            default => $this->random->getInt(181, 540),
        };

        return $days === null ? null : today()->addDays($days)->toDateString();
    }

    /**
     * Pallet ages spread across the dashboard's stale windows.
     */
    private function palletAgeInDays(): int
    {
        $roll = $this->random->getInt(1, 100);

        return match (true) {
            $roll <= 35 => $this->random->getInt(0, 14),
            $roll <= 65 => $this->random->getInt(15, 45),
            $roll <= 85 => $this->random->getInt(46, 90),
            default => $this->random->getInt(91, 180),
        };
    }

    private function roll(int $percent): bool
    {
        return $this->random->getInt(1, 100) <= $percent;
    }

    /**
     * A time inside working hours (07:00-20:59) `$daysAgo` days back, never in
     * the future.
     */
    private function pastWorkingTime(int $daysAgo): CarbonInterface
    {
        $time = now()->subDays($daysAgo)->setTime($this->random->getInt(7, 20), $this->random->getInt(0, 59));

        return $time->isFuture() ? $time->subDay() : $time;
    }

    /**
     * A working-hours time 1-5 days after `$previous`, clamped so it is never
     * in the future or before `$previous`.
     */
    private function timeAfter(CarbonInterface $previous): CarbonInterface
    {
        $candidate = $previous->copy()
            ->addDays($this->random->getInt(1, 5))
            ->setTime($this->random->getInt(7, 20), $this->random->getInt(0, 59));

        $ceiling = now()->subMinutes(10);

        return $candidate->gt($ceiling) ? $ceiling->max($previous->copy()->addMinute()) : $candidate;
    }

    /**
     * @param  list<int>  $rowIds
     * @return list<int>
     */
    private function pickRowIds(array $rowIds, int $count): array
    {
        /** @var list<int> $shuffled */
        $shuffled = $this->random->shuffleArray($rowIds);

        return array_slice($shuffled, 0, $count);
    }
}
