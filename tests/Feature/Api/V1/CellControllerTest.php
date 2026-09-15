<?php

use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;

test('a cell\'s pallet carries both raw product name columns, whatever the request locale', function () {
    // The payload no longer varies by `Accept-Language`: both store columns
    // ship raw. See .ai/rules/shared-database.md.
    actingAsMobileUser();

    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 1, 'flats_count' => 1]);
    $product = Product::factory()->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    // Noise: an untranslated product on another row ships an empty ar_name.
    $otherRow = Row::factory()->create(['letter' => 'Y', 'cells_count' => 1, 'flats_count' => 1]);
    $untranslated = Product::factory()->create(['name' => 'Gadgets', 'ar_name' => '']);
    Pallet::factory()->create([
        'cell_id' => $otherRow->cells()->first()->id,
        'product_id' => $untranslated->id,
    ]);

    $cell = $row->cells()->first();
    Pallet::factory()->create(['cell_id' => $cell->id, 'product_id' => $product->id]);

    $response = $this->getJson("/api/v1/rows/{$row->letter}/cells", ['Accept-Language' => 'ar']);
    $otherResponse = $this->getJson("/api/v1/rows/{$otherRow->letter}/cells", ['Accept-Language' => 'ar']);

    $response->assertOk();
    expect($response->json('data.0.pallet.product_name'))->toBe('Widgets');
    expect($response->json('data.0.pallet.product_ar_name'))->toBe('ودجات');

    $otherResponse->assertOk();
    expect($otherResponse->json('data.0.pallet.product_name'))->toBe('Gadgets');
    expect($otherResponse->json('data.0.pallet.product_ar_name'))->toBe('');
});

test('an authenticated worker can list a row cells with every property the app reads', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 2, 'flats_count' => 1]);
    $product = Product::factory()->imageUrl('https://cdn.example.com/widgets.png')->create([
        'name' => 'Widgets',
        'ar_name' => 'ودجات',
    ]);
    $occupiedCell = $row->cells()->where('cell_number', 1)->first();
    $emptyCell = $row->cells()->where('cell_number', 2)->first();
    $pallet = Pallet::factory()->create(['cell_id' => $occupiedCell->id, 'product_id' => $product->id]);

    $response = $this->getJson("/api/v1/rows/{$row->letter}/cells");

    $response->assertOk();
    expect(collect($response->json('data'))->firstWhere('id', $occupiedCell->id))->toEqual([
        'id' => $occupiedCell->id,
        'row_letter' => 'Z',
        'cell_number' => 1,
        'flat_number' => 1,
        'state' => 'full',
        'is_active' => true,
        'pallet' => [
            'id' => $pallet->id,
            'product_id' => $product->id,
            'product_name' => 'Widgets',
            'product_ar_name' => 'ودجات',
            'product_image_url' => 'https://cdn.example.com/widgets.png',
            'expiration_date' => $pallet->expiration_date->toDateString(),
            'added_at' => $pallet->created_at->toIso8601String(),
            'cell_entered_at' => null,
            'is_stale' => null,
            'remaining_boxes' => $pallet->remaining_boxes,
        ],
    ]);
    expect(collect($response->json('data'))->firstWhere('id', $emptyCell->id))->toEqual([
        'id' => $emptyCell->id,
        'row_letter' => 'Z',
        'cell_number' => 2,
        'flat_number' => 1,
        'state' => 'empty',
        'is_active' => true,
        'pallet' => null,
    ]);
});

test('a row cell listing computes is_stale from a caller-supplied stale_after_days instead of a fixed threshold', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);
    $stalePallet = Pallet::factory()->stale()->create(['cell_id' => $row->cells()->where('cell_number', 1)->first()->id]);
    $freshPallet = Pallet::factory()->create(['cell_id' => $row->cells()->where('cell_number', 2)->first()->id]);

    $response = $this->getJson("/api/v1/rows/{$row->letter}/cells?stale_after_days=5");

    $response->assertOk();
    expect(collect($response->json('data'))->firstWhere('id', $stalePallet->cell_id)['pallet']['is_stale'])->toBeTrue();
    expect(collect($response->json('data'))->firstWhere('id', $freshPallet->cell_id)['pallet']['is_stale'])->toBeFalse();
});

test('a row cell listing rejects a non-positive stale_after_days', function () {
    actingAsMobileUser();

    $row = Row::factory()->create();

    $response = $this->getJson("/api/v1/rows/{$row->letter}/cells?stale_after_days=0");

    $response->assertInvalid(['stale_after_days']);
});

test('a row cell listing filters by the pallet\'s product name, excluding a non-matching cell and an empty one', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['cells_count' => 3, 'flats_count' => 1]);
    $matchingCell = $row->cells()->where('cell_number', 1)->first();
    $nonMatchingCell = $row->cells()->where('cell_number', 2)->first();
    // Noise: cell 3 stays empty and must never match a search.
    Pallet::factory()->create([
        'cell_id' => $matchingCell->id,
        'product_id' => Product::factory()->create(['name' => 'Large Blue Widget'])->id,
    ]);
    Pallet::factory()->create([
        'cell_id' => $nonMatchingCell->id,
        'product_id' => Product::factory()->create(['name' => 'Small Gadget'])->id,
    ]);

    $response = $this->getJson("/api/v1/rows/{$row->letter}/cells?search=Widget");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matchingCell->id);
});

test('a row cell listing search matches every space-separated word regardless of order', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);
    $matchingCell = $row->cells()->where('cell_number', 1)->first();
    $nonMatchingCell = $row->cells()->where('cell_number', 2)->first();
    Pallet::factory()->create([
        'cell_id' => $matchingCell->id,
        'product_id' => Product::factory()->create(['name' => 'Glass Tea Pot'])->id,
    ]);
    // Noise: "greentea" must not match "Tea, Green" since it isn't a substring of either word.
    Pallet::factory()->create([
        'cell_id' => $nonMatchingCell->id,
        'product_id' => Product::factory()->create(['name' => 'Tea, Green'])->id,
    ]);

    $response = $this->getJson('/api/v1/rows/'.$row->letter.'/cells?search='.urlencode('tea glass'));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matchingCell->id);
});

test('a row cell listing search also matches the pallet\'s Arabic product name', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);
    $matchingCell = $row->cells()->where('cell_number', 1)->first();
    $nonMatchingCell = $row->cells()->where('cell_number', 2)->first();
    Pallet::factory()->create([
        'cell_id' => $matchingCell->id,
        'product_id' => Product::factory()->create(['name' => 'Widgets', 'ar_name' => 'ودجات'])->id,
    ]);
    Pallet::factory()->create([
        'cell_id' => $nonMatchingCell->id,
        'product_id' => Product::factory()->create(['name' => 'Gadgets', 'ar_name' => 'أدوات'])->id,
    ]);

    $response = $this->getJson('/api/v1/rows/'.$row->letter.'/cells?search='.urlencode('ودجات'));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matchingCell->id);
});

test('a row cell listing rejects a search term over 255 characters', function () {
    actingAsMobileUser();

    $row = Row::factory()->create();

    $response = $this->getJson('/api/v1/rows/'.$row->letter.'/cells?search='.str_repeat('a', 256));

    $response->assertInvalid(['search']);
});

test('a row cell listing excludes cells belonging to a different row', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);
    Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);

    $response = $this->getJson("/api/v1/rows/{$row->letter}/cells");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

test('a row cell listing paginates instead of returning everything at once', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['cells_count' => 25, 'flats_count' => 1]);

    $response = $this->getJson("/api/v1/rows/{$row->letter}/cells");

    assertJsonListingPaginates($response, total: 25);
});

test('an unauthenticated caller cannot list a row cells', function () {
    $row = Row::factory()->create();

    $response = $this->getJson("/api/v1/rows/{$row->letter}/cells");

    $response->assertUnauthorized();
});

test('an authenticated worker can look up a cell by its coordinates with every property the app reads', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 3, 'flats_count' => 2]);
    $product = Product::factory()->imageUrl('https://cdn.example.com/widgets.png')->create([
        'name' => 'Widgets',
        'ar_name' => 'ودجات',
    ]);
    $cell = $row->cells()->where('cell_number', 2)->where('flat_number', 1)->first();
    $pallet = Pallet::factory()->create(['cell_id' => $cell->id, 'product_id' => $product->id]);

    $response = $this->getJson("/api/v1/rows/{$row->letter}/cells/2/flats/1");

    $response->assertOk();
    expect($response->json())->toEqual([
        'id' => $cell->id,
        'row_letter' => 'Z',
        'cell_number' => 2,
        'flat_number' => 1,
        'state' => 'full',
        'is_active' => true,
        'pallet' => [
            'id' => $pallet->id,
            'product_id' => $product->id,
            'product_name' => 'Widgets',
            'product_ar_name' => 'ودجات',
            'product_image_url' => 'https://cdn.example.com/widgets.png',
            'expiration_date' => $pallet->expiration_date->toDateString(),
            'added_at' => $pallet->created_at->toIso8601String(),
            'cell_entered_at' => null,
            'is_stale' => null,
            'remaining_boxes' => $pallet->remaining_boxes,
        ],
    ]);
});

test('a cell lookup computes is_stale from a caller-supplied stale_after_days instead of a fixed threshold', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->where('cell_number', 1)->where('flat_number', 1)->first();
    Pallet::factory()->stale()->create(['cell_id' => $cell->id]);

    $response = $this->getJson("/api/v1/rows/{$row->letter}/cells/1/flats/1?stale_after_days=5");

    $response->assertOk()->assertJsonPath('pallet.is_stale', true);
});

test('looking up out-of-range coordinates for an existing row returns 404', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['cells_count' => 3, 'flats_count' => 2]);

    $response = $this->getJson("/api/v1/rows/{$row->letter}/cells/99/flats/1");

    $response->assertNotFound();
});

test('looking up a cell for an unknown row returns 404', function () {
    actingAsMobileUser();

    $response = $this->getJson('/api/v1/rows/ZZ/cells/1/flats/1');

    $response->assertNotFound();
});

test('a cell lookup excludes a cell with identical coordinates from a different row', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);
    Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);

    $expectedCell = $row->cells()->where('cell_number', 1)->where('flat_number', 1)->first();

    $response = $this->getJson("/api/v1/rows/{$row->letter}/cells/1/flats/1");

    $response->assertOk()->assertJsonPath('id', $expectedCell->id);
});

test('an unauthenticated caller cannot look up a cell', function () {
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);

    $response = $this->getJson("/api/v1/rows/{$row->letter}/cells/1/flats/1");

    $response->assertUnauthorized();
});

test('an inactive cell is reported as such by the lookup and listing endpoints', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->where('cell_number', 1)->where('flat_number', 1)->first();
    $cell->update(['is_active' => false]);

    $lookup = $this->getJson("/api/v1/rows/{$row->letter}/cells/1/flats/1");
    $lookup->assertOk()->assertJsonPath('is_active', false);

    $listing = $this->getJson("/api/v1/rows/{$row->letter}/cells");
    $listing->assertOk();
    expect(collect($listing->json('data'))->firstWhere('id', $cell->id)['is_active'])->toBeFalse();
});
