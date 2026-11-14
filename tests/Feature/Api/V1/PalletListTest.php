<?php

use App\Models\Pallet;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\Row;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;

/**
 * The ids `GET /api/v1/pallets` answers for the given query, in order.
 *
 * @param  array<string, mixed>  $query
 * @return list<int>
 */
function listedPalletIds(array $query = []): array
{
    return array_column(test()->getJson('/api/v1/pallets?'.http_build_query($query))->assertOk()->json('data'), 'id');
}

// --- Access -------------------------------------------------------------------

test('a guest is refused the pallet list', function () {
    $this->getJson('/api/v1/pallets')->assertUnauthorized();
});

test('a WMS user who is not an admin is refused the pallet list', function () {
    actingAsMobileUser();
    Pallet::factory()->create();

    $this->getJson('/api/v1/pallets')->assertForbidden();
});

// --- Shape --------------------------------------------------------------------

test('a placed pallet is listed with every PalletResource field, its receipt and its etag', function () {
    Carbon::setTestNow('2026-11-12 10:00:00');
    actingAsAdminMobileUser();
    $warehouse = Warehouse::factory()->create(['name' => 'Annex']);
    $row = Row::factory()->for($warehouse)->create(['letter' => 'Q', 'cells_count' => 1, 'flats_count' => 1]);
    $product = Product::factory()->imageUrl(null)->boxesCount(10)->create(['name' => 'Olive oil', 'ar_name' => 'زيت زيتون']);
    $receipt = Receipt::factory()->finalized()->for($warehouse)->create();
    $pallet = Pallet::factory()->create([
        'cell_id' => $row->cells()->value('id'),
        'receipt_id' => $receipt->id,
        'product_id' => $product->id,
        'remaining_boxes' => 8,
        'expiration_date' => '2027-01-31',
        'note' => 'Brand X',
    ]);

    $response = $this->getJson('/api/v1/pallets')->assertOk();

    expect($response->json('data'))->toEqual([[
        'id' => $pallet->id,
        'placed' => true,
        'state' => 'full',
        'expiration_date' => '2027-01-31',
        'cell_entered_at' => null,
        'remaining_boxes' => 8,
        'boxes_depleted_message' => null,
        'note' => 'Brand X',
        'product' => ['id' => $product->id, 'name' => 'Olive oil', 'ar_name' => 'زيت زيتون', 'image_url' => null, 'boxes_count' => 10, 'active' => true],
        'location' => ['row_letter' => 'Q', 'cell_number' => 1, 'flat_number' => 1],
        'warehouse' => ['id' => $warehouse->id, 'name' => 'Annex'],
        'receipt' => ['id' => $receipt->id, 'created_at' => $receipt->created_at->toIso8601String()],
        'etag' => '"'.$pallet->fresh()->load('cell')->etag().'"',
    ]]);
});

test('an unplaced pallet is listed with no location, its receipt warehouse, and a pallet stored directly has no receipt', function () {
    actingAsAdminMobileUser();
    $warehouse = Warehouse::factory()->create(['name' => 'North']);
    $product = Product::factory()->create(['name' => 'A product']);
    $unplaced = unplacedPalletIn($warehouse, ['product_id' => $product->id]);
    $stored = placedPalletIn($warehouse, ['product_id' => $product->id, 'created_at' => now()->addMinute()]);

    $data = collect($this->getJson('/api/v1/pallets')->assertOk()->json('data'))->keyBy('id');

    expect($data[$unplaced->id])->toMatchArray([
        'placed' => false,
        'state' => null,
        'location' => null,
        'warehouse' => ['id' => $warehouse->id, 'name' => 'North'],
        'receipt' => ['id' => $unplaced->receipt_id, 'created_at' => $unplaced->receipt->created_at->toIso8601String()],
        'etag' => '"'.$unplaced->fresh()->etag().'"',
    ])->and($data[$stored->id]['receipt'])->toBeNull();
});

test('the etag of a listed pallet is accepted as If-Match by the pallet actions', function () {
    actingAsAdminMobileUser();
    $pallet = Pallet::factory()->create(['note' => 'Brand X']);
    $etag = $this->getJson('/api/v1/pallets')->json('data.0.etag');

    $this->postJson("/api/v1/pallets/{$pallet->id}/empty", [], ['If-Match' => '"stale"'])->assertStatus(412);
    $this->postJson("/api/v1/pallets/{$pallet->id}/empty", [], ['If-Match' => $etag])->assertNoContent();
});

// --- Order --------------------------------------------------------------------

test('pallets are sorted by product name, then note with no note last, then oldest first', function () {
    actingAsAdminMobileUser();
    $warehouse = Warehouse::firstOrCreateDefault();
    $apples = Product::factory()->create(['name' => 'Apples']);
    $bananas = Product::factory()->create(['name' => 'Bananas']);
    $at = fn (int $minutes) => now()->subHour()->addMinutes($minutes);

    $bananaOld = unplacedPalletIn($warehouse, ['product_id' => $bananas->id, 'created_at' => $at(1)]);
    $appleNoNote = placedPalletIn($warehouse, ['product_id' => $apples->id, 'created_at' => $at(2)]);
    $appleZed = unplacedPalletIn($warehouse, ['product_id' => $apples->id, 'note' => 'Zed', 'created_at' => $at(3)]);
    $appleAlphaNew = placedPalletIn($warehouse, ['product_id' => $apples->id, 'note' => 'Alpha', 'created_at' => $at(5)]);
    $appleAlphaOld = unplacedPalletIn($warehouse, ['product_id' => $apples->id, 'note' => 'Alpha', 'created_at' => $at(4)]);

    expect(listedPalletIds())->toBe([
        $appleAlphaOld->id,
        $appleAlphaNew->id,
        $appleZed->id,
        $appleNoNote->id,
        $bananaOld->id,
    ]);
});

// --- Filters ------------------------------------------------------------------

test('product_id[] keeps the pallets of those products', function () {
    actingAsAdminMobileUser();
    $kept = Pallet::factory()->create();
    $alsoKept = Pallet::factory()->unplaced()->create();
    Pallet::factory()->create(); // noise

    expect(listedPalletIds(['product_id' => [$kept->product_id, $alsoKept->product_id]]))
        ->toEqualCanonicalizing([$kept->id, $alsoKept->id]);
});

test('warehouse_ids[] keeps placed pallets by their cell and unplaced ones by their receipt', function () {
    actingAsAdminMobileUser();
    $north = Warehouse::factory()->create();
    $south = Warehouse::factory()->create();
    $placed = placedPalletIn($north);
    $unplaced = unplacedPalletIn($north);
    placedPalletIn($south); // noise
    unplacedPalletIn($south); // noise

    expect(listedPalletIds(['warehouse_ids' => [$north->id]]))->toEqualCanonicalizing([$placed->id, $unplaced->id]);
});

test('placed=true and placed=false keep the placed and the unplaced pallets', function () {
    actingAsAdminMobileUser();
    $placed = Pallet::factory()->create();
    $unplaced = Pallet::factory()->unplaced()->create();

    expect(listedPalletIds(['placed' => 'true']))->toBe([$placed->id])
        ->and(listedPalletIds(['placed' => 'false']))->toBe([$unplaced->id])
        ->and(listedPalletIds())->toHaveCount(2);
});

test('has_note=true and has_note=false keep the pallets with and without a note', function () {
    actingAsAdminMobileUser();
    $noted = Pallet::factory()->create(['note' => 'Olive oil 5L']);
    $bare = Pallet::factory()->create();

    expect(listedPalletIds(['has_note' => 'true']))->toBe([$noted->id])
        ->and(listedPalletIds(['has_note' => 'false']))->toBe([$bare->id]);
});

test('q matches the note or the product name or Arabic name, contains and case-insensitive', function () {
    actingAsAdminMobileUser();
    $byNote = Pallet::factory()->create(['note' => 'Real: OLIVE oil 5L']);
    $byName = Pallet::factory()->for(Product::factory()->state(['name' => 'Extra virgin olive oil']))->create();
    $byArName = Pallet::factory()->for(Product::factory()->state(['name' => 'Something', 'ar_name' => 'زيت زيتون']))->create();
    Pallet::factory()->for(Product::factory()->state(['name' => 'Rice', 'ar_name' => 'أرز']))->create(['note' => 'Basmati']); // noise

    expect(listedPalletIds(['q' => 'olive']))->toEqualCanonicalizing([$byNote->id, $byName->id])
        ->and(listedPalletIds(['q' => 'زيتون']))->toBe([$byArName->id]);
});

test('q matches a literal percent sign or underscore rather than as a wildcard', function () {
    actingAsAdminMobileUser();
    $literal = Pallet::factory()->create(['note' => '100% olive']);
    Pallet::factory()->create(['note' => '100 olive']); // noise

    expect(listedPalletIds(['q' => '100%']))->toBe([$literal->id])
        ->and(listedPalletIds(['q' => '_']))->toBe([]);
});

test('receipt_id keeps the pallets created by that receipt', function () {
    actingAsAdminMobileUser();
    $pallet = Pallet::factory()->unplaced()->create();
    Pallet::factory()->unplaced()->create(); // noise, its own receipt
    Pallet::factory()->create(); // noise, no receipt

    expect(listedPalletIds(['receipt_id' => $pallet->receipt_id]))->toBe([$pallet->id]);
});

test('the filters combine', function () {
    actingAsAdminMobileUser();
    $product = Product::factory()->create();
    $match = Pallet::factory()->unplaced()->create(['product_id' => $product->id, 'note' => 'Brand X']);
    Pallet::factory()->create(['product_id' => $product->id, 'note' => 'Brand X']); // placed
    Pallet::factory()->unplaced()->create(['product_id' => $product->id]); // no note

    expect(listedPalletIds(['product_id' => [$product->id], 'placed' => 'false', 'has_note' => 'true', 'q' => 'brand']))->toBe([$match->id]);
});

test('invalid filters are refused', function (array $query, string $field) {
    actingAsAdminMobileUser();

    $this->getJson('/api/v1/pallets?'.http_build_query($query))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'placed not a boolean' => [['placed' => 'maybe'], 'placed'],
    'has_note not a boolean' => [['has_note' => 'maybe'], 'has_note'],
    'unknown product' => [['product_id' => [999_999]], 'product_id.0'],
    'unknown warehouse' => [['warehouse_ids' => [999_999]], 'warehouse_ids.0'],
    'receipt_id not an integer' => [['receipt_id' => 'abc'], 'receipt_id'],
    'per_page above 200' => [['per_page' => 201], 'per_page'],
    'per_page zero' => [['per_page' => 0], 'per_page'],
]);

// --- Paging -------------------------------------------------------------------

test('the list is paged, 20 per page by default, in the usual envelope', function () {
    actingAsAdminMobileUser();
    $product = Product::factory()->create();
    Pallet::factory()->unplaced()->count(21)->create(['product_id' => $product->id]);

    $response = $this->getJson('/api/v1/pallets')->assertOk();

    expect($response->json('data'))->toHaveCount(20)
        ->and($response->json('meta.total'))->toBe(21)
        ->and($response->json('meta.per_page'))->toBe(20)
        ->and($response->json('links.next'))->not->toBeNull();
});

test('per_page takes up to 200 pallets on one page', function () {
    actingAsAdminMobileUser();
    $product = Product::factory()->create();
    Pallet::factory()->unplaced()->count(3)->create(['product_id' => $product->id]);

    $response = $this->getJson('/api/v1/pallets?per_page=2')->assertOk();
    expect($response->json('data'))->toHaveCount(2)->and($response->json('meta.last_page'))->toBe(2);

    $this->getJson('/api/v1/pallets?per_page=200')->assertOk()->assertJsonPath('meta.per_page', 200);
});
