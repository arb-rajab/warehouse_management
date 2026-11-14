<?php

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Models\CellStatusLog;
use App\Models\CellStatusLogFlag;
use App\Models\CellVerificationRound;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;
use App\Models\Warehouse;
use App\Services\PalletCorrectionService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Working hours, so no off-hours flag can appear for the wrong reason.
    Carbon::setTestNow('2026-11-12 10:00:00');
});

/**
 * A selection entry for the bulk endpoints, with the pallet's current etag
 * as the list sends it.
 *
 * @return array{id: int, etag: string}
 */
function selected(Pallet $pallet): array
{
    $fresh = $pallet->fresh();

    return ['id' => $fresh->id, 'etag' => '"'.$fresh->load('cell')->etag().'"'];
}

// --- Access -------------------------------------------------------------------

test('a guest is refused both bulk corrections', function (string $uri) {
    $pallet = Pallet::factory()->create();

    $this->postJson($uri, ['pallets' => [['id' => $pallet->id]], 'note' => 'x', 'product_id' => $pallet->product_id, 'reason' => 'abc'])->assertUnauthorized();
})->with(['/api/v1/pallets/bulk/note', '/api/v1/pallets/change-product']);

test('a WMS user who is not an admin is refused the bulk note and nothing changes', function () {
    actingAsMobileUser();
    $pallet = Pallet::factory()->create(['note' => 'Before']);

    $this->postJson('/api/v1/pallets/bulk/note', ['pallets' => [['id' => $pallet->id]], 'note' => 'After'])->assertForbidden();

    expect($pallet->fresh()->note)->toBe('Before')->and(CellStatusLog::query()->count())->toBe(0);
});

test('a WMS user who is not an admin is refused the product change and nothing changes', function () {
    actingAsMobileUser();
    $pallet = Pallet::factory()->create();
    $target = Product::factory()->create();

    $this->postJson('/api/v1/pallets/change-product', ['pallets' => [['id' => $pallet->id]], 'product_id' => $target->id, 'reason' => 'Wrong product'])->assertForbidden();

    expect($pallet->fresh()->product_id)->not->toBe($target->id)->and(CellStatusLog::query()->count())->toBe(0);
});

// --- Bulk note ----------------------------------------------------------------

test('the bulk note gives every selected pallet the note, placed and unplaced, and answers them with new etags', function () {
    $admin = actingAsAdminMobileUser();
    $warehouse = Warehouse::firstOrCreateDefault();
    $placed = placedPalletIn($warehouse, ['note' => 'Olive oil']);
    $unplaced = unplacedPalletIn($warehouse);
    $other = Pallet::factory()->create(['note' => 'Untouched']); // noise
    $before = selected($placed);

    $response = $this->postJson('/api/v1/pallets/bulk/note', [
        'pallets' => [selected($unplaced), $before],
        'note' => '  Olive oil 5L, Brand X  ',
    ])->assertOk();

    expect(array_column($response->json(), 'id'))->toBe([$unplaced->id, $placed->id])
        ->and(array_column($response->json(), 'note'))->toBe(['Olive oil 5L, Brand X', 'Olive oil 5L, Brand X'])
        ->and($response->json('1.etag'))->toBe('"'.$placed->fresh()->load('cell')->etag().'"')
        ->and($response->json('1.etag'))->not->toBe($before['etag'])
        ->and($placed->fresh()->note)->toBe('Olive oil 5L, Brand X')
        ->and($unplaced->fresh()->note)->toBe('Olive oil 5L, Brand X')
        ->and($other->fresh()->note)->toBe('Untouched');

    $logs = CellStatusLog::query()->orderBy('pallet_id')->get();
    expect($logs)->toHaveCount(2)
        ->and($logs->pluck('batch_id')->unique())->toHaveCount(1)
        ->and($logs->first()->batch_id)->toBeString();

    $placedLog = $logs->firstWhere('pallet_id', $placed->id);
    expect($placedLog->only(['cell_id', 'action', 'from_state', 'to_state', 'product_id', 'boxes_count', 'user_id', 'note', 'old_note', 'new_note', 'reason', 'old_product_id']))->toEqual([
        'cell_id' => $placed->cell_id,
        'action' => CellLogAction::NoteChanged,
        'from_state' => CellState::Full,
        'to_state' => CellState::Full,
        'product_id' => $placed->product_id,
        'boxes_count' => $placed->remaining_boxes,
        'user_id' => $admin->id,
        'note' => null,
        'old_note' => 'Olive oil',
        'new_note' => 'Olive oil 5L, Brand X',
        'reason' => null,
        'old_product_id' => null,
    ]);

    $unplacedLog = $logs->firstWhere('pallet_id', $unplaced->id);
    expect($unplacedLog->only(['cell_id', 'from_state', 'to_state', 'old_note', 'new_note']))->toEqual([
        'cell_id' => null,
        'from_state' => null,
        'to_state' => null,
        'old_note' => null,
        'new_note' => 'Olive oil 5L, Brand X',
    ]);
});

test('a null or empty note clears it', function (?string $note) {
    actingAsAdminMobileUser();
    $pallet = Pallet::factory()->create(['note' => 'Olive oil']);

    $this->postJson('/api/v1/pallets/bulk/note', ['pallets' => [['id' => $pallet->id]], 'note' => $note])
        ->assertOk()
        ->assertJsonPath('0.note', null);

    expect($pallet->fresh()->note)->toBeNull()
        ->and(CellStatusLog::query()->sole()->only(['old_note', 'new_note']))->toBe(['old_note' => 'Olive oil', 'new_note' => null]);
})->with(['null' => [null], 'empty' => [''], 'blank' => ['   ']]);

test('a pallet that already carries the note is answered unchanged and gets no log entry', function () {
    actingAsAdminMobileUser();
    $same = Pallet::factory()->create(['note' => 'Brand X']);
    $different = Pallet::factory()->create();
    $sameEtag = selected($same)['etag'];

    $response = $this->postJson('/api/v1/pallets/bulk/note', ['pallets' => [selected($same), selected($different)], 'note' => 'Brand X'])->assertOk();

    expect($response->json('0.etag'))->toBe($sameEtag)
        ->and(CellStatusLog::query()->sole()->pallet_id)->toBe($different->id);
});

test('the etag of a pallet is optional', function () {
    actingAsAdminMobileUser();
    $pallet = Pallet::factory()->create();

    $this->postJson('/api/v1/pallets/bulk/note', ['pallets' => [['id' => $pallet->id]], 'note' => 'Brand X'])->assertOk();

    expect($pallet->fresh()->note)->toBe('Brand X');
});

test('a bare etag without quotes is accepted too', function () {
    actingAsAdminMobileUser();
    $pallet = Pallet::factory()->create();

    $this->postJson('/api/v1/pallets/bulk/note', ['pallets' => [['id' => $pallet->id, 'etag' => trim(selected($pallet)['etag'], '"')]], 'note' => 'Brand X'])->assertOk();
});

test('the bulk note is all or nothing: a stale etag or a gone pallet writes nothing and names each failing pallet', function () {
    actingAsAdminMobileUser();
    $fine = Pallet::factory()->create(['note' => 'Before']);
    $stale = Pallet::factory()->create(['note' => 'Before']);
    $staleEntry = selected($stale);
    $stale->update(['remaining_boxes' => $stale->remaining_boxes - 1]);
    $gone = Pallet::factory()->create();
    $goneId = $gone->id;
    $gone->delete();

    $response = $this->postJson('/api/v1/pallets/bulk/note', [
        'pallets' => [selected($fine), $staleEntry, ['id' => $goneId]],
        'note' => 'After',
    ]);

    $response->assertStatus(409)->assertJson([
        'message' => '2 pallets could not be changed.',
        'error_code' => 'bulk_rejected',
        'failed' => [
            ['id' => $stale->id, 'error_code' => 'pallet_changed', 'message' => __('messages.pallet_changed')],
            ['id' => $goneId, 'error_code' => 'pallet_not_found', 'message' => __('messages.pallet_not_found')],
        ],
    ]);
    expect($response->json('failed'))->toHaveCount(2)
        ->and($fine->fresh()->note)->toBe('Before')
        ->and($stale->fresh()->note)->toBe('Before')
        ->and(CellStatusLog::query()->count())->toBe(0);
});

test('one failing pallet is reported in the singular', function () {
    actingAsAdminMobileUser();

    $this->postJson('/api/v1/pallets/bulk/note', ['pallets' => [['id' => 999_999]], 'note' => 'After'])
        ->assertStatus(409)
        ->assertJsonPath('message', '1 pallet could not be changed.');
});

test('a placed pallet whose cell was opened since it was read is refused as changed', function () {
    actingAsAdminMobileUser();
    $pallet = Pallet::factory()->create();
    $entry = selected($pallet);
    $pallet->cell->update(['state' => CellState::Opened]);

    $this->postJson('/api/v1/pallets/bulk/note', ['pallets' => [$entry], 'note' => 'After'])
        ->assertStatus(409)
        ->assertJsonPath('failed.0.error_code', 'pallet_changed');
});

test('the bulk note body is validated', function (array $body, string $field) {
    actingAsAdminMobileUser();
    $pallet = Pallet::factory()->create();
    $body = json_decode(str_replace('"PALLET"', (string) $pallet->id, json_encode($body)), true);

    $this->postJson('/api/v1/pallets/bulk/note', $body)->assertUnprocessable()->assertJsonValidationErrors($field);

    expect(CellStatusLog::query()->count())->toBe(0);
})->with([
    'no pallets' => [['note' => 'x'], 'pallets'],
    'an empty list' => [['pallets' => [], 'note' => 'x'], 'pallets'],
    'a pallet without id' => [['pallets' => [['etag' => 'x']], 'note' => 'x'], 'pallets.0.id'],
    'a duplicate id' => [['pallets' => [['id' => 'PALLET'], ['id' => 'PALLET']], 'note' => 'x'], 'pallets.0.id'],
    'no note key' => [['pallets' => [['id' => 'PALLET']]], 'note'],
    'a note over 255 characters' => [['pallets' => [['id' => 'PALLET']], 'note' => str_repeat('a', 256)], 'note'],
]);

test('the bulk note takes at most 500 pallets', function () {
    actingAsAdminMobileUser();

    $this->postJson('/api/v1/pallets/bulk/note', ['pallets' => array_map(fn (int $id) => ['id' => $id], range(1, 501)), 'note' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('pallets');
});

// --- Product change -----------------------------------------------------------

test('the product change moves pallets of different products and warehouses, placed and unplaced, keeping everything else', function () {
    $admin = actingAsAdminMobileUser();
    $north = Warehouse::factory()->create();
    $south = Warehouse::factory()->create();
    $diversen = Product::factory()->boxesCount(50)->create(['name' => 'DIVERSEN']);
    $wrong = Product::factory()->create(['name' => 'Wrong one']);
    $olive = Product::factory()->boxesCount(12)->create(['name' => 'Olive oil 5L']);
    $placed = placedPalletIn($north, ['product_id' => $diversen->id, 'remaining_boxes' => 30, 'expiration_date' => '2027-05-01', 'note' => 'Olive oil 5L Brand X']);
    $unplaced = unplacedPalletIn($south, ['product_id' => $wrong->id, 'remaining_boxes' => 7, 'note' => 'Olive oil']);
    $noise = Pallet::factory()->create(['product_id' => $diversen->id, 'note' => 'Other']);
    $placedCellState = $placed->cell->state;

    $response = $this->postJson('/api/v1/pallets/change-product', [
        'pallets' => [selected($placed), selected($unplaced)],
        'product_id' => $olive->id,
        'reason' => 'Arrived as DIVERSEN, product created by accounting',
    ])->assertOk();

    expect(array_column($response->json(), 'id'))->toBe([$placed->id, $unplaced->id])
        ->and($response->json('0.product.id'))->toBe($olive->id)
        ->and($response->json('0.note'))->toBeNull()
        ->and($response->json('0.etag'))->toBe('"'.$placed->fresh()->load('cell')->etag().'"');

    $placedAfter = $placed->fresh();
    expect($placedAfter->only(['product_id', 'cell_id', 'remaining_boxes', 'note']))->toBe([
        'product_id' => $olive->id,
        'cell_id' => $placed->cell_id,
        'remaining_boxes' => 30,
        'note' => null,
    ])
        ->and($placedAfter->expiration_date->toDateString())->toBe('2027-05-01')
        ->and($placed->cell->fresh()->state)->toBe($placedCellState)
        ->and($unplaced->fresh()->only(['product_id', 'cell_id', 'remaining_boxes', 'note']))->toBe([
            'product_id' => $olive->id,
            'cell_id' => null,
            'remaining_boxes' => 7,
            'note' => null,
        ])
        ->and($noise->fresh()->only(['product_id', 'note']))->toBe(['product_id' => $diversen->id, 'note' => 'Other']);

    $logs = CellStatusLog::query()->get()->keyBy('pallet_id');
    expect($logs)->toHaveCount(2)
        ->and($logs->pluck('batch_id')->unique())->toHaveCount(1)
        ->and($logs[$placed->id]->only(['cell_id', 'action', 'from_state', 'to_state', 'product_id', 'old_product_id', 'reason', 'old_note', 'new_note', 'boxes_count', 'user_id']))->toEqual([
            'cell_id' => $placed->cell_id,
            'action' => CellLogAction::ProductChanged,
            'from_state' => $placedCellState,
            'to_state' => $placedCellState,
            'product_id' => $olive->id,
            'old_product_id' => $diversen->id,
            'reason' => 'Arrived as DIVERSEN, product created by accounting',
            'old_note' => 'Olive oil 5L Brand X',
            'new_note' => null,
            'boxes_count' => 30,
            'user_id' => $admin->id,
        ])
        ->and($logs[$unplaced->id]->only(['cell_id', 'from_state', 'to_state', 'old_product_id', 'product_id']))->toEqual([
            'cell_id' => null,
            'from_state' => null,
            'to_state' => null,
            'old_product_id' => $wrong->id,
            'product_id' => $olive->id,
        ]);
});

test('keep_note keeps the pallets notes and logs them unchanged', function () {
    actingAsAdminMobileUser();
    $pallet = Pallet::factory()->create(['note' => 'Brand X']);
    $target = Product::factory()->create();

    $this->postJson('/api/v1/pallets/change-product', ['pallets' => [['id' => $pallet->id]], 'product_id' => $target->id, 'reason' => 'Wrong product', 'keep_note' => true])
        ->assertOk()
        ->assertJsonPath('0.note', 'Brand X');

    expect($pallet->fresh()->note)->toBe('Brand X')
        ->and(CellStatusLog::query()->sole()->only(['old_note', 'new_note']))->toBe(['old_note' => 'Brand X', 'new_note' => 'Brand X']);
});

test('one pallet is a list of one', function () {
    actingAsAdminMobileUser();
    $pallet = Pallet::factory()->create();
    $target = Product::factory()->create();

    $this->postJson('/api/v1/pallets/change-product', ['pallets' => [selected($pallet)], 'product_id' => $target->id, 'reason' => 'Wrong product'])
        ->assertOk()
        ->assertJsonCount(1);

    expect($pallet->fresh()->product_id)->toBe($target->id);
});

test('the product change is all or nothing and names every failing pallet with its own code', function () {
    actingAsAdminMobileUser();
    $target = Product::factory()->create();
    $fine = Pallet::factory()->create();
    $fineProduct = $fine->product_id;
    $stale = Pallet::factory()->create();
    $staleEntry = selected($stale);
    $stale->update(['note' => 'Changed meanwhile']);
    $same = Pallet::factory()->create(['product_id' => $target->id]);
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $verified = Pallet::factory()->create(['cell_id' => $row->cells()->value('id')]);
    CellVerificationRound::factory()->covering($row)->create();

    $response = $this->postJson('/api/v1/pallets/change-product', [
        'pallets' => [selected($fine), $staleEntry, selected($same), selected($verified), ['id' => 999_999]],
        'product_id' => $target->id,
        'reason' => 'Wrong product',
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('error_code', 'bulk_rejected')
        ->assertJsonPath('message', '4 pallets could not be changed.');
    expect(array_map(fn (array $failure) => [$failure['id'], $failure['error_code']], $response->json('failed')))->toBe([
        [$stale->id, 'pallet_changed'],
        [$same->id, 'pallet_same_product'],
        [$verified->id, 'cell_in_active_round'],
        [999_999, 'pallet_not_found'],
    ])
        ->and($fine->fresh()->product_id)->toBe($fineProduct)
        ->and(CellStatusLog::query()->count())->toBe(0);
});

test('a finished verification round does not block the product change', function () {
    actingAsAdminMobileUser();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $pallet = Pallet::factory()->create(['cell_id' => $row->cells()->value('id')]);
    CellVerificationRound::factory()->covering($row)->completed()->create();
    $target = Product::factory()->create();

    $this->postJson('/api/v1/pallets/change-product', ['pallets' => [['id' => $pallet->id]], 'product_id' => $target->id, 'reason' => 'Wrong product'])->assertOk();
});

test('the product change body is validated', function (array $body, string $field) {
    actingAsAdminMobileUser();
    $pallet = Pallet::factory()->create();
    $active = Product::factory()->create();
    $inactive = Product::factory()->inactive()->create();
    $body = json_decode(str_replace(['"PALLET"', '"ACTIVE"', '"INACTIVE"'], [$pallet->id, $active->id, $inactive->id], json_encode($body)), true);

    $this->postJson('/api/v1/pallets/change-product', $body)->assertUnprocessable()->assertJsonValidationErrors($field);

    expect($pallet->fresh()->product_id)->not->toBe($active->id)->and(CellStatusLog::query()->count())->toBe(0);
})->with([
    'no reason' => [['pallets' => [['id' => 'PALLET']], 'product_id' => 'ACTIVE'], 'reason'],
    'a reason under 3 characters' => [['pallets' => [['id' => 'PALLET']], 'product_id' => 'ACTIVE', 'reason' => 'ab'], 'reason'],
    'a reason over 255 characters' => [['pallets' => [['id' => 'PALLET']], 'product_id' => 'ACTIVE', 'reason' => str_repeat('a', 256)], 'reason'],
    'no product' => [['pallets' => [['id' => 'PALLET']], 'reason' => 'Wrong product'], 'product_id'],
    'an unknown product' => [['pallets' => [['id' => 'PALLET']], 'product_id' => 999_999, 'reason' => 'Wrong product'], 'product_id'],
    'an inactive product' => [['pallets' => [['id' => 'PALLET']], 'product_id' => 'INACTIVE', 'reason' => 'Wrong product'], 'product_id'],
    'a duplicate id' => [['pallets' => [['id' => 'PALLET'], ['id' => 'PALLET']], 'product_id' => 'ACTIVE', 'reason' => 'Wrong product'], 'pallets.1.id'],
    'no pallets' => [['product_id' => 'ACTIVE', 'reason' => 'Wrong product'], 'pallets'],
    'keep_note not a boolean' => [['pallets' => [['id' => 'PALLET']], 'product_id' => 'ACTIVE', 'reason' => 'Wrong product', 'keep_note' => 'maybe'], 'keep_note'],
]);

// --- The history --------------------------------------------------------------

test('a bulk correction of many pallets raises no rapid-actions flag and does not count towards a later action', function () {
    actingAsAdminMobileUser();
    $product = Product::factory()->create();
    $pallets = Pallet::factory()->count(15)->create(['product_id' => $product->id]);
    $target = Product::factory()->create();

    $this->postJson('/api/v1/pallets/change-product', [
        'pallets' => $pallets->map(fn (Pallet $pallet) => ['id' => $pallet->id])->all(),
        'product_id' => $target->id,
        'reason' => 'Wrong product',
    ])->assertOk();
    $this->postJson('/api/v1/pallets/bulk/note', ['pallets' => $pallets->map(fn (Pallet $pallet) => ['id' => $pallet->id])->all(), 'note' => 'Brand X'])->assertOk();
    $this->postJson("/api/v1/pallets/{$pallets->first()->id}/empty")->assertNoContent();

    expect(CellStatusLog::query()->count())->toBe(31)
        ->and(CellStatusLogFlag::query()->count())->toBe(0);
});

test('the cell log feed shows a correction with its old and new values, and no cell for an unplaced pallet', function () {
    actingAsAdminMobileUser();
    $diversen = Product::factory()->create(['name' => 'DIVERSEN', 'ar_name' => 'متنوع']);
    $olive = Product::factory()->create(['name' => 'Olive oil 5L', 'ar_name' => 'زيت زيتون']);
    $pallet = unplacedPalletIn(Warehouse::firstOrCreateDefault(), ['product_id' => $diversen->id, 'note' => 'Olive oil 5L']);

    $this->postJson('/api/v1/pallets/change-product', ['pallets' => [['id' => $pallet->id]], 'product_id' => $olive->id, 'reason' => 'Arrived as DIVERSEN'])->assertOk();

    $log = CellStatusLog::query()->sole();
    $entry = $this->getJson("/api/v1/cell-logs?pallet_id={$pallet->id}")->assertOk()->json('data.0');

    expect($entry)->toMatchArray([
        'id' => $log->id,
        'action' => 'product_changed',
        'from_state' => null,
        'to_state' => null,
        'cell' => null,
        'related_cell' => null,
        'batch_id' => $log->batch_id,
        'reason' => 'Arrived as DIVERSEN',
        'old_note' => 'Olive oil 5L',
        'new_note' => null,
        'old_product' => ['id' => $diversen->id, 'name' => 'DIVERSEN', 'ar_name' => 'متنوع'],
        'new_product' => ['id' => $olive->id, 'name' => 'Olive oil 5L', 'ar_name' => 'زيت زيتون'],
        'pallet' => ['id' => $pallet->id, 'expiration_date' => $pallet->expiration_date?->toDateString()],
    ])->and($entry['product']['id'])->toBe($olive->id);
});

test('the admin cell log page lists a correction of an unplaced pallet', function () {
    actingAsAdmin();
    $pallet = Pallet::factory()->unplaced()->create();
    $target = Product::factory()->create();

    app(PalletCorrectionService::class)->changeProduct([['id' => $pallet->id, 'etag' => null]], $target->id, 'Wrong product', false, auth()->id());

    $this->get('/admin/cell-logs')->assertOk()->assertInertia(fn ($page) => $page
        ->where('logs.data.0.action', 'product_changed')
        ->where('logs.data.0.cell', null)
        ->where('logs.data.0.old_product.id', $pallet->product_id)
        ->where('logs.data.0.reason', 'Wrong product'));
});
