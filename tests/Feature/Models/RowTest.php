<?php

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\CellVerificationReport;
use App\Models\CellVerificationRound;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;
use App\Models\Warehouse;

test('a row has many cells, generated on creation for exactly its own dimensions', function () {
    $row = Row::factory()->create(['cells_count' => 3, 'flats_count' => 2]);
    $otherRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);

    $cells = $row->cells;

    expect($cells)->toHaveCount(6);
    expect($cells->pluck('row_id')->unique()->all())->toBe([$row->id]);
    expect($cells->pluck('id'))->not->toContain($otherRow->cells->first()->id);
    expect($otherRow->cells()->count())->toBe(1);

    foreach ([1, 2, 3] as $cellNumber) {
        foreach ([1, 2] as $flatNumber) {
            $cell = $cells->first(fn (Cell $cell) => $cell->cell_number === $cellNumber && $cell->flat_number === $flatNumber);

            expect($cell)->not->toBeNull();
            expect($cell->state)->toBe(CellState::Empty);
        }
    }
});

test('hasPallets is false when none of the rows cells hold a pallet', function () {
    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);
    $otherRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    Pallet::factory()->create(['cell_id' => $otherRow->cells()->first()->id]);

    expect($row->hasPallets())->toBeFalse();
});

test('hasPallets is true when one of the rows cells holds a pallet', function () {
    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);
    $emptyCell = $row->cells()->first();
    $occupiedCell = $row->cells()->skip(1)->first();
    Pallet::factory()->create(['cell_id' => $occupiedCell->id]);

    expect($row->hasPallets())->toBeTrue();
    expect($emptyCell->fresh()->pallet)->toBeNull();
});

test('hasHistory is false when none of the rows cells have a status log or verification report', function () {
    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);
    $otherRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    CellStatusLog::factory()->create(['cell_id' => $otherRow->cells()->first()->id]);

    expect($row->hasHistory())->toBeFalse();
});

test('hasHistory is true when one of the rows cells has a status log', function () {
    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    CellStatusLog::factory()->create(['cell_id' => $cell->id]);

    expect($row->hasHistory())->toBeTrue();
});

test('hasHistory is true when one of the rows cells is only the related_cell_id of a transfer log', function () {
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $destinationRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $sourceCell = $row->cells()->first();
    $destinationCell = $destinationRow->cells()->first();
    CellStatusLog::factory()->create([
        'cell_id' => $destinationCell->id,
        'related_cell_id' => $sourceCell->id,
        'action' => CellLogAction::TransferredIn,
    ]);

    expect($row->hasHistory())->toBeTrue();
});

test('hasHistory is true when one of the rows cells has a verification report', function () {
    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    CellVerificationReport::factory()->create(['cell_id' => $cell->id]);

    expect($row->hasHistory())->toBeTrue();
});

test('filterOptions returns rows ordered by letter and the highest cells_count as maxColumnNumber', function () {
    $rowC = Row::factory()->create(['letter' => 'C', 'cells_count' => 2, 'flats_count' => 1]);
    $rowA = Row::factory()->create(['letter' => 'A', 'cells_count' => 5, 'flats_count' => 1]);
    $rowB = Row::factory()->create(['letter' => 'B', 'cells_count' => 3, 'flats_count' => 1]);

    $options = Row::filterOptions();

    expect($options['rows']->pluck('letter')->all())->toBe(['A', 'B', 'C']);
    expect($options['rows']->pluck('id')->all())->toBe([$rowA->id, $rowB->id, $rowC->id]);
    expect($options['maxColumnNumber'])->toBe(5);
});

test('filterOptions leaves a letter only one warehouse uses as a plain id and letter', function () {
    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);

    expect(Row::filterOptions()['rows']->all())->toBe([['id' => $row->id, 'letter' => 'A']]);
});

test('filterOptions names the warehouse of a letter that two warehouses share, and only of those', function () {
    $main = Warehouse::firstOrCreateDefault();
    $annex = Warehouse::factory()->create(['name' => 'Annex']);
    $mainA = Row::factory()->for($main)->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $annexA = Row::factory()->for($annex)->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $annexB = Row::factory()->for($annex)->create(['letter' => 'B', 'cells_count' => 1, 'flats_count' => 1]);

    expect(Row::filterOptions()['rows']->all())->toBe([
        ['id' => $mainA->id, 'letter' => 'A', 'warehouse' => ['id' => $main->id, 'name' => Warehouse::DEFAULT_NAME]],
        ['id' => $annexA->id, 'letter' => 'A', 'warehouse' => ['id' => $annex->id, 'name' => 'Annex']],
        ['id' => $annexB->id, 'letter' => 'B'],
    ]);
});

test('filterOptions maxColumnNumber is 0 when there are no rows', function () {
    $options = Row::filterOptions();

    expect($options['rows'])->toBeEmpty();
    expect($options['maxColumnNumber'])->toBe(0);
});

test('a row has many verification rounds, excluding a round covering another row', function () {
    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $otherRow = Row::factory()->create(['letter' => 'B', 'cells_count' => 1, 'flats_count' => 1]);

    $round = CellVerificationRound::factory()->covering($row)->create();
    CellVerificationRound::factory()->covering($otherRow)->create(); // noise

    expect($row->verificationRounds)->toHaveCount(1);
    expect($row->verificationRounds->first()->id)->toBe($round->id);
});

test('the underActiveVerification scope returns only rows claimed by an unfinished round', function () {
    $claimed = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $released = Row::factory()->create(['letter' => 'B', 'cells_count' => 1, 'flats_count' => 1]);
    Row::factory()->create(['letter' => 'C', 'cells_count' => 1, 'flats_count' => 1]); // noise: never in a round

    CellVerificationRound::factory()->covering($claimed)->create();
    CellVerificationRound::factory()->completed()->covering($released)->create();

    $results = Row::query()->underActiveVerification()->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($claimed->id);
});

test('highestFlatsCount is the tallest row\'s level count, and 1 when there are no rows', function () {
    expect(Row::highestFlatsCount())->toBe(1);

    Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 2]);
    Row::factory()->create(['letter' => 'B', 'cells_count' => 1, 'flats_count' => 5]);
    Row::factory()->create(['letter' => 'C', 'cells_count' => 1, 'flats_count' => 3]);

    expect(Row::highestFlatsCount())->toBe(5);
});

test('the withFullGrid scope loads every row in letter order with its ordered cells, pallets and the product box count', function () {
    $b = Row::factory()->create(['letter' => 'B', 'cells_count' => 2, 'flats_count' => 2]);
    $a = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $product = Product::factory()->boxesCount(36)->create();
    Pallet::factory()->create(['cell_id' => $b->cells()->where('cell_number', 2)->where('flat_number', 1)->first()->id, 'product_id' => $product->id]);

    $rows = Row::query()->withFullGrid()->get();

    expect($rows->pluck('letter')->all())->toBe(['A', 'B']);
    expect($rows->last()->cells->map(fn (Cell $c) => "{$c->cell_number}-{$c->flat_number}")->all())->toBe(['1-1', '1-2', '2-1', '2-2']);
    expect($rows->first()->cells)->toHaveCount(1);

    $occupied = $rows->last()->cells->first(fn (Cell $c) => $c->pallet !== null);

    expect($occupied->pallet->product->relationLoaded('setting'))->toBeTrue();
    expect($occupied->pallet->product->boxes_count)->toBe(36);
    expect($rows->last()->has_pallets)->toBeTrue();
    expect($rows->first()->has_pallets)->toBeFalse();
});

test('the withFullGrid scope can keep only cells holding a pallet of a published or unpublished product', function () {
    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 3, 'flats_count' => 1]);
    $live = Product::factory()->create();
    $retired = Product::factory()->inactive()->create();
    [$first, $second] = [$row->cells()->where('cell_number', 1)->first(), $row->cells()->where('cell_number', 2)->first()];
    Pallet::factory()->create(['cell_id' => $first->id, 'product_id' => $live->id]);
    Pallet::factory()->create(['cell_id' => $second->id, 'product_id' => $retired->id]);

    expect(Row::query()->withFullGrid(true)->first()->cells->pluck('id')->all())->toBe([$first->id]);
    expect(Row::query()->withFullGrid(false)->first()->cells->pluck('id')->all())->toBe([$second->id]);
    expect(Row::query()->withFullGrid()->first()->cells)->toHaveCount(3);
});

test('a row belongs to its warehouse', function () {
    $warehouse = Warehouse::factory()->create();
    $row = Row::factory()->for($warehouse)->create(['cells_count' => 1, 'flats_count' => 1]);
    Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);

    expect($row->warehouse->is($warehouse))->toBeTrue();
});

test('a factory row goes into the Default warehouse unless the caller picks one', function () {
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);

    expect($row->warehouse->name)->toBe(Warehouse::DEFAULT_NAME);
});

test('forWarehouses narrows rows to the given warehouses and is a no-op for null', function () {
    $north = Warehouse::factory()->create();
    $south = Warehouse::factory()->create();
    $inNorth = Row::factory()->for($north)->create(['cells_count' => 1, 'flats_count' => 1]);
    $inSouth = Row::factory()->for($south)->create(['cells_count' => 1, 'flats_count' => 1]);
    $noise = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);

    expect(Row::query()->forWarehouses([$north->id])->pluck('id')->all())->toBe([$inNorth->id])
        ->and(Row::query()->forWarehouses([$north->id, $south->id])->pluck('id')->sort()->values()->all())->toBe([$inNorth->id, $inSouth->id])
        ->and(Row::query()->forWarehouses(null)->pluck('id'))->toContain($noise->id);
});

test('routeParameters adds warehouse_id only while another warehouse uses the same letter', function () {
    $north = Warehouse::factory()->create();
    $south = Warehouse::factory()->create();
    $rowA = Row::factory()->for($north)->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $rowB = Row::factory()->for($north)->create(['letter' => 'B', 'cells_count' => 1, 'flats_count' => 1]);
    $southA = Row::factory()->for($south)->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);

    expect($rowB->routeParameters())->toBe(['row' => 'B'])
        ->and($rowA->routeParameters())->toBe(['row' => 'A', 'warehouse_id' => $north->id])
        ->and($southA->routeParameters())->toBe(['row' => 'A', 'warehouse_id' => $south->id]);
});
