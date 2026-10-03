<?php

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Exceptions\InvalidSlotStateException;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;
use App\Models\User;
use App\Services\PalletActionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * These call the service directly rather than through a route: route model
 * binding always hands the service a freshly loaded pallet, so the
 * "read, then something else commits, then lock" window these cover can only
 * be opened by holding on to a model instance across another call.
 */
function palletActionsUnderTest(): PalletActionService
{
    return app(PalletActionService::class);
}

/**
 * Cells on the second level of a fresh row, ordered by id — second level so
 * transfer()'s first-level auto-open never changes the expected states.
 *
 * @return list<Cell>
 */
function secondLevelCells(int $count): array
{
    $row = Row::factory()->create(['cells_count' => $count, 'flats_count' => 2]);

    return $row->cells()->where('flat_number', 2)->orderBy('id')->get()->all();
}

test('transferring a pallet onto the cell it is already in is rejected as such and nothing changes', function () {
    [$cell] = secondLevelCells(1);
    $pallet = Pallet::factory()->create(['cell_id' => $cell->id]);
    $user = User::factory()->mobileUser()->create();

    expect(fn () => palletActionsUnderTest()->transfer($pallet, $cell->id, $user->id, null))
        ->toThrow(InvalidSlotStateException::class, __('messages.pallet_already_at_location'));

    expect($pallet->fresh()->cell_id)->toBe($cell->id)
        ->and($cell->fresh()->state)->toBe(CellState::Full)
        ->and(CellStatusLog::query()->count())->toBe(0);
});

test('a transfer of a pallet that another request moved after it was read is rejected, leaving every cell as that move left it', function () {
    [$origin, $movedTo, $destination] = secondLevelCells(3);
    $pallet = Pallet::factory()->create(['cell_id' => $origin->id]);
    $user = User::factory()->mobileUser()->create();

    $staleCopy = Pallet::query()->findOrFail($pallet->id);
    palletActionsUnderTest()->transfer(Pallet::query()->findOrFail($pallet->id), $movedTo->id, $user->id, null);

    expect(fn () => palletActionsUnderTest()->transfer($staleCopy, $destination->id, $user->id, null))
        ->toThrow(InvalidSlotStateException::class, __('messages.pallet_moved'));

    expect($pallet->fresh()->cell_id)->toBe($movedTo->id)
        ->and($origin->fresh()->state)->toBe(CellState::Empty)
        ->and($movedTo->fresh()->state)->toBe(CellState::Full)
        ->and($destination->fresh()->state)->toBe(CellState::Empty)
        // Only the concurrent move's own out/in pair.
        ->and(CellStatusLog::query()->count())->toBe(2);
});

test('a stale pallet that moved is rejected before acting on the pallet that now occupies its old cell', function (string $action, CellState $occupantState) {
    [$origin, $movedTo] = secondLevelCells(2);
    $product = Product::factory()->boxesCount(10)->create();
    $pallet = Pallet::factory()->create(['cell_id' => $origin->id, 'product_id' => $product->id, 'remaining_boxes' => 10]);
    $user = User::factory()->mobileUser()->create();

    $staleCopy = Pallet::query()->findOrFail($pallet->id);
    palletActionsUnderTest()->transfer(Pallet::query()->findOrFail($pallet->id), $movedTo->id, $user->id, null);

    // Something else is stored in the cell the stale copy still points at, in
    // the state the action would accept — so only the moved check can stop it.
    $occupant = Pallet::factory()->create(['cell_id' => $origin->id, 'product_id' => $product->id, 'remaining_boxes' => 10]);
    $origin->update(['state' => $occupantState]);
    $logCount = CellStatusLog::query()->count();

    $act = match ($action) {
        'open' => fn () => palletActionsUnderTest()->open($staleCopy, 2, false, $user->id, null),
        'removeBoxes' => fn () => palletActionsUnderTest()->removeBoxes($staleCopy, 2, false, $user->id, null),
        'emptyPallet' => fn () => palletActionsUnderTest()->emptyPallet($staleCopy, $user->id, null),
        'update' => fn () => palletActionsUnderTest()->update($staleCopy, $product->id, null, 3, $user->id, false),
    };

    expect($act)->toThrow(InvalidSlotStateException::class, __('messages.pallet_moved'));

    expect($origin->fresh()->state)->toBe($occupantState)
        ->and($occupant->fresh()->remaining_boxes)->toBe(10)
        ->and($pallet->fresh()->cell_id)->toBe($movedTo->id)
        ->and($pallet->fresh()->remaining_boxes)->toBe(10)
        ->and($movedTo->fresh()->state)->toBe(CellState::Full)
        ->and(CellStatusLog::query()->count())->toBe($logCount);
})->with([
    'open' => ['open', CellState::Full],
    'remove boxes' => ['removeBoxes', CellState::Opened],
    'empty' => ['emptyPallet', CellState::Full],
    'update' => ['update', CellState::Full],
]);

test('a transfer moves, logs and counts boxes through the locked pallet, not the callers stale copy', function () {
    [$source, $destination] = secondLevelCells(2);
    $product = Product::factory()->boxesCount(10)->create();
    $pallet = Pallet::factory()->opened()->create(['cell_id' => $source->id, 'product_id' => $product->id, 'remaining_boxes' => 10]);
    $user = User::factory()->mobileUser()->create();

    $staleCopy = Pallet::query()->findOrFail($pallet->id);
    palletActionsUnderTest()->removeBoxes(Pallet::query()->findOrFail($pallet->id), 6, false, $user->id, null);

    $transferred = palletActionsUnderTest()->transfer($staleCopy, $destination->id, $user->id, null);

    expect($transferred->cell_id)->toBe($destination->id)
        ->and($transferred->remaining_boxes)->toBe(4);

    $transferLogs = CellStatusLog::query()
        ->where('pallet_id', $pallet->id)
        ->whereIn('action', [CellLogAction::TransferredOut, CellLogAction::TransferredIn])
        ->orderBy('id')
        ->get();

    expect($transferLogs->pluck('boxes_count')->all())->toBe([4, 4])
        ->and($transferLogs->pluck('cell_id')->all())->toBe([$source->id, $destination->id]);
});

test('a transfer locks both cells in ascending id order whichever way the pallet moves, then the pallet', function (bool $towardsLowerId) {
    [$lowCell, $highCell] = secondLevelCells(2);
    [$source, $destination] = $towardsLowerId ? [$highCell, $lowCell] : [$lowCell, $highCell];
    $pallet = Pallet::factory()->create(['cell_id' => $source->id]);
    $pallet = Pallet::query()->findOrFail($pallet->id);
    $user = User::factory()->mobileUser()->create();

    DB::enableQueryLog();
    palletActionsUnderTest()->transfer($pallet, $destination->id, $user->id, null);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // sqlite compiles lockForUpdate() away, so the locking reads are matched
    // by shape: the by-id reads that run before anything is written.
    $lockingReads = collect($queries)
        ->filter(fn (array $query): bool => str_starts_with($query['query'], 'select * from "cells" where "cells"."id" = ?')
            || str_starts_with($query['query'], 'select * from "pallets" where "pallets"."id" = ?'))
        ->map(fn (array $query): array => [str_contains($query['query'], '"pallets"') ? 'pallet' : 'cell', $query['bindings'][0]])
        ->values()
        ->all();

    expect(array_slice($lockingReads, 0, 3))->toBe([
        ['cell', $lowCell->id],
        ['cell', $highCell->id],
        ['pallet', $pallet->id],
    ]);
    expect($pallet->fresh()->cell_id)->toBe($destination->id);
})->with([
    'towards the lower id' => [true],
    'towards the higher id' => [false],
]);

test('changing a pallets product writes a product_changed log carrying the new product', function () {
    [$cell] = secondLevelCells(1);
    $oldProduct = Product::factory()->boxesCount(10)->create();
    $newProduct = Product::factory()->boxesCount(12)->create();
    $pallet = Pallet::factory()->create(['cell_id' => $cell->id, 'product_id' => $oldProduct->id, 'remaining_boxes' => 8]);
    $user = User::factory()->create();

    palletActionsUnderTest()->update($pallet, $newProduct->id, '2027-03-01', 5, $user->id, false, 'Relabelled after recount');

    $log = CellStatusLog::query()->sole();

    expect($log->only(['cell_id', 'related_cell_id', 'action', 'from_state', 'to_state', 'product_id', 'pallet_id', 'boxes_count', 'user_id', 'note']))->toEqual([
        'cell_id' => $cell->id,
        'related_cell_id' => null,
        'action' => CellLogAction::ProductChanged,
        'from_state' => CellState::Full,
        'to_state' => CellState::Full,
        'product_id' => $newProduct->id,
        'pallet_id' => $pallet->id,
        'boxes_count' => 5,
        'user_id' => $user->id,
        'note' => 'Relabelled after recount',
    ]);

    $fresh = $pallet->fresh();
    expect($fresh->product_id)->toBe($newProduct->id)
        ->and($fresh->remaining_boxes)->toBe(5)
        ->and($fresh->expiration_date->toDateString())->toBe('2027-03-01')
        ->and($cell->fresh()->state)->toBe(CellState::Full);
});

test('correcting only a pallets expiration date and remaining boxes writes no log', function () {
    [$cell] = secondLevelCells(1);
    $product = Product::factory()->boxesCount(10)->create();
    $pallet = Pallet::factory()->create(['cell_id' => $cell->id, 'product_id' => $product->id, 'remaining_boxes' => 8]);
    $user = User::factory()->create();

    palletActionsUnderTest()->update($pallet, $product->id, '2027-03-01', 5, $user->id, false);

    expect($pallet->fresh()->remaining_boxes)->toBe(5)
        ->and(CellStatusLog::query()->count())->toBe(0);
});

test('updating a pallet to a product that does not exist is rejected and nothing changes', function () {
    [$cell] = secondLevelCells(1);
    $product = Product::factory()->boxesCount(10)->create();
    $pallet = Pallet::factory()->create(['cell_id' => $cell->id, 'product_id' => $product->id, 'remaining_boxes' => 8, 'expiration_date' => '2027-01-01']);
    $user = User::factory()->create();
    $missingProductId = Product::query()->max('id') + 1000;

    expect(fn () => palletActionsUnderTest()->update($pallet, $missingProductId, '2027-03-01', 5, $user->id, false))
        ->toThrow(ModelNotFoundException::class);

    $fresh = $pallet->fresh();
    expect($fresh->product_id)->toBe($product->id)
        ->and($fresh->remaining_boxes)->toBe(8)
        ->and($fresh->expiration_date->toDateString())->toBe('2027-01-01')
        ->and(CellStatusLog::query()->count())->toBe(0);
});
