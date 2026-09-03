<?php

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;

test('an authenticated admin can store a pallet into an empty cell', function () {
    $admin = actingAsAdmin();

    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    $product = Product::factory()->create(['boxes_count' => 10]);
    $expirationDate = now()->addMonth()->toDateString();

    $response = $this->post("/admin/cells/{$cell->id}/pallet", [
        'product_id' => $product->id,
        'expiration_date' => $expirationDate,
        'note' => 'Admin backfill.',
    ]);

    $response->assertRedirect(route('admin.cells.index'));

    $pallet = Pallet::query()->sole();
    expect($pallet->cell_id)->toBe($cell->id);
    expect($pallet->remaining_boxes)->toBe(10);
    expect($cell->refresh()->state)->toBe(CellState::Full);

    $this->assertDatabaseHas('cell_status_logs', [
        'cell_id' => $cell->id,
        'action' => CellLogAction::Stored->value,
        'from_state' => CellState::Empty->value,
        'to_state' => CellState::Full->value,
        'product_id' => $product->id,
        'pallet_id' => $pallet->id,
        'boxes_count' => 10,
        'user_id' => $admin->id,
        'note' => 'Admin backfill.',
    ]);
});

test('storing a pallet preserves the current map query on redirect', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 2]);
    $cell = $row->cells()->where('flat_number', 2)->first();
    $product = Product::factory()->create();

    $response = $this->post("/admin/cells/{$cell->id}/pallet?flat_number=2", [
        'product_id' => $product->id,
        'expiration_date' => now()->addMonth()->toDateString(),
    ]);

    $response->assertRedirect('/admin/cells?flat_number=2');
});

test('storing a pallet from a row page redirects back to that row page instead of the cell map', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    $product = Product::factory()->create();

    $response = $this->from("/admin/rows/{$row->letter}")->post("/admin/cells/{$cell->id}/pallet", [
        'product_id' => $product->id,
        'expiration_date' => now()->addMonth()->toDateString(),
    ]);

    $response->assertRedirect("/admin/rows/{$row->letter}");
});

test('storing a pallet into a non-empty cell is rejected with a non-field action error and nothing changes', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    $existingPallet = Pallet::factory()->create(['cell_id' => $cell->id]);
    $product = Product::factory()->create();

    $response = $this->post("/admin/cells/{$cell->id}/pallet", [
        'product_id' => $product->id,
        'expiration_date' => now()->addMonth()->toDateString(),
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['action' => __('messages.slot_not_empty')]);

    $this->assertDatabaseCount('pallets', 1);
    expect($existingPallet->id)->not->toBeNull();
});

test('storing a pallet into an inactive cell is rejected with a non-field action error', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    $cell->update(['is_active' => false]);
    $product = Product::factory()->create();

    $response = $this->post("/admin/cells/{$cell->id}/pallet", [
        'product_id' => $product->id,
        'expiration_date' => now()->addMonth()->toDateString(),
    ]);

    $response->assertSessionHasErrors(['action' => __('messages.slot_inactive')]);
    $this->assertDatabaseCount('pallets', 0);
});

test('a mobile app user cannot store a pallet via the admin route', function () {
    actingAsMobilePanelUser();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    $product = Product::factory()->create();

    $response = $this->post("/admin/cells/{$cell->id}/pallet", [
        'product_id' => $product->id,
        'expiration_date' => now()->addMonth()->toDateString(),
    ]);

    $response->assertForbidden();
    $this->assertDatabaseCount('pallets', 0);
});

test('an unauthenticated caller cannot store a pallet and nothing changes', function () {
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    $product = Product::factory()->create();

    $response = $this->post("/admin/cells/{$cell->id}/pallet", [
        'product_id' => $product->id,
        'expiration_date' => now()->addMonth()->toDateString(),
    ]);

    $response->assertRedirect(route('login'));
    $this->assertDatabaseCount('pallets', 0);
});

test('an authenticated admin can open a full pallet, removing boxes at the same time', function () {
    $admin = actingAsAdmin();
    $product = Product::factory()->create(['boxes_count' => 10]);
    $pallet = Pallet::factory()->create(['product_id' => $product->id]);

    $response = $this->post("/admin/pallets/{$pallet->id}/open", ['boxes_count' => 3]);

    $response->assertRedirect(route('admin.cells.index'));
    expect($pallet->cell->refresh()->state)->toBe(CellState::Opened);
    expect($pallet->refresh()->remaining_boxes)->toBe(7);

    $this->assertDatabaseHas('cell_status_logs', [
        'action' => CellLogAction::Opened->value,
        'pallet_id' => $pallet->id,
        'boxes_count' => 7,
        'user_id' => $admin->id,
    ]);
});

test('opening a pallet with more boxes than remain, with confirm_empty, empties the pallet instead', function () {
    actingAsAdmin();
    $product = Product::factory()->create(['boxes_count' => 5]);
    $pallet = Pallet::factory()->create(['product_id' => $product->id]);
    $cell = $pallet->cell;

    $response = $this->post("/admin/pallets/{$pallet->id}/open", [
        'boxes_count' => 6,
        'confirm_empty' => true,
    ]);

    $response->assertRedirect(route('admin.cells.index'));
    $this->assertDatabaseMissing('pallets', ['id' => $pallet->id]);
    expect($cell->refresh()->state)->toBe(CellState::Empty);
});

test('opening a pallet with a boxes_count equal to what remains, with confirm_empty, empties the pallet instead', function () {
    actingAsAdmin();
    $product = Product::factory()->create(['boxes_count' => 5]);
    $pallet = Pallet::factory()->create(['product_id' => $product->id]);
    $cell = $pallet->cell;

    $response = $this->post("/admin/pallets/{$pallet->id}/open", [
        'boxes_count' => 5,
        'confirm_empty' => true,
    ]);

    $response->assertRedirect(route('admin.cells.index'));
    $this->assertDatabaseMissing('pallets', ['id' => $pallet->id]);
    expect($cell->refresh()->state)->toBe(CellState::Empty);
});

test('opening a pallet with a boxes_count equal to what remains, without confirm_empty, is rejected and nothing changes', function () {
    actingAsAdmin();
    $product = Product::factory()->create(['boxes_count' => 5]);
    $pallet = Pallet::factory()->create(['product_id' => $product->id]);

    $response = $this->post("/admin/pallets/{$pallet->id}/open", ['boxes_count' => 5]);

    $response->assertSessionHasErrors(['action' => __('messages.insufficient_boxes_remaining')]);
    expect($pallet->cell->refresh()->state)->toBe(CellState::Full);
    expect($pallet->refresh()->remaining_boxes)->toBe(5);
});

test('opening a pallet with more boxes than remain, without confirm_empty, is rejected and nothing changes', function () {
    actingAsAdmin();
    $product = Product::factory()->create(['boxes_count' => 5]);
    $pallet = Pallet::factory()->create(['product_id' => $product->id]);

    $response = $this->post("/admin/pallets/{$pallet->id}/open", ['boxes_count' => 6]);

    $response->assertSessionHasErrors(['action' => __('messages.insufficient_boxes_remaining')]);
    expect($pallet->cell->refresh()->state)->toBe(CellState::Full);
    expect($pallet->refresh()->remaining_boxes)->toBe(5);
});

test('opening a pallet from a row page redirects back to that row page instead of the cell map', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    $product = Product::factory()->create(['boxes_count' => 10]);
    $pallet = Pallet::factory()->create(['cell_id' => $cell->id, 'product_id' => $product->id]);

    $response = $this->from("/admin/rows/{$row->letter}")->post("/admin/pallets/{$pallet->id}/open", [
        'boxes_count' => 3,
    ]);

    $response->assertRedirect("/admin/rows/{$row->letter}");
});

test('opening an already-opened pallet is rejected with a non-field action error', function () {
    actingAsAdmin();
    $pallet = Pallet::factory()->opened()->create();

    $response = $this->post("/admin/pallets/{$pallet->id}/open", ['boxes_count' => 1]);

    $response->assertSessionHasErrors(['action' => __('messages.pallet_not_full')]);
});

test('a mobile app user cannot open a pallet via the admin route', function () {
    actingAsMobilePanelUser();
    $pallet = Pallet::factory()->create();

    $response = $this->post("/admin/pallets/{$pallet->id}/open", ['boxes_count' => 1]);

    $response->assertForbidden();
    expect($pallet->cell->refresh()->state)->toBe(CellState::Full);
});

test('an unauthenticated caller cannot open a pallet and nothing changes', function () {
    $pallet = Pallet::factory()->create();

    $response = $this->post("/admin/pallets/{$pallet->id}/open", ['boxes_count' => 1]);

    $response->assertRedirect(route('login'));
    expect($pallet->cell->refresh()->state)->toBe(CellState::Full);
});

test('an authenticated admin can remove more boxes from an already-opened pallet', function () {
    $admin = actingAsAdmin();
    $product = Product::factory()->create(['boxes_count' => 10]);
    $pallet = Pallet::factory()->create(['product_id' => $product->id, 'remaining_boxes' => 6]);
    $pallet->cell->update(['state' => CellState::Opened]);

    $response = $this->post("/admin/pallets/{$pallet->id}/remove-boxes", ['boxes_count' => 4]);

    $response->assertRedirect(route('admin.cells.index'));
    expect($pallet->refresh()->remaining_boxes)->toBe(2);

    $this->assertDatabaseHas('cell_status_logs', [
        'action' => CellLogAction::BoxesRemoved->value,
        'pallet_id' => $pallet->id,
        'user_id' => $admin->id,
    ]);
});

test('removing a boxes_count equal to what remains, with confirm_empty, empties the pallet instead', function () {
    actingAsAdmin();
    $product = Product::factory()->create(['boxes_count' => 10]);
    $pallet = Pallet::factory()->create(['product_id' => $product->id, 'remaining_boxes' => 4]);
    $cell = $pallet->cell;
    $cell->update(['state' => CellState::Opened]);

    $response = $this->post("/admin/pallets/{$pallet->id}/remove-boxes", [
        'boxes_count' => 4,
        'confirm_empty' => true,
    ]);

    $response->assertRedirect(route('admin.cells.index'));
    $this->assertDatabaseMissing('pallets', ['id' => $pallet->id]);
    expect($cell->refresh()->state)->toBe(CellState::Empty);
});

test('removing a boxes_count equal to what remains, without confirm_empty, is rejected and nothing changes', function () {
    actingAsAdmin();
    $product = Product::factory()->create(['boxes_count' => 10]);
    $pallet = Pallet::factory()->create(['product_id' => $product->id, 'remaining_boxes' => 4]);
    $pallet->cell->update(['state' => CellState::Opened]);

    $response = $this->post("/admin/pallets/{$pallet->id}/remove-boxes", ['boxes_count' => 4]);

    $response->assertSessionHasErrors(['action' => __('messages.insufficient_boxes_remaining')]);
    expect($pallet->refresh()->remaining_boxes)->toBe(4);
});

test('removing boxes from a full (not yet opened) pallet is rejected with a non-field action error', function () {
    actingAsAdmin();
    $pallet = Pallet::factory()->create();

    $response = $this->post("/admin/pallets/{$pallet->id}/remove-boxes", ['boxes_count' => 1]);

    $response->assertSessionHasErrors(['action' => __('messages.pallet_not_opened')]);
});

test('a mobile app user cannot remove boxes via the admin route', function () {
    actingAsMobilePanelUser();
    $pallet = Pallet::factory()->opened()->create(['remaining_boxes' => 6]);

    $response = $this->post("/admin/pallets/{$pallet->id}/remove-boxes", ['boxes_count' => 1]);

    $response->assertForbidden();
    expect($pallet->refresh()->remaining_boxes)->toBe(6);
});

test('an unauthenticated caller cannot remove boxes and nothing changes', function () {
    $pallet = Pallet::factory()->opened()->create(['remaining_boxes' => 6]);

    $response = $this->post("/admin/pallets/{$pallet->id}/remove-boxes", ['boxes_count' => 1]);

    $response->assertRedirect(route('login'));
    expect($pallet->refresh()->remaining_boxes)->toBe(6);
});

test('an authenticated admin can empty a pallet regardless of remaining boxes', function () {
    $admin = actingAsAdmin();
    $pallet = Pallet::factory()->create();
    $cell = $pallet->cell;

    $response = $this->post("/admin/pallets/{$pallet->id}/empty", ['note' => 'Damaged.']);

    $response->assertRedirect(route('admin.cells.index'));
    $this->assertDatabaseMissing('pallets', ['id' => $pallet->id]);
    expect($cell->refresh()->state)->toBe(CellState::Empty);

    $this->assertDatabaseHas('cell_status_logs', [
        'cell_id' => $cell->id,
        'action' => CellLogAction::Emptied->value,
        'user_id' => $admin->id,
        'note' => 'Damaged.',
    ]);
});

test('emptying a pallet in an inactive cell is rejected with a non-field action error', function () {
    actingAsAdmin();
    $pallet = Pallet::factory()->create();
    $pallet->cell->update(['is_active' => false]);

    $response = $this->post("/admin/pallets/{$pallet->id}/empty");

    $response->assertSessionHasErrors(['action' => __('messages.slot_inactive')]);
    $this->assertDatabaseHas('pallets', ['id' => $pallet->id]);
});

test('a mobile app user cannot empty a pallet via the admin route', function () {
    actingAsMobilePanelUser();
    $pallet = Pallet::factory()->create();

    $response = $this->post("/admin/pallets/{$pallet->id}/empty");

    $response->assertForbidden();
    $this->assertDatabaseHas('pallets', ['id' => $pallet->id]);
});

test('an unauthenticated caller cannot empty a pallet and nothing changes', function () {
    $pallet = Pallet::factory()->create();

    $response = $this->post("/admin/pallets/{$pallet->id}/empty");

    $response->assertRedirect(route('login'));
    $this->assertDatabaseHas('pallets', ['id' => $pallet->id]);
});

test('an authenticated admin can transfer a pallet to another empty cell', function () {
    $admin = actingAsAdmin();
    $sourceRow = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $sourceCell = $sourceRow->cells()->first();
    $pallet = Pallet::factory()->create(['cell_id' => $sourceCell->id, 'remaining_boxes' => 7]);

    $destinationRow = Row::factory()->create(['letter' => 'B', 'cells_count' => 1, 'flats_count' => 1]);
    $destinationCell = $destinationRow->cells()->first();

    $response = $this->post("/admin/pallets/{$pallet->id}/transfer", [
        'row_letter' => $destinationRow->letter,
        'cell_number' => $destinationCell->cell_number,
        'flat_number' => $destinationCell->flat_number,
        'note' => 'Consolidating.',
    ]);

    $response->assertRedirect(route('admin.cells.index'));
    expect($sourceCell->refresh()->state)->toBe(CellState::Empty);
    expect($destinationCell->refresh()->state)->toBe(CellState::Full);
    expect($pallet->refresh()->cell_id)->toBe($destinationCell->id);

    $this->assertDatabaseHas('cell_status_logs', [
        'cell_id' => $sourceCell->id,
        'related_cell_id' => $destinationCell->id,
        'action' => CellLogAction::TransferredOut->value,
        'user_id' => $admin->id,
        'note' => 'Consolidating.',
    ]);
    $this->assertDatabaseHas('cell_status_logs', [
        'cell_id' => $destinationCell->id,
        'related_cell_id' => $sourceCell->id,
        'action' => CellLogAction::TransferredIn->value,
        'user_id' => $admin->id,
        'note' => 'Consolidating.',
    ]);
});

test('transferring a pallet from a row page redirects back to the source row page instead of the cell map', function () {
    actingAsAdmin();
    $sourceRow = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $sourceCell = $sourceRow->cells()->first();
    $pallet = Pallet::factory()->create(['cell_id' => $sourceCell->id]);

    $destinationRow = Row::factory()->create(['letter' => 'B', 'cells_count' => 1, 'flats_count' => 1]);
    $destinationCell = $destinationRow->cells()->first();

    $response = $this->from("/admin/rows/{$sourceRow->letter}")->post("/admin/pallets/{$pallet->id}/transfer", [
        'row_letter' => $destinationRow->letter,
        'cell_number' => $destinationCell->cell_number,
        'flat_number' => $destinationCell->flat_number,
    ]);

    $response->assertRedirect("/admin/rows/{$sourceRow->letter}");
});

test('transferring to a non-empty destination is rejected with a non-field action error and nothing changes', function () {
    actingAsAdmin();
    $sourceRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $sourceCell = $sourceRow->cells()->first();
    $pallet = Pallet::factory()->create(['cell_id' => $sourceCell->id]);

    $destinationRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $destinationCell = $destinationRow->cells()->first();
    Pallet::factory()->create(['cell_id' => $destinationCell->id]);

    $response = $this->post("/admin/pallets/{$pallet->id}/transfer", [
        'row_letter' => $destinationRow->letter,
        'cell_number' => $destinationCell->cell_number,
        'flat_number' => $destinationCell->flat_number,
    ]);

    $response->assertSessionHasErrors(['action' => __('messages.destination_not_empty')]);
    expect($pallet->refresh()->cell_id)->toBe($sourceCell->id);
});

test('transferring a pallet to its own current cell is rejected as a validation error', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    $pallet = Pallet::factory()->create(['cell_id' => $cell->id]);

    $response = $this->post("/admin/pallets/{$pallet->id}/transfer", [
        'row_letter' => $row->letter,
        'cell_number' => $cell->cell_number,
        'flat_number' => $cell->flat_number,
    ]);

    $response->assertSessionHasErrors(['cell_number']);
    expect($pallet->refresh()->cell_id)->toBe($cell->id);
});

test('a mobile app user cannot transfer a pallet via the admin route', function () {
    actingAsMobilePanelUser();
    $sourceRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $sourceCell = $sourceRow->cells()->first();
    $pallet = Pallet::factory()->create(['cell_id' => $sourceCell->id]);
    $destinationRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $destinationCell = $destinationRow->cells()->first();

    $response = $this->post("/admin/pallets/{$pallet->id}/transfer", [
        'row_letter' => $destinationRow->letter,
        'cell_number' => $destinationCell->cell_number,
        'flat_number' => $destinationCell->flat_number,
    ]);

    $response->assertForbidden();
    expect($pallet->refresh()->cell_id)->toBe($sourceCell->id);
});

test('an unauthenticated caller cannot transfer a pallet and nothing changes', function () {
    $sourceRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $sourceCell = $sourceRow->cells()->first();
    $pallet = Pallet::factory()->create(['cell_id' => $sourceCell->id]);
    $destinationRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $destinationCell = $destinationRow->cells()->first();

    $response = $this->post("/admin/pallets/{$pallet->id}/transfer", [
        'row_letter' => $destinationRow->letter,
        'cell_number' => $destinationCell->cell_number,
        'flat_number' => $destinationCell->flat_number,
    ]);

    $response->assertRedirect(route('login'));
    expect($pallet->refresh()->cell_id)->toBe($sourceCell->id);
    $this->assertDatabaseCount('cell_status_logs', 0);
});
