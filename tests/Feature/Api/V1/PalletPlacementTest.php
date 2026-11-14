<?php

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Models\CellStatusLog;
use App\Models\CellVerificationRound;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;
use App\Models\Setting;
use App\Models\Warehouse;

/**
 * The coordinates of the first cell of a one-cell row, as place sends them.
 *
 * @return array{row_letter: string, cell_number: int, flat_number: int}
 */
function slotOf(Row $row, int $cellNumber = 1, int $flatNumber = 1): array
{
    return ['row_letter' => $row->letter, 'cell_number' => $cellNumber, 'flat_number' => $flatNumber];
}

// --- Place: the happy path -----------------------------------------------------

test('placing an unplaced pallet puts it in the slot, answers it as placed and logs it as stored with its boxes', function () {
    $user = actingAsMobileUser();
    $warehouse = Warehouse::factory()->create(['name' => 'Annex']);
    $row = Row::factory()->for($warehouse)->create(['letter' => 'Z', 'cells_count' => 2, 'flats_count' => 3]);
    $cell = $row->cells()->where('cell_number', 2)->where('flat_number', 3)->first();
    $product = Product::factory()->imageUrl('https://cdn.example.com/widgets.png')->boxesCount(10)->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    $pallet = unplacedPalletIn($warehouse, ['product_id' => $product->id, 'remaining_boxes' => 7, 'expiration_date' => '2027-03-04']);

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/place", [...slotOf($row, 2, 3), 'note' => 'Back of the hall']);

    $response->assertOk();
    $log = CellStatusLog::query()->sole();

    expect($response->json())->toEqual([
        'id' => $pallet->id,
        'placed' => true,
        'state' => 'full',
        'expiration_date' => '2027-03-04',
        'cell_entered_at' => $log->created_at->toIso8601String(),
        'remaining_boxes' => 7,
        'boxes_depleted_message' => null,
        'note' => null,
        'product' => ['id' => $product->id, 'name' => 'Widgets', 'ar_name' => 'ودجات', 'image_url' => 'https://cdn.example.com/widgets.png', 'boxes_count' => 10, 'active' => true],
        'location' => ['row_letter' => 'Z', 'cell_number' => 2, 'flat_number' => 3],
        'warehouse' => ['id' => $warehouse->id, 'name' => 'Annex'],
    ]);

    expect($pallet->fresh()->cell_id)->toBe($cell->id)
        ->and($pallet->fresh()->receipt_id)->toBe($pallet->receipt_id)
        ->and($cell->fresh()->state)->toBe(CellState::Full)
        ->and($log->only(['cell_id', 'related_cell_id', 'action', 'from_state', 'to_state', 'product_id', 'pallet_id', 'boxes_count', 'user_id', 'note']))->toEqual([
            'cell_id' => $cell->id,
            'related_cell_id' => null,
            'action' => CellLogAction::Stored,
            'from_state' => CellState::Empty,
            'to_state' => CellState::Full,
            'product_id' => $product->id,
            'pallet_id' => $pallet->id,
            'boxes_count' => 7,
            'user_id' => $user->id,
            'note' => 'Back of the hall',
        ]);
});

test('a pallet placed without a note logs a null note', function () {
    actingAsMobileUser();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $pallet = unplacedPalletIn(Warehouse::firstOrCreateDefault());

    $this->postJson("/api/v1/pallets/{$pallet->id}/place", slotOf($row))->assertOk();

    expect(CellStatusLog::query()->sole()->note)->toBeNull();
});

test('a pallet placed on the first level is opened when level 1 may be opened, and full on any other level', function (int $level, string $state) {
    actingAsMobileUser();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 4]);
    $pallet = unplacedPalletIn(Warehouse::firstOrCreateDefault());

    $this->postJson("/api/v1/pallets/{$pallet->id}/place", slotOf($row, 1, $level))
        ->assertOk()->assertJsonPath('state', $state);

    expect($row->cells()->where('flat_number', $level)->first()->state->value)->toBe($state)
        ->and(CellStatusLog::query()->sole()->to_state->value)->toBe($state);
})->with([
    'first level' => [1, 'opened'],
    'second level' => [2, 'full'],
    'fourth level' => [4, 'full'],
]);

test('a pallet placed on the first level stays full when the settings do not allow opening there', function () {
    actingAsMobileUser();
    Setting::factory()->create(['allowed_open_levels' => [2]]);
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $pallet = unplacedPalletIn(Warehouse::firstOrCreateDefault());

    $this->postJson("/api/v1/pallets/{$pallet->id}/place", slotOf($row))->assertOk()->assertJsonPath('state', 'full');

    expect($row->cells()->first()->state)->toBe(CellState::Full);
});

test('placing hands out the ETag of the placed pallet, which differs from the one it had unplaced', function () {
    actingAsMobileUser();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 2]);
    $pallet = unplacedPalletIn(Warehouse::firstOrCreateDefault());

    $unplacedEtag = $this->getJson("/api/v1/pallets/{$pallet->id}")->assertOk()->headers->get('ETag');
    $placed = $this->postJson("/api/v1/pallets/{$pallet->id}/place", slotOf($row, 1, 2))->assertOk();
    $placedEtag = $placed->headers->get('ETag');

    expect($unplacedEtag)->not->toBeNull()
        ->and($placedEtag)->not->toBeNull()->not->toBe($unplacedEtag)
        ->and($this->getJson("/api/v1/pallets/{$pallet->id}")->headers->get('ETag'))->toBe($placedEtag);
});

test('a placed pallet can be acted on with the normal pallet actions, and leaves the unplaced listing', function () {
    actingAsMobileUser();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $pallet = unplacedPalletIn(Warehouse::firstOrCreateDefault(), ['remaining_boxes' => 6]);
    unplacedPalletIn(Warehouse::firstOrCreateDefault()); // noise: stays unplaced

    $this->postJson("/api/v1/pallets/{$pallet->id}/place", slotOf($row))->assertOk();
    $this->postJson("/api/v1/pallets/{$pallet->id}/remove-boxes", ['boxes_count' => 2])->assertOk()->assertJsonPath('remaining_boxes', 4);

    $listed = collect($this->getJson('/api/v1/pallets/unplaced')->assertOk()->json('data'))->pluck('id')->all();
    expect($listed)->not->toContain($pallet->id)->toHaveCount(1);
});

test('any WMS user may place a pallet, not only an admin', function () {
    actingAsMobileUser();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $pallet = unplacedPalletIn(Warehouse::firstOrCreateDefault());

    $this->postJson("/api/v1/pallets/{$pallet->id}/place", slotOf($row))->assertOk();
});

// --- Place: refusals, each leaving everything as it was ---------------------------

test('placing is refused to a guest and changes nothing', function () {
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $pallet = unplacedPalletIn(Warehouse::firstOrCreateDefault());

    $this->postJson("/api/v1/pallets/{$pallet->id}/place", slotOf($row))->assertUnauthorized();

    expect($pallet->fresh()->cell_id)->toBeNull()
        ->and($row->cells()->first()->state)->toBe(CellState::Empty)
        ->and(CellStatusLog::query()->count())->toBe(0);
});

test('placing a pallet that does not exist is a 404', function () {
    actingAsMobileUser();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);

    $this->postJson('/api/v1/pallets/999999/place', slotOf($row))->assertNotFound();
});

test('placing a pallet that is already in a cell is refused with 409 pallet_already_placed, and it stays where it is', function () {
    actingAsMobileUser();
    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 2, 'flats_count' => 1]);
    [$current, $target] = $row->cells()->orderBy('cell_number')->get();
    $pallet = Pallet::factory()->create(['cell_id' => $current->id]);

    $this->postJson("/api/v1/pallets/{$pallet->id}/place", slotOf($row, 2))
        ->assertConflict()
        ->assertExactJson(['message' => __('messages.pallet_already_placed'), 'error_code' => 'pallet_already_placed']);

    expect($pallet->fresh()->cell_id)->toBe($current->id)
        ->and($target->fresh()->state)->toBe(CellState::Empty)
        ->and(CellStatusLog::query()->count())->toBe(0);
});

test('placing into another warehouse than the pallet was received into is refused with 409 pallet_warehouse_mismatch', function () {
    actingAsMobileUser();
    $receivedInto = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();
    $row = Row::factory()->for($other)->create(['letter' => 'B', 'cells_count' => 1, 'flats_count' => 1]);
    $pallet = unplacedPalletIn($receivedInto);

    $this->postJson("/api/v1/pallets/{$pallet->id}/place", slotOf($row))
        ->assertConflict()
        ->assertExactJson(['message' => __('messages.pallet_warehouse_mismatch'), 'error_code' => 'pallet_warehouse_mismatch']);

    expect($pallet->fresh()->cell_id)->toBeNull()
        ->and($row->cells()->first()->state)->toBe(CellState::Empty)
        ->and(CellStatusLog::query()->count())->toBe(0);
});

test('a pallet that arrived on no receipt cannot be placed anywhere, as it belongs to no warehouse', function () {
    actingAsMobileUser();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $pallet = Pallet::factory()->unplaced()->create(['receipt_id' => null]);

    $this->postJson("/api/v1/pallets/{$pallet->id}/place", slotOf($row))
        ->assertConflict()->assertJsonPath('error_code', 'pallet_warehouse_mismatch');

    expect($pallet->fresh()->cell_id)->toBeNull();
});

test('placing into an occupied slot is refused with 409 slot_not_empty and the pallet stays unplaced', function () {
    actingAsMobileUser();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $occupant = Pallet::factory()->create(['cell_id' => $row->cells()->first()->id]);
    $pallet = unplacedPalletIn(Warehouse::firstOrCreateDefault());

    $this->postJson("/api/v1/pallets/{$pallet->id}/place", slotOf($row))->assertConflict()->assertJsonPath('error_code', 'slot_not_empty');

    expect($pallet->fresh()->cell_id)->toBeNull()
        ->and($occupant->fresh()->cell_id)->toBe($row->cells()->first()->id)
        ->and(CellStatusLog::query()->count())->toBe(0);
});

test('placing into an inactive slot is refused with 409 slot_inactive', function () {
    actingAsMobileUser();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $row->cells()->first()->update(['is_active' => false]);
    $pallet = unplacedPalletIn(Warehouse::firstOrCreateDefault());

    $this->postJson("/api/v1/pallets/{$pallet->id}/place", slotOf($row))->assertConflict()->assertJsonPath('error_code', 'slot_inactive');

    expect($pallet->fresh()->cell_id)->toBeNull()
        ->and(CellStatusLog::query()->count())->toBe(0);
});

test('placing into a row under an unfinished verification round is refused with cell_in_active_round', function () {
    actingAsMobileUser();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    CellVerificationRound::factory()->covering($row)->create();
    $pallet = unplacedPalletIn(Warehouse::firstOrCreateDefault());

    assertCellInActiveRoundRejection($this->postJson("/api/v1/pallets/{$pallet->id}/place", slotOf($row)));

    expect($pallet->fresh()->cell_id)->toBeNull()
        ->and($row->cells()->first()->state)->toBe(CellState::Empty)
        ->and(CellStatusLog::query()->count())->toBe(0);
});

// --- Place: If-Match -------------------------------------------------------------

test('placing with the pallets current ETag in If-Match goes through', function () {
    actingAsMobileUser();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $pallet = unplacedPalletIn(Warehouse::firstOrCreateDefault());
    $etag = $this->getJson("/api/v1/pallets/{$pallet->id}")->headers->get('ETag');

    $this->postJson("/api/v1/pallets/{$pallet->id}/place", slotOf($row), ['If-Match' => $etag])->assertOk();

    expect($pallet->fresh()->cell_id)->toBe($row->cells()->first()->id);
});

test('placing with a stale If-Match is refused with 412 and nothing changes', function () {
    actingAsMobileUser();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $pallet = unplacedPalletIn(Warehouse::firstOrCreateDefault(), ['remaining_boxes' => 6]);
    $etag = $this->getJson("/api/v1/pallets/{$pallet->id}")->headers->get('ETag');
    // The count is corrected by someone else after the worker loaded the pallet.
    $pallet->update(['remaining_boxes' => 5]);

    $this->postJson("/api/v1/pallets/{$pallet->id}/place", slotOf($row), ['If-Match' => $etag])
        ->assertStatus(412)
        ->assertJsonPath('error_code', 'precondition_failed');

    expect($pallet->fresh()->cell_id)->toBeNull()
        ->and($row->cells()->first()->state)->toBe(CellState::Empty)
        ->and(CellStatusLog::query()->count())->toBe(0);
});

test('an ETag read while the pallet was unplaced is stale once another worker has placed it', function () {
    actingAsMobileUser();
    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 2, 'flats_count' => 1]);
    $pallet = unplacedPalletIn(Warehouse::firstOrCreateDefault());
    $etag = $this->getJson("/api/v1/pallets/{$pallet->id}")->headers->get('ETag');

    $this->postJson("/api/v1/pallets/{$pallet->id}/place", slotOf($row, 1))->assertOk();

    // The second worker, still holding the unplaced ETag, is told the pallet is already placed.
    $this->postJson("/api/v1/pallets/{$pallet->id}/place", slotOf($row, 2), ['If-Match' => $etag])
        ->assertConflict()->assertJsonPath('error_code', 'pallet_already_placed');
});

test('placing with If-Match star or no If-Match applies no precondition', function () {
    actingAsMobileUser();
    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 2, 'flats_count' => 1]);
    $first = unplacedPalletIn(Warehouse::firstOrCreateDefault());
    $second = unplacedPalletIn(Warehouse::firstOrCreateDefault());

    $this->postJson("/api/v1/pallets/{$first->id}/place", slotOf($row, 1), ['If-Match' => '*'])->assertOk();
    $this->postJson("/api/v1/pallets/{$second->id}/place", slotOf($row, 2))->assertOk();
});

// --- Place: the warehouse of a shared row letter -----------------------------------

test('without a warehouse_id a letter two warehouses use is placed in the pallets own warehouse', function () {
    actingAsMobileUser();
    $north = Warehouse::factory()->create(['name' => 'North']);
    $south = Warehouse::factory()->create(['name' => 'South']);
    $northRow = Row::factory()->for($north)->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 2]);
    $southRow = Row::factory()->for($south)->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 2]);
    $pallet = unplacedPalletIn($north);

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/place", slotOf($northRow, 1, 2));

    $response->assertOk()->assertJsonPath('warehouse', ['id' => $north->id, 'name' => 'North']);
    expect($pallet->fresh()->cell_id)->toBe($northRow->cells()->where('flat_number', 2)->value('id'))
        ->and($southRow->cells()->where('state', '!=', CellState::Empty->value)->count())->toBe(0);
});

test('an explicit warehouse_id of the pallets own warehouse places it there', function () {
    actingAsMobileUser();
    $north = Warehouse::factory()->create();
    $south = Warehouse::factory()->create();
    $northRow = Row::factory()->for($north)->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 2]);
    Row::factory()->for($south)->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 2]);
    $pallet = unplacedPalletIn($north);

    $this->postJson("/api/v1/pallets/{$pallet->id}/place", [...slotOf($northRow, 1, 2), 'warehouse_id' => $north->id])->assertOk();

    expect($pallet->fresh()->cell_id)->toBe($northRow->cells()->where('flat_number', 2)->value('id'));
});

test('an explicit warehouse_id of another warehouse is refused with 409 pallet_warehouse_mismatch, even for a shared letter', function () {
    actingAsMobileUser();
    $north = Warehouse::factory()->create();
    $south = Warehouse::factory()->create();
    Row::factory()->for($north)->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 2]);
    $southRow = Row::factory()->for($south)->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 2]);
    $pallet = unplacedPalletIn($north);

    $this->postJson("/api/v1/pallets/{$pallet->id}/place", [...slotOf($southRow, 1, 2), 'warehouse_id' => $south->id])
        ->assertConflict()->assertJsonPath('error_code', 'pallet_warehouse_mismatch');

    expect($pallet->fresh()->cell_id)->toBeNull()
        ->and(CellStatusLog::query()->count())->toBe(0);
});

test('a letter two other warehouses share, with no warehouse_id, still asks for one', function () {
    actingAsMobileUser();
    $receivedInto = Warehouse::factory()->create();
    $north = Warehouse::factory()->create();
    $south = Warehouse::factory()->create();
    Row::factory()->for($north)->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    Row::factory()->for($south)->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $pallet = unplacedPalletIn($receivedInto);

    $this->postJson("/api/v1/pallets/{$pallet->id}/place", ['row_letter' => 'A', 'cell_number' => 1, 'flat_number' => 1])
        ->assertUnprocessable()->assertJsonValidationErrors(['warehouse_id']);

    expect($pallet->fresh()->cell_id)->toBeNull();
});

test('a letter only one warehouse uses resolves there without a warehouse_id, and is then refused if it is not the pallets warehouse', function () {
    actingAsMobileUser();
    $receivedInto = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();
    $row = Row::factory()->for($other)->create(['letter' => 'Q', 'cells_count' => 1, 'flats_count' => 1]);
    $pallet = unplacedPalletIn($receivedInto);

    $this->postJson("/api/v1/pallets/{$pallet->id}/place", slotOf($row))->assertConflict()->assertJsonPath('error_code', 'pallet_warehouse_mismatch');
});

// --- Place: validation -----------------------------------------------------------

test('placing validates the coordinates and the note, and changes nothing', function (array $payload, string $field) {
    actingAsMobileUser();
    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $pallet = unplacedPalletIn(Warehouse::firstOrCreateDefault());

    $this->postJson("/api/v1/pallets/{$pallet->id}/place", $payload)->assertUnprocessable()->assertJsonValidationErrors([$field]);

    expect($pallet->fresh()->cell_id)->toBeNull()
        ->and($row->cells()->first()->state)->toBe(CellState::Empty)
        ->and(CellStatusLog::query()->count())->toBe(0);
})->with([
    'no row letter' => [['cell_number' => 1, 'flat_number' => 1], 'row_letter'],
    'unknown row letter' => [['row_letter' => 'Y', 'cell_number' => 1, 'flat_number' => 1], 'row_letter'],
    'row letter too long' => [['row_letter' => 'ABC', 'cell_number' => 1, 'flat_number' => 1], 'row_letter'],
    'no cell number' => [['row_letter' => 'A', 'flat_number' => 1], 'cell_number'],
    'cell number zero' => [['row_letter' => 'A', 'cell_number' => 0, 'flat_number' => 1], 'cell_number'],
    'cell number past the row' => [['row_letter' => 'A', 'cell_number' => 2, 'flat_number' => 1], 'cell_number'],
    'no flat number' => [['row_letter' => 'A', 'cell_number' => 1], 'flat_number'],
    'flat number past the row' => [['row_letter' => 'A', 'cell_number' => 1, 'flat_number' => 2], 'cell_number'],
    'unknown warehouse' => [['row_letter' => 'A', 'cell_number' => 1, 'flat_number' => 1, 'warehouse_id' => 999999], 'warehouse_id'],
    'note too long' => [['row_letter' => 'A', 'cell_number' => 1, 'flat_number' => 1, 'note' => str_repeat('n', 1001)], 'note'],
]);

// --- GET pallets/unplaced -----------------------------------------------------------

test('the unplaced listing shows every property of an unplaced pallet, with its warehouse from the receipt', function () {
    actingAsMobileUser();
    $warehouse = Warehouse::factory()->create(['name' => 'Annex']);
    $product = Product::factory()->imageUrl(null)->boxesCount(10)->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    $pallet = unplacedPalletIn($warehouse, ['product_id' => $product->id, 'remaining_boxes' => 9, 'expiration_date' => '2027-03-04']);

    $response = $this->getJson('/api/v1/pallets/unplaced');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0'))->toEqual([
            'id' => $pallet->id,
            'placed' => false,
            'state' => null,
            'expiration_date' => '2027-03-04',
            'cell_entered_at' => null,
            'remaining_boxes' => 9,
            'boxes_depleted_message' => null,
            'note' => null,
            'product' => ['id' => $product->id, 'name' => 'Widgets', 'ar_name' => 'ودجات', 'image_url' => null, 'boxes_count' => 10, 'active' => true],
            'location' => null,
            'warehouse' => ['id' => $warehouse->id, 'name' => 'Annex'],
        ]);
});

test('the unplaced listing leaves out placed pallets', function () {
    actingAsMobileUser();
    $unplaced = unplacedPalletIn(Warehouse::firstOrCreateDefault());
    Pallet::factory()->create(); // noise: placed
    $received = unplacedPalletIn(Warehouse::firstOrCreateDefault());
    $received->update(['cell_id' => Row::factory()->create(['cells_count' => 1, 'flats_count' => 1])->cells()->value('id')]); // noise: received, then placed

    $ids = collect($this->getJson('/api/v1/pallets/unplaced')->assertOk()->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$unplaced->id]);
});

test('the unplaced listing is oldest received first, breaking a tie by id', function () {
    actingAsMobileUser();
    $newest = backdate(unplacedPalletIn(Warehouse::firstOrCreateDefault()), '2026-11-08 09:00:00');
    $oldest = backdate(unplacedPalletIn(Warehouse::firstOrCreateDefault()), '2026-11-01 09:00:00');
    $tiedFirst = backdate(unplacedPalletIn(Warehouse::firstOrCreateDefault()), '2026-11-05 09:00:00');
    $tiedSecond = backdate(unplacedPalletIn(Warehouse::firstOrCreateDefault()), '2026-11-05 09:00:00');

    $ids = collect($this->getJson('/api/v1/pallets/unplaced')->assertOk()->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$oldest->id, $tiedFirst->id, $tiedSecond->id, $newest->id]);
});

test('the unplaced listing filters by warehouse_ids[] through the receipts warehouse', function () {
    actingAsMobileUser();
    $north = Warehouse::factory()->create();
    $south = Warehouse::factory()->create();
    $inNorth = unplacedPalletIn($north);
    $inSouth = unplacedPalletIn($south);
    $inDefault = unplacedPalletIn(Warehouse::firstOrCreateDefault()); // noise

    $one = collect($this->getJson("/api/v1/pallets/unplaced?warehouse_ids[]={$north->id}")->assertOk()->json('data'))->pluck('id')->all();
    $two = collect($this->getJson("/api/v1/pallets/unplaced?warehouse_ids[]={$north->id}&warehouse_ids[]={$south->id}")->assertOk()->json('data'))->pluck('id')->sort()->values()->all();
    $all = collect($this->getJson('/api/v1/pallets/unplaced')->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

    expect($one)->toBe([$inNorth->id])
        ->and($two)->toBe([$inNorth->id, $inSouth->id])
        ->and($all)->toBe([$inNorth->id, $inSouth->id, $inDefault->id]);
});

test('the unplaced listing filters by product_id[]', function () {
    actingAsMobileUser();
    $widgets = Product::factory()->create();
    $gadgets = Product::factory()->create();
    $gizmos = Product::factory()->create(); // noise
    $widgetPallet = unplacedPalletIn(Warehouse::firstOrCreateDefault(), ['product_id' => $widgets->id]);
    $gadgetPallet = unplacedPalletIn(Warehouse::firstOrCreateDefault(), ['product_id' => $gadgets->id]);
    unplacedPalletIn(Warehouse::firstOrCreateDefault(), ['product_id' => $gizmos->id]);

    $one = collect($this->getJson("/api/v1/pallets/unplaced?product_id[]={$widgets->id}")->assertOk()->json('data'))->pluck('id')->all();
    $two = collect($this->getJson("/api/v1/pallets/unplaced?product_id[]={$widgets->id}&product_id[]={$gadgets->id}")->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

    expect($one)->toBe([$widgetPallet->id])
        ->and($two)->toBe([$widgetPallet->id, $gadgetPallet->id]);
});

test('the unplaced listing combines the warehouse and product filters', function () {
    actingAsMobileUser();
    $north = Warehouse::factory()->create();
    $widgets = Product::factory()->create();
    $gadgets = Product::factory()->create();
    $match = unplacedPalletIn($north, ['product_id' => $widgets->id]);
    unplacedPalletIn($north, ['product_id' => $gadgets->id]); // noise: right warehouse, wrong product
    unplacedPalletIn(Warehouse::firstOrCreateDefault(), ['product_id' => $widgets->id]); // noise: right product, wrong warehouse

    $ids = collect($this->getJson("/api/v1/pallets/unplaced?warehouse_ids[]={$north->id}&product_id[]={$widgets->id}")->assertOk()->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$match->id]);
});

test('the unplaced listing rejects an unknown warehouse', function () {
    actingAsMobileUser();

    $this->getJson('/api/v1/pallets/unplaced?warehouse_ids[]=999999')->assertUnprocessable()->assertJsonValidationErrors(['warehouse_ids.0']);
});

test('the unplaced listing paginates twenty per page and keeps its filters on the next link', function () {
    actingAsMobileUser();
    $product = Product::factory()->create();
    foreach (range(1, 25) as $_) {
        unplacedPalletIn(Warehouse::firstOrCreateDefault(), ['product_id' => $product->id]);
    }

    $response = $this->getJson("/api/v1/pallets/unplaced?product_id[]={$product->id}");

    assertJsonListingPaginates($response, 25);
    assertNextLinkKeepsQuery($response, 'product_id');
    expect($this->getJson("/api/v1/pallets/unplaced?product_id[]={$product->id}&page=2")->json('data'))->toHaveCount(5);
});

test('the unplaced listing names each pallets own warehouse without lazy loading', function () {
    actingAsMobileUser();
    $north = Warehouse::factory()->create(['name' => 'North']);
    $south = Warehouse::factory()->create(['name' => 'South']);
    $inNorth = unplacedPalletIn($north);
    $inSouth = unplacedPalletIn($south);

    $rows = collect($this->getJson('/api/v1/pallets/unplaced')->assertOk()->json('data'))->keyBy('id');

    expect($rows[$inNorth->id]['warehouse']['name'])->toBe('North')
        ->and($rows[$inSouth->id]['warehouse']['name'])->toBe('South');
});

test('the unplaced route is not captured by pallets/{pallet}', function () {
    actingAsMobileUser();
    $unplaced = unplacedPalletIn(Warehouse::firstOrCreateDefault());

    $listing = $this->getJson('/api/v1/pallets/unplaced');
    $single = $this->getJson("/api/v1/pallets/{$unplaced->id}");

    $listing->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
    $single->assertOk()->assertJsonPath('id', $unplaced->id)->assertJsonPath('placed', false);
    expect(route('api.v1.pallets.unplaced', [], false))->toBe('/api/v1/pallets/unplaced');
});

test('the unplaced listing answers a repeat request that already holds the current data with a 304', function () {
    actingAsMobileUser();
    unplacedPalletIn(Warehouse::firstOrCreateDefault());

    $etag = $this->getJson('/api/v1/pallets/unplaced')->assertOk()->headers->get('ETag');

    expect($etag)->not->toBeNull();
    $this->getJson('/api/v1/pallets/unplaced', ['If-None-Match' => $etag])->assertNotModified();
});

test('the unplaced listing is refused to a guest', function () {
    unplacedPalletIn(Warehouse::firstOrCreateDefault());

    $this->getJson('/api/v1/pallets/unplaced')->assertUnauthorized();
});

test('an unplaced pallet shown on its own carries placed false, a null state and location, and its warehouse and ETag', function () {
    actingAsMobileUser();
    $warehouse = Warehouse::factory()->create(['name' => 'Annex']);
    $pallet = unplacedPalletIn($warehouse);

    $response = $this->getJson("/api/v1/pallets/{$pallet->id}");

    $response->assertOk();
    expect($response->json('placed'))->toBeFalse()
        ->and($response->json('state'))->toBeNull()
        ->and($response->json('location'))->toBeNull()
        ->and($response->json('cell_entered_at'))->toBeNull()
        ->and($response->json('warehouse'))->toEqual(['id' => $warehouse->id, 'name' => 'Annex'])
        ->and($response->headers->get('ETag'))->not->toBeNull();
});

// --- The other pallet actions refuse an unplaced pallet ---------------------------------

test('open, remove-boxes, empty and transfer refuse an unplaced pallet with 409 pallet_not_placed and change nothing', function (string $action, array $payload) {
    actingAsMobileUser();
    $destination = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $pallet = unplacedPalletIn(Warehouse::firstOrCreateDefault(), ['remaining_boxes' => 6]);

    $this->postJson("/api/v1/pallets/{$pallet->id}/{$action}", $payload)
        ->assertConflict()
        ->assertExactJson(['message' => __('messages.pallet_not_placed'), 'error_code' => 'pallet_not_placed']);

    expect(Pallet::query()->count())->toBe(1)
        ->and($pallet->fresh()->cell_id)->toBeNull()
        ->and($pallet->fresh()->remaining_boxes)->toBe(6)
        ->and($destination->cells()->first()->state)->toBe(CellState::Empty)
        ->and(CellStatusLog::query()->count())->toBe(0);
})->with([
    'open' => ['open', []],
    'open with boxes' => ['open', ['boxes_count' => 2]],
    'remove-boxes' => ['remove-boxes', ['boxes_count' => 1]],
    'empty' => ['empty', []],
    'transfer' => ['transfer', ['to_row_letter' => 'A', 'to_cell_number' => 1, 'to_flat_number' => 1]],
]);

test('an unplaced pallet is refused before the If-Match precondition is looked at', function () {
    actingAsMobileUser();
    $pallet = unplacedPalletIn(Warehouse::firstOrCreateDefault());

    $this->postJson("/api/v1/pallets/{$pallet->id}/empty", [], ['If-Match' => '"not-the-etag"'])
        ->assertConflict()->assertJsonPath('error_code', 'pallet_not_placed');
});
