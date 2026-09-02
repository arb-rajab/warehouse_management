<?php

use App\Enums\CellLogAction;
use App\Enums\CellLogFlagReason;
use App\Enums\CellState;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;
use Illuminate\Support\Carbon;

test('an authenticated worker can add a pallet to an empty slot', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 2, 'flats_count' => 1]);
    $product = Product::factory()->create([
        'name' => 'Widgets',
        'image_url' => 'https://cdn.example.com/widgets.png',
        'boxes_count' => 10,
    ]);
    $cell = $row->cells()->where('cell_number', 1)->first();
    $expirationDate = now()->addMonth()->toDateString();

    $response = $this->postJson('/api/v1/pallets', [
        'row_letter' => $row->letter,
        'cell_number' => 1,
        'flat_number' => 1,
        'product_id' => $product->id,
        'expiration_date' => $expirationDate,
    ]);

    $response->assertCreated();

    $pallet = Pallet::query()->sole();

    expect($response->json())->toEqual([
        'id' => $pallet->id,
        'state' => 'full',
        'expiration_date' => $expirationDate,
        'remaining_boxes' => 10,
        'boxes_depleted_message' => null,
        'product' => [
            'id' => $product->id,
            'name' => 'Widgets',
            'image_url' => 'https://cdn.example.com/widgets.png',
            'boxes_count' => 10,
        ],
        'location' => [
            'row_letter' => 'Z',
            'cell_number' => 1,
            'flat_number' => 1,
        ],
    ]);

    $this->assertDatabaseCount('pallets', 1);
    expect($pallet->remaining_boxes)->toBe(10);
    expect($cell->refresh()->state)->toBe(CellState::Full);
});

test('adding a pallet logs the status change, with an optional note', function () {
    $user = actingAsMobileUser();

    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    $product = Product::factory()->create(['boxes_count' => 8]);

    $this->postJson('/api/v1/pallets', [
        'row_letter' => $row->letter,
        'cell_number' => 1,
        'flat_number' => 1,
        'product_id' => $product->id,
        'expiration_date' => now()->addMonth()->toDateString(),
        'note' => 'Arrived on the morning truck.',
    ])->assertCreated();

    $pallet = Pallet::query()->sole();

    $this->assertDatabaseHas('cell_status_logs', [
        'cell_id' => $cell->id,
        'action' => CellLogAction::Stored->value,
        'from_state' => CellState::Empty->value,
        'to_state' => CellState::Full->value,
        'product_id' => $product->id,
        'pallet_id' => $pallet->id,
        'boxes_count' => 8,
        'user_id' => $user->id,
        'note' => 'Arrived on the morning truck.',
    ]);
});

test('adding a pallet without a note logs a null note', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    $product = Product::factory()->create();

    $this->postJson('/api/v1/pallets', [
        'row_letter' => $row->letter,
        'cell_number' => 1,
        'flat_number' => 1,
        'product_id' => $product->id,
        'expiration_date' => now()->addMonth()->toDateString(),
    ])->assertCreated();

    expect(CellStatusLog::query()->where('cell_id', $cell->id)->sole()->note)->toBeNull();
});

test('adding a pallet to a non-empty slot is rejected and nothing changes', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    $existingPallet = Pallet::factory()->create(['cell_id' => $cell->id]);
    $product = Product::factory()->create();

    $response = $this->postJson('/api/v1/pallets', [
        'row_letter' => $row->letter,
        'cell_number' => 1,
        'flat_number' => 1,
        'product_id' => $product->id,
        'expiration_date' => now()->addMonth()->toDateString(),
    ]);

    $response->assertStatus(409)->assertJsonPath('error_code', 'slot_not_empty');

    $this->assertDatabaseCount('pallets', 1);
    expect($cell->refresh()->state)->toBe(CellState::Full);
    expect($existingPallet->id)->not->toBeNull();
});

test('adding a pallet with an unknown row letter is rejected and nothing changes', function () {
    actingAsMobileUser();

    $product = Product::factory()->create();

    $response = $this->postJson('/api/v1/pallets', [
        'row_letter' => 'ZZ',
        'cell_number' => 1,
        'flat_number' => 1,
        'product_id' => $product->id,
        'expiration_date' => now()->addMonth()->toDateString(),
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseCount('pallets', 0);
});

test('adding a pallet with an overly long row letter is rejected and nothing changes', function () {
    actingAsMobileUser();

    $product = Product::factory()->create();

    $response = $this->postJson('/api/v1/pallets', [
        'row_letter' => str_repeat('Z', 5),
        'cell_number' => 1,
        'flat_number' => 1,
        'product_id' => $product->id,
        'expiration_date' => now()->addMonth()->toDateString(),
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('row_letter');
    $this->assertDatabaseCount('pallets', 0);
});

test('adding a pallet with out-of-range coordinates is rejected and nothing changes', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $product = Product::factory()->create();

    $response = $this->postJson('/api/v1/pallets', [
        'row_letter' => $row->letter,
        'cell_number' => 99,
        'flat_number' => 1,
        'product_id' => $product->id,
        'expiration_date' => now()->addMonth()->toDateString(),
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseCount('pallets', 0);
});

test('adding a pallet with an unknown product is rejected and nothing changes', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);

    $response = $this->postJson('/api/v1/pallets', [
        'row_letter' => $row->letter,
        'cell_number' => 1,
        'flat_number' => 1,
        'product_id' => 999999,
        'expiration_date' => now()->addMonth()->toDateString(),
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseCount('pallets', 0);
});

test('adding a pallet with a past expiration date is rejected and nothing changes', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $product = Product::factory()->create();

    $response = $this->postJson('/api/v1/pallets', [
        'row_letter' => $row->letter,
        'cell_number' => 1,
        'flat_number' => 1,
        'product_id' => $product->id,
        'expiration_date' => now()->subDay()->toDateString(),
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseCount('pallets', 0);
});

test('an unauthenticated caller cannot add a pallet and nothing changes', function () {
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $product = Product::factory()->create();

    $response = $this->postJson('/api/v1/pallets', [
        'row_letter' => $row->letter,
        'cell_number' => 1,
        'flat_number' => 1,
        'product_id' => $product->id,
        'expiration_date' => now()->addMonth()->toDateString(),
    ]);

    $response->assertUnauthorized();
    $this->assertDatabaseCount('pallets', 0);
});

test('an authenticated worker can view a pallet with every property the app reads', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 2, 'flats_count' => 2]);
    $cell = $row->cells()->where('cell_number', 2)->where('flat_number', 1)->first();
    $product = Product::factory()->create([
        'name' => 'Widgets',
        'image_url' => 'https://cdn.example.com/widgets.png',
        'boxes_count' => 10,
    ]);
    $pallet = Pallet::factory()->create(['cell_id' => $cell->id, 'product_id' => $product->id]);

    $response = $this->getJson("/api/v1/pallets/{$pallet->id}");

    $response->assertOk();
    expect($response->json())->toEqual([
        'id' => $pallet->id,
        'state' => 'full',
        'expiration_date' => $pallet->expiration_date->toDateString(),
        'remaining_boxes' => 10,
        'boxes_depleted_message' => null,
        'product' => [
            'id' => $product->id,
            'name' => 'Widgets',
            'image_url' => 'https://cdn.example.com/widgets.png',
            'boxes_count' => 10,
        ],
        'location' => [
            'row_letter' => 'Z',
            'cell_number' => 2,
            'flat_number' => 1,
        ],
    ]);
});

test('viewing an unknown pallet returns 404', function () {
    actingAsMobileUser();

    $response = $this->getJson('/api/v1/pallets/999999');

    $response->assertNotFound();
});

test('an unauthenticated caller cannot view a pallet', function () {
    $pallet = Pallet::factory()->create();

    $response = $this->getJson("/api/v1/pallets/{$pallet->id}");

    $response->assertUnauthorized();
});

test('an authenticated worker can open a full pallet, removing boxes at the same time', function () {
    actingAsMobileUser();

    $product = Product::factory()->create(['boxes_count' => 10]);
    $pallet = Pallet::factory()->create(['product_id' => $product->id]);

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/open", [
        'boxes_count' => 3,
    ]);

    $response->assertOk()->assertJsonPath('state', 'opened');
    $response->assertJsonPath('remaining_boxes', 7);
    $response->assertJsonPath('boxes_depleted_message', null);
    expect($pallet->cell->refresh()->state)->toBe(CellState::Opened);
    expect($pallet->refresh()->remaining_boxes)->toBe(7);
});

test('opening a pallet that takes the last boxes returns a boxes_depleted_message', function () {
    actingAsMobileUser();

    $product = Product::factory()->create(['boxes_count' => 1]);
    $pallet = Pallet::factory()->create(['product_id' => $product->id]);

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/open", [
        'boxes_count' => 1,
    ]);

    $response->assertOk()->assertJsonPath('remaining_boxes', 0);
    $response->assertJsonPath('boxes_depleted_message', __('messages.pallet_boxes_depleted'));
});

test('opening a pallet logs the status change, with an optional note', function () {
    $user = actingAsMobileUser();

    $product = Product::factory()->create(['boxes_count' => 10]);
    $pallet = Pallet::factory()->create(['product_id' => $product->id]);

    $this->postJson("/api/v1/pallets/{$pallet->id}/open", [
        'boxes_count' => 4,
        'note' => 'Checked for damage before opening.',
    ])->assertOk();

    $this->assertDatabaseHas('cell_status_logs', [
        'cell_id' => $pallet->cell_id,
        'action' => CellLogAction::Opened->value,
        'from_state' => CellState::Full->value,
        'to_state' => CellState::Opened->value,
        'product_id' => $pallet->product_id,
        'pallet_id' => $pallet->id,
        'boxes_count' => 6,
        'user_id' => $user->id,
        'note' => 'Checked for damage before opening.',
    ]);
});

test('opening a pallet without a note logs a null note', function () {
    actingAsMobileUser();

    $pallet = Pallet::factory()->create();

    $this->postJson("/api/v1/pallets/{$pallet->id}/open", [
        'boxes_count' => 1,
    ])->assertOk();

    expect(CellStatusLog::query()->where('cell_id', $pallet->cell_id)->sole()->note)->toBeNull();
});

test('opening a pallet without a boxes_count is rejected', function () {
    actingAsMobileUser();

    $pallet = Pallet::factory()->create();

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/open");

    $response->assertStatus(422)->assertJsonValidationErrors('boxes_count');
    expect($pallet->cell->refresh()->state)->toBe(CellState::Full);
});

test('opening a pallet with more boxes than remain is rejected and nothing changes', function () {
    actingAsMobileUser();

    $product = Product::factory()->create(['boxes_count' => 5]);
    $pallet = Pallet::factory()->create(['product_id' => $product->id]);

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/open", [
        'boxes_count' => 6,
    ]);

    $response->assertStatus(409)->assertJsonPath('error_code', 'insufficient_boxes_remaining');
    expect($pallet->cell->refresh()->state)->toBe(CellState::Full);
    expect($pallet->refresh()->remaining_boxes)->toBe(5);
    $this->assertDatabaseCount('cell_status_logs', 0);
});

test('opening a pallet with more boxes than remain, with confirm_empty, empties the pallet instead', function () {
    $user = actingAsMobileUser();

    $product = Product::factory()->create(['boxes_count' => 5]);
    $pallet = Pallet::factory()->create(['product_id' => $product->id]);
    $cell = $pallet->cell;

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/open", [
        'boxes_count' => 6,
        'confirm_empty' => true,
        'note' => 'Damaged in transit.',
    ]);

    $response->assertNoContent();
    $this->assertDatabaseMissing('pallets', ['id' => $pallet->id]);
    expect($cell->refresh()->state)->toBe(CellState::Empty);

    $this->assertDatabaseHas('cell_status_logs', [
        'cell_id' => $cell->id,
        'action' => CellLogAction::Emptied->value,
        'from_state' => CellState::Full->value,
        'to_state' => CellState::Empty->value,
        'product_id' => $product->id,
        'pallet_id' => $pallet->id,
        'boxes_count' => 5,
        'user_id' => $user->id,
        'note' => 'Damaged in transit.',
    ]);
});

test('opening a pallet with confirm_empty but a sufficient boxes_count still just opens it', function () {
    actingAsMobileUser();

    $product = Product::factory()->create(['boxes_count' => 5]);
    $pallet = Pallet::factory()->create(['product_id' => $product->id]);

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/open", [
        'boxes_count' => 3,
        'confirm_empty' => true,
    ]);

    $response->assertOk()->assertJsonPath('state', 'opened');
    $response->assertJsonPath('remaining_boxes', 2);
    $this->assertDatabaseHas('pallets', ['id' => $pallet->id]);
    expect($pallet->cell->refresh()->state)->toBe(CellState::Opened);
});

test('opening an already-opened pallet is rejected and nothing changes', function () {
    actingAsMobileUser();

    $pallet = Pallet::factory()->opened()->create();

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/open", [
        'boxes_count' => 1,
    ]);

    $response->assertStatus(409)->assertJsonPath('error_code', 'pallet_not_full');
    expect($pallet->cell->refresh()->state)->toBe(CellState::Opened);
    expect($pallet->refresh()->remaining_boxes)->toBe($pallet->product->boxes_count);
});

test('opening an unknown pallet returns 404', function () {
    actingAsMobileUser();

    $response = $this->postJson('/api/v1/pallets/999999/open', [
        'boxes_count' => 1,
    ]);

    $response->assertNotFound();
});

test('an unauthenticated caller cannot open a pallet and nothing changes', function () {
    $pallet = Pallet::factory()->create();

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/open", [
        'boxes_count' => 1,
    ]);

    $response->assertUnauthorized();
    expect($pallet->cell->refresh()->state)->toBe(CellState::Full);
});

test('an authenticated worker can remove more boxes from an already-opened pallet', function () {
    actingAsMobileUser();

    $product = Product::factory()->create(['boxes_count' => 10]);
    $pallet = Pallet::factory()->create(['product_id' => $product->id, 'remaining_boxes' => 6]);
    $pallet->cell->update(['state' => CellState::Opened]);

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/remove-boxes", [
        'boxes_count' => 4,
    ]);

    $response->assertOk()->assertJsonPath('remaining_boxes', 2);
    expect($pallet->refresh()->remaining_boxes)->toBe(2);
});

test('removing the last boxes from an opened pallet returns a boxes_depleted_message', function () {
    actingAsMobileUser();

    $product = Product::factory()->create(['boxes_count' => 10]);
    $pallet = Pallet::factory()->create(['product_id' => $product->id, 'remaining_boxes' => 2]);
    $pallet->cell->update(['state' => CellState::Opened]);

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/remove-boxes", [
        'boxes_count' => 2,
    ]);

    $response->assertOk()->assertJsonPath('remaining_boxes', 0);
    $response->assertJsonPath('boxes_depleted_message', __('messages.pallet_boxes_depleted'));
});

test('removing boxes logs the status change, with an optional note', function () {
    $user = actingAsMobileUser();

    $product = Product::factory()->create(['boxes_count' => 10]);
    $pallet = Pallet::factory()->create(['product_id' => $product->id, 'remaining_boxes' => 6]);
    $pallet->cell->update(['state' => CellState::Opened]);

    $this->postJson("/api/v1/pallets/{$pallet->id}/remove-boxes", [
        'boxes_count' => 4,
        'note' => 'Pulled for the morning order.',
    ])->assertOk();

    $this->assertDatabaseHas('cell_status_logs', [
        'cell_id' => $pallet->cell_id,
        'action' => CellLogAction::BoxesRemoved->value,
        'from_state' => CellState::Opened->value,
        'to_state' => CellState::Opened->value,
        'product_id' => $pallet->product_id,
        'pallet_id' => $pallet->id,
        'boxes_count' => 2,
        'user_id' => $user->id,
        'note' => 'Pulled for the morning order.',
    ]);
});

test('removing boxes from a full (not yet opened) pallet is rejected and nothing changes', function () {
    actingAsMobileUser();

    $pallet = Pallet::factory()->create();

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/remove-boxes", [
        'boxes_count' => 1,
    ]);

    $response->assertStatus(409)->assertJsonPath('error_code', 'pallet_not_opened');
    expect($pallet->refresh()->remaining_boxes)->toBe($pallet->product->boxes_count);
    $this->assertDatabaseCount('cell_status_logs', 0);
});

test('removing more boxes than remain is rejected and nothing changes', function () {
    actingAsMobileUser();

    $product = Product::factory()->create(['boxes_count' => 10]);
    $pallet = Pallet::factory()->create(['product_id' => $product->id, 'remaining_boxes' => 3]);
    $pallet->cell->update(['state' => CellState::Opened]);

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/remove-boxes", [
        'boxes_count' => 4,
    ]);

    $response->assertStatus(409)->assertJsonPath('error_code', 'insufficient_boxes_remaining');
    expect($pallet->refresh()->remaining_boxes)->toBe(3);
    $this->assertDatabaseCount('cell_status_logs', 0);
});

test('removing more boxes than remain, with confirm_empty, empties the pallet instead', function () {
    $user = actingAsMobileUser();

    $product = Product::factory()->create(['boxes_count' => 10]);
    $pallet = Pallet::factory()->create(['product_id' => $product->id, 'remaining_boxes' => 3]);
    $cell = $pallet->cell;
    $cell->update(['state' => CellState::Opened]);

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/remove-boxes", [
        'boxes_count' => 4,
        'confirm_empty' => true,
        'note' => 'Nothing worth keeping left.',
    ]);

    $response->assertNoContent();
    $this->assertDatabaseMissing('pallets', ['id' => $pallet->id]);
    expect($cell->refresh()->state)->toBe(CellState::Empty);

    $this->assertDatabaseHas('cell_status_logs', [
        'cell_id' => $cell->id,
        'action' => CellLogAction::Emptied->value,
        'from_state' => CellState::Opened->value,
        'to_state' => CellState::Empty->value,
        'product_id' => $product->id,
        'pallet_id' => $pallet->id,
        'boxes_count' => 3,
        'user_id' => $user->id,
        'note' => 'Nothing worth keeping left.',
    ]);
});

test('removing boxes with confirm_empty but a sufficient boxes_count still just removes them', function () {
    actingAsMobileUser();

    $product = Product::factory()->create(['boxes_count' => 10]);
    $pallet = Pallet::factory()->create(['product_id' => $product->id, 'remaining_boxes' => 6]);
    $pallet->cell->update(['state' => CellState::Opened]);

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/remove-boxes", [
        'boxes_count' => 4,
        'confirm_empty' => true,
    ]);

    $response->assertOk()->assertJsonPath('remaining_boxes', 2);
    $this->assertDatabaseHas('pallets', ['id' => $pallet->id]);
    expect($pallet->cell->refresh()->state)->toBe(CellState::Opened);
});

test('removing boxes without a boxes_count is rejected', function () {
    actingAsMobileUser();

    $pallet = Pallet::factory()->opened()->create();

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/remove-boxes");

    $response->assertStatus(422)->assertJsonValidationErrors('boxes_count');
});

test('removing boxes from an unknown pallet returns 404', function () {
    actingAsMobileUser();

    $response = $this->postJson('/api/v1/pallets/999999/remove-boxes', [
        'boxes_count' => 1,
    ]);

    $response->assertNotFound();
});

test('an unauthenticated caller cannot remove boxes and nothing changes', function () {
    $pallet = Pallet::factory()->opened()->create();

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/remove-boxes", [
        'boxes_count' => 1,
    ]);

    $response->assertUnauthorized();
    expect($pallet->refresh()->remaining_boxes)->toBe($pallet->product->boxes_count);
});

test('an authenticated worker can empty an opened pallet', function () {
    actingAsMobileUser();

    $pallet = Pallet::factory()->opened()->create();
    $cell = $pallet->cell;

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/empty");

    $response->assertNoContent();
    $this->assertDatabaseMissing('pallets', ['id' => $pallet->id]);
    expect($cell->refresh()->state)->toBe(CellState::Empty);
});

test('an authenticated worker can empty a full pallet', function () {
    actingAsMobileUser();

    $pallet = Pallet::factory()->create();
    $cell = $pallet->cell;

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/empty");

    $response->assertNoContent();
    $this->assertDatabaseMissing('pallets', ['id' => $pallet->id]);
    expect($cell->refresh()->state)->toBe(CellState::Empty);
});

test('emptying a pallet logs the status change, with an optional note', function () {
    $user = actingAsMobileUser();

    $product = Product::factory()->create(['boxes_count' => 10]);
    $pallet = Pallet::factory()->create(['product_id' => $product->id, 'remaining_boxes' => 4]);
    $cell = $pallet->cell;
    $productId = $pallet->product_id;
    $palletId = $pallet->id;

    $this->postJson("/api/v1/pallets/{$pallet->id}/empty", [
        'note' => 'Product was expired.',
    ])->assertNoContent();

    $this->assertDatabaseHas('cell_status_logs', [
        'cell_id' => $cell->id,
        'action' => CellLogAction::Emptied->value,
        'from_state' => CellState::Full->value,
        'to_state' => CellState::Empty->value,
        'product_id' => $productId,
        'pallet_id' => $palletId,
        'boxes_count' => 4,
        'user_id' => $user->id,
        'note' => 'Product was expired.',
    ]);
});

test('emptying a pallet without a note logs a null note', function () {
    actingAsMobileUser();

    $pallet = Pallet::factory()->create();
    $cell = $pallet->cell;

    $this->postJson("/api/v1/pallets/{$pallet->id}/empty")->assertNoContent();

    expect(CellStatusLog::query()->where('cell_id', $cell->id)->sole()->note)->toBeNull();
});

test('emptying an unknown pallet returns 404', function () {
    actingAsMobileUser();

    $response = $this->postJson('/api/v1/pallets/999999/empty');

    $response->assertNotFound();
});

test('an unauthenticated caller cannot empty a pallet and nothing changes', function () {
    $pallet = Pallet::factory()->opened()->create();

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/empty");

    $response->assertUnauthorized();
    $this->assertDatabaseHas('pallets', ['id' => $pallet->id]);
    expect($pallet->cell->refresh()->state)->toBe(CellState::Opened);
});

test('an authenticated worker can transfer a full pallet to an empty slot', function () {
    $user = actingAsMobileUser();

    $sourceRow = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $sourceCell = $sourceRow->cells()->first();
    $product = Product::factory()->create(['boxes_count' => 10]);
    $pallet = Pallet::factory()->create(['cell_id' => $sourceCell->id, 'product_id' => $product->id, 'remaining_boxes' => 7]);

    $destinationRow = Row::factory()->create(['letter' => 'B', 'cells_count' => 1, 'flats_count' => 1]);
    $destinationCell = $destinationRow->cells()->first();

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/transfer", [
        'to_row_letter' => $destinationRow->letter,
        'to_cell_number' => 1,
        'to_flat_number' => 1,
        'note' => 'Consolidating widgets onto row B.',
    ]);

    $response->assertOk()->assertJsonPath('state', 'full');

    expect($response->json('location'))->toEqual([
        'row_letter' => 'B',
        'cell_number' => 1,
        'flat_number' => 1,
    ]);

    expect($sourceCell->refresh()->state)->toBe(CellState::Empty);
    expect($destinationCell->refresh()->state)->toBe(CellState::Full);
    expect($pallet->refresh()->cell_id)->toBe($destinationCell->id);
    expect($pallet->remaining_boxes)->toBe(7);

    $this->assertDatabaseHas('cell_status_logs', [
        'cell_id' => $sourceCell->id,
        'related_cell_id' => $destinationCell->id,
        'action' => CellLogAction::TransferredOut->value,
        'from_state' => CellState::Full->value,
        'to_state' => CellState::Empty->value,
        'product_id' => $pallet->product_id,
        'pallet_id' => $pallet->id,
        'boxes_count' => 7,
        'user_id' => $user->id,
        'note' => 'Consolidating widgets onto row B.',
    ]);
    $this->assertDatabaseHas('cell_status_logs', [
        'cell_id' => $destinationCell->id,
        'related_cell_id' => $sourceCell->id,
        'action' => CellLogAction::TransferredIn->value,
        'from_state' => CellState::Empty->value,
        'to_state' => CellState::Full->value,
        'product_id' => $pallet->product_id,
        'pallet_id' => $pallet->id,
        'boxes_count' => 7,
        'user_id' => $user->id,
        'note' => 'Consolidating widgets onto row B.',
    ]);
});

test('transferring a pallet without a note logs a null note on both sides', function () {
    actingAsMobileUser();

    $sourceRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $sourceCell = $sourceRow->cells()->first();
    $pallet = Pallet::factory()->create(['cell_id' => $sourceCell->id]);

    $destinationRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $destinationCell = $destinationRow->cells()->first();

    $this->postJson("/api/v1/pallets/{$pallet->id}/transfer", [
        'to_row_letter' => $destinationRow->letter,
        'to_cell_number' => 1,
        'to_flat_number' => 1,
    ])->assertOk();

    $notes = CellStatusLog::query()->where('cell_id', $sourceCell->id)
        ->orWhere('cell_id', $destinationCell->id)
        ->pluck('note');

    expect($notes)->toHaveCount(2);
    expect($notes->filter()->isEmpty())->toBeTrue();
});

test('transferring an opened pallet leaves the destination opened, not full', function () {
    actingAsMobileUser();

    $sourceRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $sourceCell = $sourceRow->cells()->first();
    $pallet = Pallet::factory()->opened()->create(['cell_id' => $sourceCell->id]);

    $destinationRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $destinationCell = $destinationRow->cells()->first();

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/transfer", [
        'to_row_letter' => $destinationRow->letter,
        'to_cell_number' => 1,
        'to_flat_number' => 1,
    ]);

    $response->assertOk()->assertJsonPath('state', 'opened');
    expect($destinationCell->refresh()->state)->toBe(CellState::Opened);
    expect($sourceCell->refresh()->state)->toBe(CellState::Empty);
});

test('transferring to a non-empty destination is rejected and nothing changes', function () {
    actingAsMobileUser();

    $sourceRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $sourceCell = $sourceRow->cells()->first();
    $pallet = Pallet::factory()->create(['cell_id' => $sourceCell->id]);

    $destinationRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $destinationCell = $destinationRow->cells()->first();
    $destinationPallet = Pallet::factory()->create(['cell_id' => $destinationCell->id]);

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/transfer", [
        'to_row_letter' => $destinationRow->letter,
        'to_cell_number' => 1,
        'to_flat_number' => 1,
    ]);

    $response->assertStatus(409)->assertJsonPath('error_code', 'destination_not_empty');

    expect($pallet->refresh()->cell_id)->toBe($sourceCell->id);
    expect($sourceCell->refresh()->state)->toBe(CellState::Full);
    expect($destinationCell->refresh()->state)->toBe(CellState::Full);
    $this->assertDatabaseCount('cell_status_logs', 0);
    expect($destinationPallet->id)->not->toBeNull();
});

test('transferring a pallet to its own current slot is rejected and nothing changes', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    $pallet = Pallet::factory()->create(['cell_id' => $cell->id]);

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/transfer", [
        'to_row_letter' => $row->letter,
        'to_cell_number' => 1,
        'to_flat_number' => 1,
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseCount('cell_status_logs', 0);
    expect($cell->refresh()->state)->toBe(CellState::Full);
});

test('transferring to out-of-range destination coordinates is rejected and nothing changes', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    $pallet = Pallet::factory()->create(['cell_id' => $cell->id]);

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/transfer", [
        'to_row_letter' => $row->letter,
        'to_cell_number' => 99,
        'to_flat_number' => 1,
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseCount('cell_status_logs', 0);
});

test('transferring to an unknown destination row letter is rejected and nothing changes', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    $pallet = Pallet::factory()->create(['cell_id' => $cell->id]);

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/transfer", [
        'to_row_letter' => 'ZZ',
        'to_cell_number' => 1,
        'to_flat_number' => 1,
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('to_row_letter');
    $this->assertDatabaseCount('cell_status_logs', 0);
    expect($pallet->refresh()->cell_id)->toBe($cell->id);
});

test('an unauthenticated caller cannot transfer a pallet and nothing changes', function () {
    $sourceRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $sourceCell = $sourceRow->cells()->first();
    $pallet = Pallet::factory()->create(['cell_id' => $sourceCell->id]);

    $destinationRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/transfer", [
        'to_row_letter' => $destinationRow->letter,
        'to_cell_number' => 1,
        'to_flat_number' => 1,
    ]);

    $response->assertUnauthorized();
    expect($pallet->refresh()->cell_id)->toBe($sourceCell->id);
    $this->assertDatabaseCount('cell_status_logs', 0);
});

test('a pallet transfer only logs its own two cells, not another pallets', function () {
    actingAsMobileUser();

    $sourceRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $sourceCell = $sourceRow->cells()->first();
    $pallet = Pallet::factory()->create(['cell_id' => $sourceCell->id]);

    CellStatusLog::factory()->create();

    $destinationRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $destinationCell = $destinationRow->cells()->first();

    $this->postJson("/api/v1/pallets/{$pallet->id}/transfer", [
        'to_row_letter' => $destinationRow->letter,
        'to_cell_number' => 1,
        'to_flat_number' => 1,
    ])->assertOk();

    $logs = CellStatusLog::query()->where('cell_id', $sourceCell->id)
        ->orWhere('cell_id', $destinationCell->id)
        ->get();

    expect($logs)->toHaveCount(2);
    expect($logs->firstWhere('cell_id', $sourceCell->id)->related_cell_id)->toBe($destinationCell->id);
    expect($logs->firstWhere('cell_id', $destinationCell->id)->related_cell_id)->toBe($sourceCell->id);
});

test('storing and then quickly emptying a pallet in the same cell flags the emptied log as a quick flip', function () {
    Carbon::setTestNow('2026-08-01 12:00:00');
    actingAsMobileUser();

    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $product = Product::factory()->create();

    $storeResponse = $this->postJson('/api/v1/pallets', [
        'row_letter' => $row->letter,
        'cell_number' => 1,
        'flat_number' => 1,
        'product_id' => $product->id,
        'expiration_date' => now()->addMonth()->toDateString(),
    ])->assertCreated();

    Carbon::setTestNow('2026-08-01 12:00:30');
    $this->postJson("/api/v1/pallets/{$storeResponse->json('id')}/empty")->assertNoContent();

    $emptiedLog = CellStatusLog::query()->where('action', CellLogAction::Emptied->value)->sole();

    expect($emptiedLog->flags()->where('reason', CellLogFlagReason::QuickFlip->value)->exists())->toBeTrue();

    Carbon::setTestNow();
});

test('performing a pallet action outside working hours flags it as off-hours', function () {
    Carbon::setTestNow('2026-08-01 23:00:00');
    actingAsMobileUser();

    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $product = Product::factory()->create();

    $this->postJson('/api/v1/pallets', [
        'row_letter' => $row->letter,
        'cell_number' => 1,
        'flat_number' => 1,
        'product_id' => $product->id,
        'expiration_date' => now()->addMonth()->toDateString(),
    ])->assertCreated();

    $log = CellStatusLog::query()->sole();

    expect($log->flags()->where('reason', CellLogFlagReason::OffHours->value)->exists())->toBeTrue();

    Carbon::setTestNow();
});

test('performing many pallet actions quickly as the same user flags rapid actions past the configured threshold', function () {
    Carbon::setTestNow('2026-08-01 10:00:00');
    actingAsMobileUser();

    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $product = Product::factory()->create();

    for ($i = 0; $i < 6; $i++) {
        Carbon::setTestNow(now()->addSeconds(10));
        $storeResponse = $this->postJson('/api/v1/pallets', [
            'row_letter' => $row->letter,
            'cell_number' => 1,
            'flat_number' => 1,
            'product_id' => $product->id,
            'expiration_date' => now()->addMonth()->toDateString(),
        ])->assertCreated();

        Carbon::setTestNow(now()->addSeconds(10));
        $this->postJson("/api/v1/pallets/{$storeResponse->json('id')}/empty")->assertNoContent();
    }

    $logs = CellStatusLog::query()->orderBy('id')->get();
    expect($logs)->toHaveCount(12);

    expect($logs[9]->flags()->where('reason', CellLogFlagReason::RapidActions->value)->exists())->toBeFalse();
    expect($logs[10]->flags()->where('reason', CellLogFlagReason::RapidActions->value)->exists())->toBeTrue();
    expect($logs[11]->flags()->where('reason', CellLogFlagReason::RapidActions->value)->exists())->toBeTrue();

    Carbon::setTestNow();
});

test('adding a pallet to an inactive slot is rejected and nothing changes', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    $cell->update(['is_active' => false]);
    $product = Product::factory()->create();

    $response = $this->postJson('/api/v1/pallets', [
        'row_letter' => $row->letter,
        'cell_number' => 1,
        'flat_number' => 1,
        'product_id' => $product->id,
        'expiration_date' => now()->addMonth()->toDateString(),
    ]);

    $response->assertStatus(409)->assertJsonPath('error_code', 'slot_inactive');

    $this->assertDatabaseCount('pallets', 0);
    expect($cell->refresh()->state)->toBe(CellState::Empty);
});

test('opening a pallet in an inactive cell is rejected and nothing changes', function () {
    actingAsMobileUser();

    $pallet = Pallet::factory()->create(['remaining_boxes' => 10]);
    $pallet->cell->update(['is_active' => false]);

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/open", [
        'boxes_count' => 3,
    ]);

    $response->assertStatus(409)->assertJsonPath('error_code', 'slot_inactive');

    expect($pallet->cell->refresh()->state)->toBe(CellState::Full);
    expect($pallet->refresh()->remaining_boxes)->toBe(10);
});

test('removing boxes from a pallet in an inactive cell is rejected and nothing changes', function () {
    actingAsMobileUser();

    $pallet = Pallet::factory()->opened()->create(['remaining_boxes' => 10]);
    $pallet->cell->update(['is_active' => false]);

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/remove-boxes", [
        'boxes_count' => 3,
    ]);

    $response->assertStatus(409)->assertJsonPath('error_code', 'slot_inactive');

    expect($pallet->refresh()->remaining_boxes)->toBe(10);
});

test('emptying a pallet in an inactive cell is rejected and nothing changes', function () {
    actingAsMobileUser();

    $pallet = Pallet::factory()->create();
    $pallet->cell->update(['is_active' => false]);

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/empty");

    $response->assertStatus(409)->assertJsonPath('error_code', 'slot_inactive');

    $this->assertDatabaseHas('pallets', ['id' => $pallet->id]);
    expect($pallet->cell->refresh()->state)->toBe(CellState::Full);
});

test('transferring a pallet out of an inactive source cell is rejected and nothing changes', function () {
    actingAsMobileUser();

    $sourceRow = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $sourceCell = $sourceRow->cells()->first();
    $pallet = Pallet::factory()->create(['cell_id' => $sourceCell->id]);
    $sourceCell->update(['is_active' => false]);

    $destinationRow = Row::factory()->create(['letter' => 'B', 'cells_count' => 1, 'flats_count' => 1]);

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/transfer", [
        'to_row_letter' => $destinationRow->letter,
        'to_cell_number' => 1,
        'to_flat_number' => 1,
    ]);

    $response->assertStatus(409)->assertJsonPath('error_code', 'slot_inactive');

    expect($pallet->refresh()->cell_id)->toBe($sourceCell->id);
});

test('transferring a pallet into an inactive destination cell is rejected and nothing changes', function () {
    actingAsMobileUser();

    $sourceRow = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $sourceCell = $sourceRow->cells()->first();
    $pallet = Pallet::factory()->create(['cell_id' => $sourceCell->id]);

    $destinationRow = Row::factory()->create(['letter' => 'B', 'cells_count' => 1, 'flats_count' => 1]);
    $destinationCell = $destinationRow->cells()->first();
    $destinationCell->update(['is_active' => false]);

    $response = $this->postJson("/api/v1/pallets/{$pallet->id}/transfer", [
        'to_row_letter' => $destinationRow->letter,
        'to_cell_number' => 1,
        'to_flat_number' => 1,
    ]);

    $response->assertStatus(409)->assertJsonPath('error_code', 'slot_inactive');

    expect($pallet->refresh()->cell_id)->toBe($sourceCell->id);
});
