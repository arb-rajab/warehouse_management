<?php

namespace Tests\Feature\Concerns;

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;
use App\Models\User;

/**
 * Shared fixture setup for the cell status log filters/scopes and the
 * dashboard stats, duplicated across the Admin (Inertia) and API (JSON)
 * controller tests that exercise the same underlying business rules through
 * two transports. Each helper only seeds data; the calling test keeps its
 * own route call and assertion style.
 */
trait SeedsCellStatusLogFixtures
{
    /**
     * @return array{product: Product, otherProduct: Product, matching: CellStatusLog}
     */
    public function seedProductFilterFixture(): array
    {
        $product = Product::factory()->create();
        $otherProduct = Product::factory()->create();

        $matching = CellStatusLog::factory()->create(['product_id' => $product->id]);
        CellStatusLog::factory()->create(['product_id' => $otherProduct->id]);

        return compact('product', 'otherProduct', 'matching');
    }

    /**
     * @return array{product: Product, otherProduct: Product, matchingA: CellStatusLog, matchingB: CellStatusLog}
     */
    public function seedMultipleProductFilterFixture(): array
    {
        $product = Product::factory()->create();
        $otherProduct = Product::factory()->create();
        $thirdProduct = Product::factory()->create();

        $matchingA = CellStatusLog::factory()->create(['product_id' => $product->id]);
        $matchingB = CellStatusLog::factory()->create(['product_id' => $otherProduct->id]);
        CellStatusLog::factory()->create(['product_id' => $thirdProduct->id]);

        return compact('product', 'otherProduct', 'matchingA', 'matchingB');
    }

    /**
     * @return array{palletId: int, matching: CellStatusLog}
     */
    public function seedPalletFilterFixture(): array
    {
        $pallet = Pallet::factory()->create();
        $palletId = $pallet->id;
        $otherPallet = Pallet::factory()->create();

        $matching = CellStatusLog::factory()->create(['pallet_id' => $palletId]);
        CellStatusLog::factory()->create(['pallet_id' => $otherPallet->id]);

        $pallet->delete();

        return compact('palletId', 'matching');
    }

    /**
     * @return array{row: Row, matching: CellStatusLog}
     */
    public function seedRowFilterFixture(): array
    {
        $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
        $otherRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
        $cell = $row->cells()->first();
        $otherCell = $otherRow->cells()->first();

        $matching = CellStatusLog::factory()->create(['cell_id' => $cell->id]);
        CellStatusLog::factory()->create(['cell_id' => $otherCell->id]);

        return compact('row', 'matching');
    }

    /**
     * @return array{rowA: Row, rowB: Row, matchingA: CellStatusLog, matchingB: CellStatusLog}
     */
    public function seedMultipleRowFilterFixture(): array
    {
        $rowA = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
        $rowB = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
        $thirdRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);

        $matchingA = CellStatusLog::factory()->create(['cell_id' => $rowA->cells()->first()->id]);
        $matchingB = CellStatusLog::factory()->create(['cell_id' => $rowB->cells()->first()->id]);
        CellStatusLog::factory()->create(['cell_id' => $thirdRow->cells()->first()->id]);

        return compact('rowA', 'rowB', 'matchingA', 'matchingB');
    }

    /**
     * @return array{matching: CellStatusLog}
     */
    public function seedColumnFilterFixture(): array
    {
        $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);
        $columnOneCell = $row->cells()->where('cell_number', 1)->first();
        $columnTwoCell = $row->cells()->where('cell_number', 2)->first();

        $matching = CellStatusLog::factory()->create(['cell_id' => $columnOneCell->id]);
        CellStatusLog::factory()->create(['cell_id' => $columnTwoCell->id]);

        return compact('matching');
    }

    /**
     * @return array{mover: User, matching: CellStatusLog}
     */
    public function seedUserFilterFixture(): array
    {
        $mover = User::factory()->create();
        $otherMover = User::factory()->create();

        $matching = CellStatusLog::factory()->create(['user_id' => $mover->id]);
        CellStatusLog::factory()->create(['user_id' => $otherMover->id]);

        return compact('mover', 'matching');
    }

    /**
     * @return array{mover: User, otherMover: User, matchingA: CellStatusLog, matchingB: CellStatusLog}
     */
    public function seedMultipleUserFilterFixture(): array
    {
        $mover = User::factory()->create();
        $otherMover = User::factory()->create();
        $thirdMover = User::factory()->create();

        $matchingA = CellStatusLog::factory()->create(['user_id' => $mover->id]);
        $matchingB = CellStatusLog::factory()->create(['user_id' => $otherMover->id]);
        CellStatusLog::factory()->create(['user_id' => $thirdMover->id]);

        return compact('mover', 'otherMover', 'matchingA', 'matchingB');
    }

    /**
     * @return array{matching: CellStatusLog}
     */
    public function seedActionFilterFixture(): array
    {
        $matching = CellStatusLog::factory()->create(['action' => CellLogAction::Opened]);
        CellStatusLog::factory()->create(['action' => CellLogAction::Emptied]);

        return compact('matching');
    }

    /**
     * @return array{opened: CellStatusLog, emptied: CellStatusLog}
     */
    public function seedMultipleActionFilterFixture(): array
    {
        $opened = CellStatusLog::factory()->create(['action' => CellLogAction::Opened]);
        $emptied = CellStatusLog::factory()->create(['action' => CellLogAction::Emptied]);
        CellStatusLog::factory()->create(['action' => CellLogAction::Stored]);

        return compact('opened', 'emptied');
    }

    /**
     * @return array{matching: CellStatusLog}
     */
    public function seedDateRangeFilterFixture(): array
    {
        $matching = CellStatusLog::factory()->create();
        $matching->forceFill(['created_at' => '2026-06-15'])->save();

        $outOfRange = CellStatusLog::factory()->create();
        $outOfRange->forceFill(['created_at' => '2026-01-01'])->save();

        return compact('matching');
    }

    /**
     * Caller must freeze Carbon at '2026-08-15 12:00:00' before calling this,
     * since the within-window vs. out-of-window backdated timestamps are
     * relative to that fixed "now".
     *
     * @return array{withinWindow: CellStatusLog}
     */
    public function seedCreatedWithinDaysFixture(): array
    {
        $withinWindow = backdate(CellStatusLog::factory()->create(), '2026-08-10 00:00:00');
        backdate(CellStatusLog::factory()->create(), '2026-08-01 00:00:00');

        return compact('withinWindow');
    }

    /**
     * @return array{matching: CellStatusLog}
     */
    public function seedPalletExpirationRangeFixture(): array
    {
        $matchingPallet = Pallet::factory()->create(['expiration_date' => '2026-06-15']);
        $outOfRangePallet = Pallet::factory()->create(['expiration_date' => '2026-01-01']);

        $matching = CellStatusLog::factory()->create(['pallet_id' => $matchingPallet->id]);
        CellStatusLog::factory()->create(['pallet_id' => $outOfRangePallet->id]);

        return compact('matching');
    }

    /**
     * Caller must freeze Carbon at '2026-08-15 12:00:00' before calling this,
     * since the within-window vs. out-of-window pallet expiration dates are
     * relative to that fixed "now".
     *
     * @return array{matching: CellStatusLog}
     */
    public function seedExpiresWithinDaysFixture(): array
    {
        $withinWindowPallet = Pallet::factory()->create(['expiration_date' => '2026-08-20']);
        $outOfRangePallet = Pallet::factory()->create(['expiration_date' => '2026-09-01']);

        $matching = CellStatusLog::factory()->create(['pallet_id' => $withinWindowPallet->id]);
        CellStatusLog::factory()->create(['pallet_id' => $outOfRangePallet->id]);

        return compact('matching');
    }

    /**
     * @return array{older: CellStatusLog, newer: CellStatusLog}
     */
    public function seedCreatedAtOrderFixture(): array
    {
        $older = backdate(CellStatusLog::factory()->create(), '2026-08-01 10:00:00');
        $newer = backdate(CellStatusLog::factory()->create(), '2026-08-01 12:00:00');

        return compact('older', 'newer');
    }

    /**
     * @return array{soon: CellStatusLog, late: CellStatusLog}
     */
    public function seedExpirationDateOrderFixture(): array
    {
        $soonPallet = Pallet::factory()->create(['expiration_date' => '2026-06-01']);
        $latePallet = Pallet::factory()->create(['expiration_date' => '2026-12-01']);

        $soon = CellStatusLog::factory()->create(['pallet_id' => $soonPallet->id]);
        $late = CellStatusLog::factory()->create(['pallet_id' => $latePallet->id]);

        return compact('soon', 'late');
    }

    /**
     * Seeds enough rows to overflow a single page of the given page size, so
     * a pagination test can assert the first page doesn't return everything.
     */
    public function seedPaginationOverflowFixture(int $perPage): void
    {
        CellStatusLog::factory()->count($perPage + 5)->create();
    }

    public function seedOccupancyFixture(): void
    {
        Cell::factory()->count(2)->create(['state' => CellState::Empty]);
        Cell::factory()->create(['state' => CellState::Opened]);
        Pallet::factory()->create();
    }

    public function seedExpiringWindowsFixture(): void
    {
        Pallet::factory()->create(['expiration_date' => '2026-08-12']);
        Pallet::factory()->create(['expiration_date' => '2026-08-20']);
        Pallet::factory()->create(['expiration_date' => '2026-11-01']);
    }

    public function seedCustomExpiringWindowFixture(): void
    {
        Pallet::factory()->create(['expiration_date' => '2026-08-25']);
    }

    /**
     * @return array{matching: Pallet}
     */
    public function seedStaleWindowFixture(): array
    {
        $matching = Pallet::factory()->stale()->create();
        Pallet::factory()->create();

        return compact('matching');
    }

    public function seedActivityWindowsFixture(): void
    {
        $cell = Cell::factory()->create();
        $pallet = Pallet::factory()->create();
        $sourceCell = Cell::factory()->create();
        $destinationCell = Cell::factory()->create();

        CellStatusLog::factory()->create(['cell_id' => $cell->id, 'action' => CellLogAction::Stored]);
        CellStatusLog::factory()->create(['cell_id' => $cell->id, 'action' => CellLogAction::Opened]);
        CellStatusLog::factory()->create(['cell_id' => $cell->id, 'action' => CellLogAction::Emptied]);
        createTransferPair($pallet, $sourceCell, $destinationCell, '2026-08-13 11:00:00', '2026-08-13 11:00:01');

        // Earlier this week (2026-08-13 is a Thursday; week start is Monday 2026-08-10).
        backdate(CellStatusLog::factory()->create(['cell_id' => $cell->id, 'action' => CellLogAction::Stored]), '2026-08-11 09:00:00');
        // Last week — must be excluded from both today's and this week's counts.
        backdate(CellStatusLog::factory()->create(['cell_id' => $cell->id, 'action' => CellLogAction::Opened]), '2026-08-06 09:00:00');
    }

    /**
     * @return array{matchingProduct: Product, matchingPallet: Pallet}
     */
    public function seedDashboardProductFilterFixture(): array
    {
        $matchingProduct = Product::factory()->create();
        $otherProduct = Product::factory()->create();

        $matchingPallet = Pallet::factory()->create(['product_id' => $matchingProduct->id, 'expiration_date' => '2026-08-14']);
        Pallet::factory()->opened()->create(['product_id' => $matchingProduct->id]);
        Pallet::factory()->create(['product_id' => $otherProduct->id, 'expiration_date' => '2026-08-14']);
        Cell::factory()->create(['state' => CellState::Empty]);

        CellStatusLog::factory()->create(['product_id' => $matchingProduct->id, 'action' => CellLogAction::Stored]);
        CellStatusLog::factory()->create(['product_id' => $otherProduct->id, 'action' => CellLogAction::Stored]);

        return compact('matchingProduct', 'matchingPallet');
    }
}
