<?php

use App\Enums\CellLogAction;
use App\Enums\CellLogFlagReason;
use App\Enums\CellState;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\CellStatusLogFlag;
use App\Models\CellVerificationReport;
use App\Models\CellVerificationRound;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\StagingRealisticDataSeeder;
use Illuminate\Http\Request;

/**
 * A snapshot of every table the seeder writes to, to compare a re-run against.
 *
 * @return array<string, int>
 */
function stagingSeedCounts(): array
{
    return [
        'rows' => Row::count(),
        'cells' => Cell::count(),
        'pallets' => Pallet::count(),
        'logs' => CellStatusLog::count(),
        'flags' => CellStatusLogFlag::count(),
        'rounds' => CellVerificationRound::count(),
        'reports' => CellVerificationReport::count(),
        'users' => User::count(),
        'products' => Product::count(),
    ];
}

test('refuses to run in production', function () {
    app()->instance('env', 'production');

    try {
        expect(fn () => (new StagingRealisticDataSeeder)->run())->toThrow(RuntimeException::class);
    } finally {
        app()->instance('env', 'testing');
    }

    expect(Row::count())->toBe(0);
});

test('brings every existing row, whatever its size, to 44 columns and 3 levels and creates A through S', function () {
    (new DatabaseSeeder)->run();

    // Noise a re-layout must not touch: a row already on the grid and one
    // outside A-S that was seeded under a different size.
    expect(Row::where('letter', 'AA')->sole()->cells_count)->toBe(8);

    (new StagingRealisticDataSeeder)->run();

    expect(Row::pluck('letter')->all())->toEqualCanonicalizing([...range('A', 'S'), 'AA']);

    foreach (Row::all() as $row) {
        expect($row->cells_count)->toBe(44)
            ->and($row->flats_count)->toBe(3);

        $cells = Cell::where('row_id', $row->id)->get();

        expect($cells)->toHaveCount(132)
            ->and($cells->where('cell_number', '>', 44))->toBeEmpty()
            ->and($cells->where('flat_number', '>', 3))->toBeEmpty()
            ->and($cells->map(fn (Cell $cell): string => "{$cell->cell_number}-{$cell->flat_number}")->unique())->toHaveCount(132);
    }
});

test('resizing keeps every pallet and every logged cell instead of discarding history', function () {
    (new DatabaseSeeder)->run();

    // Row A is seeded as 20 columns x 4 levels, so level 4 is outside the 44x3 grid.
    $rowA = Row::where('letter', 'A')->sole();
    $surplusWithPallet = Cell::where('row_id', $rowA->id)->where('flat_number', 4)->doesntHave('pallet')->firstOrFail();
    $palletInSurplus = Pallet::factory()->create(['cell_id' => $surplusWithPallet->id, 'product_id' => Product::query()->value('id')]);

    $surplusWithHistory = Cell::where('row_id', $rowA->id)->where('flat_number', 4)->where('id', '!=', $surplusWithPallet->id)->doesntHave('pallet')->firstOrFail();
    $logInSurplus = CellStatusLog::factory()->create(['cell_id' => $surplusWithHistory->id, 'product_id' => Product::query()->value('id')]);

    $unusedSurplusIds = Cell::where('row_id', $rowA->id)
        ->where('flat_number', 4)
        ->whereNotIn('id', [$surplusWithPallet->id, $surplusWithHistory->id])
        ->doesntHave('pallet')
        ->whereNotIn('id', CellStatusLog::query()->select('cell_id'))
        ->whereNotIn('id', CellStatusLog::query()->whereNotNull('related_cell_id')->select('related_cell_id'))
        ->whereNotIn('id', CellVerificationReport::query()->select('cell_id'))
        ->pluck('id');

    $palletIdsBefore = Pallet::pluck('id', 'cell_id');

    (new StagingRealisticDataSeeder)->run();

    // Each pre-existing pallet is still in the very same cell record...
    foreach ($palletIdsBefore as $cellId => $palletId) {
        expect(Pallet::findOrFail($palletId)->cell_id)->toBe($cellId);
    }

    // ...and the occupied/historic surplus cells were moved onto the grid, not deleted.
    $moved = Cell::with('pallet')->findOrFail($surplusWithPallet->id);
    expect($moved->pallet->is($palletInSurplus))->toBeTrue()
        ->and($moved->flat_number)->toBeLessThanOrEqual(3)
        ->and($moved->cell_number)->toBeLessThanOrEqual(44);
    expect(Cell::findOrFail($logInSurplus->cell_id)->flat_number)->toBeLessThanOrEqual(3);

    // The unused ones were deleted, and the row is exactly 132 cells.
    expect(Cell::whereIn('id', $unusedSurplusIds)->count())->toBe(0)
        ->and(Cell::where('row_id', $rowA->id)->count())->toBe(132);
});

test('running it twice changes nothing the second time', function () {
    (new DatabaseSeeder)->run();

    (new StagingRealisticDataSeeder)->run();
    $afterFirstRun = stagingSeedCounts();

    (new StagingRealisticDataSeeder)->run();

    expect(stagingSeedCounts())->toBe($afterFirstRun)
        ->and(User::where('email', 'staging-worker-1@staging-seed.test')->count())->toBe(1);
});

test('seeds data for every dashboard card and page filter, each with matching and non-matching rows', function () {
    (new DatabaseSeeder)->run();

    // Staging gets its unpublished products from `products:sync`; stand in for that.
    Product::query()->orderBy('id')->limit(2)->get()->each(fn (Product $product) => $product->forceFill(['published' => false])->save());

    (new StagingRealisticDataSeeder)->run();

    $totalPallets = Pallet::count();
    $today = today();

    // Cell map / matching pallets: state and is_active.
    foreach ([CellState::Empty, CellState::Full, CellState::Opened] as $state) {
        $this->assertGreaterThan(0, Cell::where('state', $state->value)->count(), "no {$state->value} cells");
    }
    expect(Cell::where('is_active', false)->has('pallet')->count())->toBeGreaterThan(0)
        ->and(Cell::where('is_active', false)->doesntHave('pallet')->count())->toBeGreaterThan(0)
        ->and(Cell::where('is_active', true)->count())->toBeGreaterThan(0);

    // Dashboard expiring cards: expired, 1/2/4/6 month windows, no date at all.
    expect(Pallet::where('expiration_date', '<', $today)->count())->toBeGreaterThan(0)
        ->and(Pallet::whereNull('expiration_date')->count())->toBeGreaterThan(0);
    foreach ([[0, 1], [1, 2], [2, 4], [4, 6]] as [$fromMonths, $toMonths]) {
        $this->assertGreaterThan(
            0,
            Pallet::whereBetween('expiration_date', [$today->copy()->addMonths($fromMonths)->addDay(), $today->copy()->addMonths($toMonths)])->count(),
            "nothing expires between {$fromMonths} and {$toMonths} months",
        );
    }
    expect(Pallet::where('expiration_date', '>', $today->copy()->addMonths(6))->count())->toBeGreaterThan(0);

    // Dashboard stale cards and the stale highlight.
    foreach ([14, 30, 60, 90] as $days) {
        $stale = Pallet::where('created_at', '<=', $today->copy()->subDays($days))->count();
        expect($stale)->toBeGreaterThan(0)->toBeLessThan($totalPallets);
    }

    // Products: inactive-in-warehouse and low-stock.
    expect(Product::where('published', false)->inWarehouse()->count())->toBeGreaterThan(0)
        ->and(Product::where('published', true)->inWarehouse()->count())->toBeGreaterThan(0);
    $lowStock = Product::whereHas('setting', fn ($setting) => $setting->whereNotNull('minimum_pallets'))
        ->whereRaw('(select minimum_pallets from wms_product_settings where wms_product_settings.product_id = products.id) > (select count(*) from pallets where pallets.product_id = products.id)')
        ->count();
    expect($lowStock)->toBeGreaterThan(0)->toBeLessThan(Product::count());

    // Cell logs: every action, several users and rows, flagged and not, both acknowledgement states.
    foreach (CellLogAction::cases() as $action) {
        $this->assertGreaterThan(0, CellStatusLog::where('action', $action->value)->count(), "no {$action->value} logs");
    }
    foreach (CellLogFlagReason::cases() as $reason) {
        $this->assertGreaterThan(0, CellStatusLogFlag::where('reason', $reason->value)->count(), "no {$reason->value} flags");
    }
    expect(CellStatusLogFlag::whereNotNull('acknowledged_at')->count())->toBeGreaterThan(0)
        ->and(CellStatusLogFlag::whereNull('acknowledged_at')->count())->toBeGreaterThan(0)
        ->and(CellStatusLog::distinct()->count('user_id'))->toBeGreaterThanOrEqual(5)
        ->and(CellStatusLog::distinct()->count('cell_id'))->toBeGreaterThan(100)
        ->and(CellStatusLog::count())->toBeGreaterThan(20);

    $filteredLogs = fn (array $query): int => CellStatusLog::query()->filtered(Request::create('/', 'GET', $query))->count();
    $totalLogs = CellStatusLog::count();
    $someUserId = CellStatusLog::query()->value('user_id');
    $topProductId = Pallet::query()->select('product_id')->groupBy('product_id')->orderByRaw('count(*) desc')->value('product_id');
    $loggedRowId = Cell::query()->whereIn('id', CellStatusLog::query()->select('cell_id'))->value('row_id');
    $loggedColumnNumber = Cell::query()->whereIn('id', CellStatusLog::query()->select('cell_id'))->where('cell_number', '>', 20)->value('cell_number');
    $reportedRowId = Cell::query()->whereIn('id', CellVerificationReport::query()->select('cell_id'))->groupBy('row_id')->orderByRaw('count(*) desc')->value('row_id');

    foreach ([
        ['flagged' => true],
        ['action' => ['stored']],
        ['action' => ['transferred_out', 'transferred_in']],
        ['created_within_days' => 7],
        ['date_from' => $today->copy()->subDays(10)->toDateString(), 'date_to' => $today->copy()->subDays(3)->toDateString()],
        ['expires_within_days' => 30],
        ['expiration_date_from' => $today->copy()->addMonths(2)->toDateString()],
        ['row_id' => [$loggedRowId]],
        ['column_number' => $loggedColumnNumber],
        ['user_id' => [$someUserId]],
        ['product_id' => [$topProductId]],
        ['product_published' => false],
        ['product_published' => true],
    ] as $query) {
        $matching = $filteredLogs($query);

        $this->assertGreaterThan(0, $matching, 'no logs match '.json_encode($query));
        $this->assertLessThan($totalLogs, $matching, 'every log matches '.json_encode($query));
    }

    // Verification rounds and reports: completed and unfinished, several users and dates.
    $filteredRounds = fn (array $query): int => CellVerificationRound::query()->filtered(Request::create('/', 'GET', $query))->count();
    $totalRounds = CellVerificationRound::count();

    expect($totalRounds)->toBeGreaterThanOrEqual(17);
    foreach ([
        ['completed' => true],
        ['completed' => false],
        ['created_within_days' => 7],
        ['date_to' => $today->copy()->subDays(20)->toDateString()],
        ['user_id' => [CellVerificationRound::query()->value('user_id')]],
    ] as $query) {
        $matching = $filteredRounds($query);

        $this->assertGreaterThan(0, $matching, 'no rounds match '.json_encode($query));
        $this->assertLessThan($totalRounds, $matching, 'every round matches '.json_encode($query));
    }

    $filteredReports = fn (array $query): int => CellVerificationReport::query()->filtered(Request::create('/', 'GET', $query))->count();
    $totalReports = CellVerificationReport::count();

    expect($totalReports)->toBeGreaterThan(100);
    foreach ([
        ['is_correct' => true],
        ['is_correct' => false],
        ['product_id' => [$topProductId]],
        ['row_id' => [$reportedRowId]],
        ['created_within_days' => 7],
        ['date_to' => $today->copy()->subDays(20)->toDateString()],
    ] as $query) {
        $matching = $filteredReports($query);

        $this->assertGreaterThan(0, $matching, 'no reports match '.json_encode($query));
        $this->assertLessThan($totalReports, $matching, 'every report matches '.json_encode($query));
    }

    expect(CellVerificationReport::where('is_correct', false)->whereNotNull('accepted_at')->count())->toBeGreaterThan(0)
        ->and(CellVerificationReport::where('is_correct', false)->whereNull('accepted_at')->count())->toBeGreaterThan(0)
        ->and(CellVerificationReport::where('is_correct', false)->where('reported_cell_state', CellState::Empty->value)->count())->toBeGreaterThan(0)
        ->and(CellVerificationReport::where('is_correct', false)->where('expected_cell_state', CellState::Empty->value)->count())->toBeGreaterThan(0)
        ->and(CellVerificationReport::where('is_correct', false)->whereColumn('expected_product_id', '!=', 'reported_product_id')->count())->toBeGreaterThan(0);
});

test('keeps unfinished rounds from claiming the same row twice and leaves most rows actionable', function () {
    (new DatabaseSeeder)->run();
    (new StagingRealisticDataSeeder)->run();

    $unfinished = CellVerificationRound::query()->unfinished()->with('rows')->get();
    $claimedRowIds = $unfinished->flatMap(fn (CellVerificationRound $round) => $round->rows->modelKeys());

    expect($claimedRowIds->count())->toBe($claimedRowIds->unique()->count())
        ->and(Row::query()->underActiveVerification()->count())->toBeLessThan(Row::count() / 2);
});

test('never writes the products table, whose synced columns belong to products:sync', function () {
    (new DatabaseSeeder)->run();

    $before = Product::query()->orderBy('id')->get(['id', 'name', 'ar_name', 'published', 'updated_at'])->toArray();

    (new StagingRealisticDataSeeder)->run();

    expect(Product::query()->orderBy('id')->get(['id', 'name', 'ar_name', 'published', 'updated_at'])->toArray())->toEqual($before);
});
