<?php

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;
use App\Models\User;

test('an authenticated worker can list cell logs with every property the app reads', function () {
    actingAsMobileUser();
    $mover = User::factory()->mobileUser()->create(['name' => 'Bob Mover']);

    $fromRow = Row::factory()->create(['letter' => 'A', 'cells_count' => 2, 'flats_count' => 2]);
    $toRow = Row::factory()->create(['letter' => 'B', 'cells_count' => 2, 'flats_count' => 2]);
    $fromCell = $fromRow->cells()->where('cell_number', 1)->where('flat_number', 2)->first();
    $toCell = $toRow->cells()->where('cell_number', 2)->where('flat_number', 1)->first();

    $product = Product::factory()->create([
        'name' => 'Widgets',
        'image_url' => 'https://cdn.example.com/widgets.png',
    ]);
    $pallet = Pallet::factory()->create([
        'product_id' => $product->id,
        'cell_id' => $toCell->id,
        'expiration_date' => '2026-09-01',
    ]);

    $log = CellStatusLog::factory()->create([
        'cell_id' => $fromCell->id,
        'related_cell_id' => $toCell->id,
        'action' => CellLogAction::TransferredOut,
        'from_state' => CellState::Full,
        'to_state' => CellState::Empty,
        'product_id' => $product->id,
        'pallet_id' => $pallet->id,
        'user_id' => $mover->id,
        'note' => 'Consolidating stock.',
    ]);

    $response = $this->getJson('/api/v1/cell-logs');

    $response->assertOk();
    expect($response->json('data.0'))->toEqual([
        'id' => $log->id,
        'action' => 'transferred_out',
        'from_state' => 'full',
        'to_state' => 'empty',
        'note' => 'Consolidating stock.',
        'cell' => [
            'row_letter' => 'A',
            'cell_number' => 1,
            'flat_number' => 2,
        ],
        'related_cell' => [
            'row_letter' => 'B',
            'cell_number' => 2,
            'flat_number' => 1,
        ],
        'product' => [
            'id' => $product->id,
            'name' => 'Widgets',
            'image_url' => 'https://cdn.example.com/widgets.png',
        ],
        'pallet' => [
            'id' => $pallet->id,
            'expiration_date' => '2026-09-01',
        ],
        'user' => [
            'id' => $mover->id,
            'name' => 'Bob Mover',
        ],
        'created_at' => $log->created_at->toIso8601String(),
    ]);
});

test('the cell log listing shows a pallet id with a null expiration date once the pallet has been emptied and deleted', function () {
    actingAsMobileUser();
    $pallet = Pallet::factory()->create();
    $palletId = $pallet->id;

    CellStatusLog::factory()->create([
        'action' => CellLogAction::Emptied,
        'pallet_id' => $palletId,
    ]);

    $pallet->delete();

    $response = $this->getJson('/api/v1/cell-logs');

    $response->assertOk();
    expect($response->json('data.0.pallet'))->toEqual([
        'id' => $palletId,
        'expiration_date' => null,
    ]);
});

test('the cell log listing paginates instead of returning everything at once', function () {
    actingAsMobileUser();

    CellStatusLog::factory()->count(25)->create();

    $response = $this->getJson('/api/v1/cell-logs');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(20);
    expect($response->json('meta.total'))->toBe(25);
});

test('an unauthenticated caller cannot list cell logs', function () {
    $response = $this->getJson('/api/v1/cell-logs');

    $response->assertUnauthorized();
});

test('the cell log listing can be filtered by product, excluding entries for other products', function () {
    actingAsMobileUser();
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();

    $matching = CellStatusLog::factory()->create(['product_id' => $product->id]);
    CellStatusLog::factory()->create(['product_id' => $otherProduct->id]);

    $response = $this->getJson("/api/v1/cell-logs?product_id={$product->id}");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
});

test('the cell log listing can be filtered by pallet, excluding entries for other pallets, even after the pallet is deleted', function () {
    actingAsMobileUser();
    $pallet = Pallet::factory()->create();
    $palletId = $pallet->id;
    $otherPallet = Pallet::factory()->create();

    $matching = CellStatusLog::factory()->create(['pallet_id' => $palletId]);
    CellStatusLog::factory()->create(['pallet_id' => $otherPallet->id]);

    $pallet->delete();

    $response = $this->getJson("/api/v1/cell-logs?pallet_id={$palletId}");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
});

test('the cell log listing can be filtered by row, excluding entries for other rows', function () {
    actingAsMobileUser();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $otherRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    $otherCell = $otherRow->cells()->first();

    $matching = CellStatusLog::factory()->create(['cell_id' => $cell->id]);
    CellStatusLog::factory()->create(['cell_id' => $otherCell->id]);

    $response = $this->getJson("/api/v1/cell-logs?row_id={$row->id}");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
});

test('the cell log listing can be filtered by column number, excluding entries for other columns', function () {
    actingAsMobileUser();
    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);
    $columnOneCell = $row->cells()->where('cell_number', 1)->first();
    $columnTwoCell = $row->cells()->where('cell_number', 2)->first();

    $matching = CellStatusLog::factory()->create(['cell_id' => $columnOneCell->id]);
    CellStatusLog::factory()->create(['cell_id' => $columnTwoCell->id]);

    $response = $this->getJson('/api/v1/cell-logs?column_number=1');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
});

test('the cell log listing can be filtered by who did it, excluding entries by other users', function () {
    actingAsMobileUser();
    $mover = User::factory()->create();
    $otherMover = User::factory()->create();

    $matching = CellStatusLog::factory()->create(['user_id' => $mover->id]);
    CellStatusLog::factory()->create(['user_id' => $otherMover->id]);

    $response = $this->getJson("/api/v1/cell-logs?user_id={$mover->id}");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
});

test('the cell log listing can be filtered by status change, excluding entries for other actions', function () {
    actingAsMobileUser();

    $matching = CellStatusLog::factory()->create(['action' => CellLogAction::Opened]);
    CellStatusLog::factory()->create(['action' => CellLogAction::Emptied]);

    $response = $this->getJson('/api/v1/cell-logs?action=opened');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
});

test('the cell log listing can be filtered by a date range, excluding entries outside it', function () {
    actingAsMobileUser();

    $matching = CellStatusLog::factory()->create();
    $matching->forceFill(['created_at' => '2026-06-15'])->save();

    $outOfRange = CellStatusLog::factory()->create();
    $outOfRange->forceFill(['created_at' => '2026-01-01'])->save();

    $response = $this->getJson('/api/v1/cell-logs?date_from=2026-06-01&date_to=2026-06-30');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
});

test('filtering cell logs with an invalid date range is rejected', function () {
    actingAsMobileUser();

    $response = $this->getJson('/api/v1/cell-logs?date_from=2026-06-30&date_to=2026-06-01');

    $response->assertUnprocessable();
});
