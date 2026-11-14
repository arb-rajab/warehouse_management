<?php

use App\Models\Pallet;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\Row;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

test('an authenticated worker can list products with every property the app reads', function () {
    actingAsMobileUser();

    $product = Product::factory()->imageUrl('https://cdn.example.com/widget.png')->boxesCount(12)->create([
        'name' => 'Widget',
        'ar_name' => 'ودجة',
    ]);
    $otherProduct = Product::factory()->create(['name' => 'Gadget']);

    $response = $this->getJson('/api/v1/products');

    $response->assertOk();
    expect(collect($response->json('data'))->firstWhere('id', $product->id))->toEqual([
        'id' => $product->id,
        'name' => 'Widget',
        'ar_name' => 'ودجة',
        'image_url' => 'https://cdn.example.com/widget.png',
        'boxes_count' => 12,
        'active' => true,
    ]);
    expect(collect($response->json('data'))->pluck('name'))->toContain('Gadget');
    expect($otherProduct->id)->not->toBeNull();
});

test('an Arabic-locale client gets the same raw name columns as an English one', function () {
    // The payload no longer varies by `Accept-Language`: both store columns
    // ship raw and the client picks. See .ai/rules/shared-database.md.
    actingAsMobileUser();

    $product = Product::factory()->imageUrl('https://cdn.example.com/widget.png')->boxesCount(12)->create([
        'name' => 'Widget',
        'ar_name' => 'ودجة',
    ]);
    // Noise: another product's names must not be the ones returned.
    Product::factory()->create(['name' => 'Gadget', 'ar_name' => 'أداة']);

    $response = $this->getJson('/api/v1/products', ['Accept-Language' => 'ar']);

    $response->assertOk();
    expect(collect($response->json('data'))->firstWhere('id', $product->id))->toEqual([
        'id' => $product->id,
        'name' => 'Widget',
        'ar_name' => 'ودجة',
        'image_url' => 'https://cdn.example.com/widget.png',
        'boxes_count' => 12,
        'active' => true,
    ]);
});

test('a product the store never translated ships an empty ar_name rather than a null one', function () {
    // `ar_name` is NOT NULL upstream, so an untranslated product carries an
    // empty string — that is exactly what the frontend resolver falls back on.
    actingAsMobileUser();

    $product = Product::factory()->imageUrl(null)->boxesCount(6)->create([
        'name' => 'Widget',
        'ar_name' => '',
    ]);

    $response = $this->getJson('/api/v1/products', ['Accept-Language' => 'ar']);

    $response->assertOk();
    expect(collect($response->json('data'))->firstWhere('id', $product->id))->toEqual([
        'id' => $product->id,
        'name' => 'Widget',
        'ar_name' => '',
        'image_url' => null,
        'boxes_count' => 6,
        'active' => true,
    ]);
});

test('an unpublished product ships active as false', function () {
    actingAsMobileUser();

    $product = Product::factory()->create(['name' => 'Widget', 'published' => false]);
    Pallet::factory()->create(['product_id' => $product->id]);

    $response = $this->getJson('/api/v1/products?product_status=inactive');

    $response->assertOk();
    expect(collect($response->json('data'))->firstWhere('id', $product->id)['active'])->toBeFalse();
});

test('the product listing returns active products and the inactive ones the warehouse still holds by default', function () {
    actingAsMobileUser();

    $activeWithoutPallets = Product::factory()->create(['name' => 'Active Widget']);
    $activeWithPallet = Product::factory()->create(['name' => 'Stocked Widget']);
    Pallet::factory()->create(['product_id' => $activeWithPallet->id]);
    $inactiveInWarehouse = Product::factory()->inactive()->create(['name' => 'Inactive Stocked Widget']);
    Pallet::factory()->create(['product_id' => $inactiveInWarehouse->id]);
    // Noise: deactivated and no longer in the warehouse.
    $inactiveElsewhere = Product::factory()->inactive()->create(['name' => 'Inactive Widget']);

    $response = $this->getJson('/api/v1/products');

    $response->assertOk();
    $rows = collect($response->json('data'));
    expect($rows->pluck('id')->all())->toEqualCanonicalizing([$activeWithoutPallets->id, $activeWithPallet->id, $inactiveInWarehouse->id]);
    expect($rows->firstWhere('id', $inactiveInWarehouse->id)['active'])->toBeFalse();
    expect($rows->pluck('id'))->not->toContain($inactiveElsewhere->id);
});

test('the product listing drops an inactive product once its last pallet leaves the warehouse', function () {
    actingAsMobileUser();

    $product = Product::factory()->inactive()->create(['name' => 'Inactive Widget']);
    $pallet = Pallet::factory()->create(['product_id' => $product->id]);
    // Noise: an active product stays listed throughout.
    $active = Product::factory()->create(['name' => 'Active Widget']);

    expect(collect($this->getJson('/api/v1/products')->json('data'))->pluck('id')->all())
        ->toEqualCanonicalizing([$product->id, $active->id]);

    $pallet->delete();

    expect(collect($this->getJson('/api/v1/products')->json('data'))->pluck('id')->all())
        ->toBe([$active->id]);
});

test('the product listing paginates instead of returning everything at once', function () {
    actingAsMobileUser();

    Product::factory()->count(25)->create();

    $response = $this->getJson('/api/v1/products');

    assertJsonListingPaginates($response, total: 25);
});

test('the product listing keeps the caller\'s query string on its pagination links', function () {
    actingAsMobileUser();

    Product::factory()->count(25)->sequence(fn ($sequence) => ['name' => "Widget {$sequence->index}"])->create();
    // Noise: a product the search excludes must not count towards the second page.
    Product::factory()->create(['name' => 'Unrelated Gadgets']);

    assertNextLinkKeepsQuery($this->getJson('/api/v1/products?q=Widget'), 'q=Widget');
});

test('the product listing filters by name, excluding a non-matching product', function () {
    actingAsMobileUser();

    $matching = Product::factory()->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    Product::factory()->create(['name' => 'Unrelated Gadgets', 'ar_name' => 'أدوات']);

    $response = $this->getJson('/api/v1/products?q=Widg');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
});

test('the product listing filters by the store\'s Arabic name, excluding a non-matching product', function () {
    actingAsMobileUser();

    $matching = Product::factory()->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    Product::factory()->create(['name' => 'Unrelated Gadgets', 'ar_name' => 'أدوات']);

    $response = $this->getJson('/api/v1/products?q='.urlencode('ودجات'));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
    // Searching matches either column; the response ships both raw, so an
    // Arabic term still comes back with the base `name` alongside it.
    expect($response->json('data.0.name'))->toBe('Widgets');
    expect($response->json('data.0.ar_name'))->toBe('ودجات');
});

test('the product listing matches an Arabic term sent with an English Accept-Language header', function () {
    // The searched columns are locale-independent: a worker with the app in
    // English still finds a product by the Arabic name printed on its box.
    actingAsMobileUser();

    $matching = Product::factory()->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    Product::factory()->create(['name' => 'Unrelated Gadgets', 'ar_name' => 'أدوات']);

    $response = $this->getJson('/api/v1/products?q='.urlencode('ودجات'), ['Accept-Language' => 'en']);

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
});

test('the product listing matches a multi-word term split across the two name columns', function () {
    actingAsMobileUser();

    $matching = Product::factory()->create(['name' => 'Large Blue Widget', 'ar_name' => 'ودجة زرقاء كبيرة']);
    // Noise: matches only the English half of the term.
    Product::factory()->create(['name' => 'Small Widget', 'ar_name' => 'ودجة صغيرة']);

    $response = $this->getJson('/api/v1/products?q='.urlencode('Widget زرقاء'));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
});

test('the product listing returns every product for a blank term', function () {
    actingAsMobileUser();

    Product::factory()->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    Product::factory()->create(['name' => 'Unrelated Gadgets', 'ar_name' => 'أدوات']);

    $response = $this->getJson('/api/v1/products?q=');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

test('the product listing can be filtered by product_status=active, excluding an inactive product', function () {
    actingAsMobileUser();

    $activeProduct = Product::factory()->create(['name' => 'Active Widget']);
    Product::factory()->inactive()->create(['name' => 'Inactive Widget']);
    // Noise: still in the warehouse, but inactive all the same.
    $inactiveInWarehouse = Product::factory()->inactive()->create(['name' => 'Inactive Stocked Widget']);
    Pallet::factory()->create(['product_id' => $inactiveInWarehouse->id]);

    $response = $this->getJson('/api/v1/products?product_status=active');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($activeProduct->id);
});

test('the product listing can be filtered by product_status=inactive, returning only inactive products the warehouse still holds', function () {
    actingAsMobileUser();

    // Noise: an active product, with and without pallets.
    Product::factory()->create(['name' => 'Active Widget']);
    $activeWithPallet = Product::factory()->create(['name' => 'Stocked Widget']);
    Pallet::factory()->create(['product_id' => $activeWithPallet->id]);
    // Noise: deactivated and no longer in the warehouse.
    Product::factory()->inactive()->create(['name' => 'Inactive Widget']);
    $inactiveInWarehouse = Product::factory()->inactive()->create(['name' => 'Inactive Stocked Widget']);
    Pallet::factory()->create(['product_id' => $inactiveInWarehouse->id]);

    $response = $this->getJson('/api/v1/products?product_status=inactive');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($inactiveInWarehouse->id);
});

test('an invalid product_status is rejected on the product listing', function () {
    actingAsMobileUser();

    $response = $this->getJson('/api/v1/products?product_status=bogus');

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['product_status']);
});

test('an unauthenticated caller cannot list products', function () {
    $response = $this->getJson('/api/v1/products');

    $response->assertUnauthorized();
});

test('an authenticated worker can fetch a single product by id', function () {
    actingAsMobileUser();

    $product = Product::factory()->imageUrl('https://cdn.example.com/widget.png')->boxesCount(12)->create([
        'name' => 'Widget',
        'ar_name' => 'ودجة',
    ]);
    // Noise: a second product must not leak into this response.
    Product::factory()->create(['name' => 'Gadget']);

    $response = $this->getJson("/api/v1/products/{$product->id}");

    $response->assertOk();
    expect($response->json())->toEqual([
        'id' => $product->id,
        'name' => 'Widget',
        'ar_name' => 'ودجة',
        'image_url' => 'https://cdn.example.com/widget.png',
        'boxes_count' => 12,
        'active' => true,
    ]);
});

test('a worker can fetch a product using any of its additional barcodes', function (string $barcode) {
    actingAsMobileUser();

    $product = Product::factory()->imageUrl('https://cdn.example.com/widget.png')->boxesCount(12)->create([
        'name' => 'Widget',
        'ar_name' => 'ودجة',
    ]);
    ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => 'OTHER-WIDGET-CODE']);
    ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => $barcode]);
    ProductBarcode::factory()->create(['barcode' => 'UNRELATED-CODE']);

    $response = $this->getJson('/api/v1/products/'.rawurlencode($barcode));

    $response->assertOk();
    expect($response->json())->toEqual([
        'id' => $product->id,
        'name' => 'Widget',
        'ar_name' => 'ودجة',
        'image_url' => 'https://cdn.example.com/widget.png',
        'boxes_count' => 12,
        'active' => true,
    ]);
})->with(['8712345678901', 'BOX-ABC-123', '000123', '9999999999999999999999999999999999999999']);

test('a barcode with leading zeroes resolves exactly instead of matching a product id', function () {
    actingAsMobileUser();

    $otherProduct = Product::factory()->create();
    $barcode = '000'.$otherProduct->id;
    $product = Product::factory()->create();
    ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => $barcode]);

    $this->getJson('/api/v1/products/'.$barcode)
        ->assertOk()
        ->assertJsonPath('id', $product->id);
});

test('the default product id takes priority over a conflicting legacy barcode', function () {
    actingAsMobileUser();

    $product = Product::factory()->create();
    ProductBarcode::factory()->create(['barcode' => (string) $product->id]);

    $this->getJson("/api/v1/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('id', $product->id);
});

test('an inactive product without pallets still resolves by its additional barcode', function () {
    actingAsMobileUser();

    $product = Product::factory()->inactive()->create();
    ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => 'INACTIVE-BOX']);
    ProductBarcode::factory()->create(['barcode' => 'UNRELATED-CODE']);

    $this->getJson('/api/v1/products/INACTIVE-BOX')
        ->assertOk()
        ->assertJsonPath('id', $product->id)
        ->assertJsonPath('active', false);
});

test('a barcode lookup requires an exact match', function (string $barcode) {
    actingAsMobileUser();

    ProductBarcode::factory()->create(['barcode' => 'BOX-12345']);

    $this->getJson('/api/v1/products/'.rawurlencode($barcode))->assertNotFound();
})->with(['BOX-123', 'UNKNOWN-CODE']);

test('a barcode the store form or the import wrote resolves too: one shared table', function () {
    actingAsMobileUser();

    $product = Product::factory()->create();
    DB::table('product_barcodes')->insert(['product_id' => $product->id, 'barcode' => 'STORE-WRITTEN-1']);
    DB::table('product_barcodes')->insert(['product_id' => Product::factory()->create()->id, 'barcode' => 'UNRELATED-CODE']);

    $this->getJson('/api/v1/products/STORE-WRITTEN-1')
        ->assertOk()
        ->assertJsonPath('id', $product->id);
});

test('a barcode several products hold answers 409 ambiguous_barcode with their ids', function () {
    actingAsMobileUser();

    $first = Product::factory()->create();
    $second = Product::factory()->create();
    ProductBarcode::factory()->create(['product_id' => $second->id, 'barcode' => 'SHARED-BOX']);
    ProductBarcode::factory()->create(['product_id' => $first->id, 'barcode' => 'SHARED-BOX']);
    ProductBarcode::factory()->create(['product_id' => $first->id, 'barcode' => 'OTHER-BOX']);

    $response = $this->getJson('/api/v1/products/SHARED-BOX');

    $response->assertConflict();
    expect($response->json())->toBe([
        'message' => 'This barcode belongs to more than one product.',
        'error_code' => 'ambiguous_barcode',
        'product_ids' => [$first->id, $second->id],
    ]);
});

test('a barcode one product holds twice is not ambiguous', function () {
    actingAsMobileUser();

    $product = Product::factory()->create();
    ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => 'TWICE-BOX']);
    ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => 'TWICE-BOX']);

    $this->getJson('/api/v1/products/TWICE-BOX')
        ->assertOk()
        ->assertJsonPath('id', $product->id);
});

test('a product id still wins over a barcode several other products hold', function () {
    actingAsMobileUser();

    $product = Product::factory()->create();
    ProductBarcode::factory()->create(['barcode' => (string) $product->id]);
    ProductBarcode::factory()->create(['barcode' => (string) $product->id]);

    $this->getJson("/api/v1/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('id', $product->id);
});

test('a stored value is matched as stored: Arabic-Indic digits and the legacy column do not match an ASCII scan', function () {
    actingAsMobileUser();

    $product = Product::factory()->create();
    ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => '٨٧١٢٣٤٥']);
    DB::table('products')->where('id', $product->id)->update(['barcode' => 'LEGACY-COLUMN-1']);

    $this->getJson('/api/v1/products/8712345')->assertNotFound();
    $this->getJson('/api/v1/products/LEGACY-COLUMN-1')->assertNotFound();
});

test('an unauthenticated caller cannot fetch a product by its additional barcode', function () {
    ProductBarcode::factory()->create(['barcode' => 'BOX-12345']);

    $this->getJson('/api/v1/products/BOX-12345')->assertUnauthorized();
});

test('fetching a deactivated product by id returns it normally with active false, not a 404', function () {
    actingAsMobileUser();

    // No pallets either: a lookup by id is exempt from the listing's
    // inactive-only-while-in-the-warehouse rule, so an old QR label resolves.
    $product = Product::factory()->inactive()->create(['name' => 'Widget']);

    $response = $this->getJson("/api/v1/products/{$product->id}");

    $response->assertOk();
    expect($response->json('active'))->toBeFalse();
});

test('fetching a nonexistent product id returns a 404', function () {
    actingAsMobileUser();

    $response = $this->getJson('/api/v1/products/999999');

    $response->assertNotFound();
});

test('an unauthenticated caller cannot fetch a single product', function () {
    $product = Product::factory()->create();

    $response = $this->getJson("/api/v1/products/{$product->id}");

    $response->assertUnauthorized();
});

test('an authenticated worker can list every current pallet of a product, ordered by expiration then arrival then location', function () {
    actingAsMobileUser();

    $product = Product::factory()->boxesCount(20)->create();
    $otherProduct = Product::factory()->create();

    $rowA = Row::factory()->create(['letter' => 'A', 'cells_count' => 3, 'flats_count' => 1]);
    $rowB = Row::factory()->create(['letter' => 'B', 'cells_count' => 2, 'flats_count' => 1]);

    // Expires soonest, in row B — must sort first despite being in a "later" row.
    $soonest = Pallet::factory()->for($product)->create([
        'cell_id' => $rowB->cells()->where('cell_number', 1)->first()->id,
        'expiration_date' => now()->addDays(5)->toDateString(),
    ]);

    // No expiration date at all — must sort after every dated pallet.
    $noExpiry = Pallet::factory()->for($product)->create([
        'cell_id' => $rowA->cells()->where('cell_number', 3)->first()->id,
        'expiration_date' => null,
    ]);

    // Same expiration date as $tiedLater but added earlier — must come first.
    $tiedEarlier = Pallet::factory()->for($product)->create([
        'cell_id' => $rowA->cells()->where('cell_number', 1)->first()->id,
        'expiration_date' => now()->addDays(30)->toDateString(),
    ]);
    $tiedLater = Pallet::factory()->for($product)->create([
        'cell_id' => $rowA->cells()->where('cell_number', 2)->first()->id,
        'expiration_date' => now()->addDays(30)->toDateString(),
    ]);
    $tiedLater->forceFill(['created_at' => $tiedEarlier->created_at->addMinute()])->save();

    // Noise: another product's pallet must never appear in this response.
    Pallet::factory()->for($otherProduct)->create([
        'cell_id' => $rowB->cells()->where('cell_number', 2)->first()->id,
    ]);

    $response = $this->getJson("/api/v1/products/{$product->id}/pallets");

    $response->assertOk();
    expect(collect($response->json())->pluck('id')->all())->toBe([
        $soonest->id, $tiedEarlier->id, $tiedLater->id, $noExpiry->id,
    ]);

    expect(collect($response->json())->firstWhere('id', $soonest->id))->toEqual([
        'id' => $soonest->id,
        'state' => 'full',
        'expiration_date' => $soonest->expiration_date->toDateString(),
        'added_at' => $soonest->created_at->toIso8601String(),
        'cell_entered_at' => null,
        'remaining_boxes' => 20,
        'product_boxes_count' => 20,
        'location' => [
            'row_letter' => 'B',
            'cell_number' => 1,
            'flat_number' => 1,
        ],
        'is_active' => true,
        'note' => null,
        'placed' => true,
        'warehouse' => ['id' => Warehouse::firstOrCreateDefault()->id, 'name' => Warehouse::DEFAULT_NAME],
    ]);
});

test('the pallet listing reflects an inactive cell', function () {
    actingAsMobileUser();

    $product = Product::factory()->create();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    $cell->update(['is_active' => false]);
    $pallet = Pallet::factory()->for($product)->create(['cell_id' => $cell->id]);

    $response = $this->getJson("/api/v1/products/{$product->id}/pallets");

    $response->assertOk();
    expect(collect($response->json())->firstWhere('id', $pallet->id)['is_active'])->toBeFalse();
});

test('the pallet listing returns an empty array for a product with no current stock', function () {
    actingAsMobileUser();

    $product = Product::factory()->create();

    $response = $this->getJson("/api/v1/products/{$product->id}/pallets");

    $response->assertOk();
    expect($response->json())->toBe([]);
});

test('the pallet listing returns an empty array rather than a 404 for a deactivated product the warehouse no longer holds', function () {
    actingAsMobileUser();

    $product = Product::factory()->inactive()->create();
    // Noise: another product's pallet must not be listed.
    Pallet::factory()->create();

    $response = $this->getJson("/api/v1/products/{$product->id}/pallets");

    $response->assertOk();
    expect($response->json())->toBe([]);
});

test('the pallet listing 404s for a nonexistent product id', function () {
    actingAsMobileUser();

    $response = $this->getJson('/api/v1/products/999999/pallets');

    $response->assertNotFound();
});

test('an unauthenticated caller cannot list a product\'s pallets', function () {
    $product = Product::factory()->create();

    $response = $this->getJson("/api/v1/products/{$product->id}/pallets");

    $response->assertUnauthorized();
});

test('the product listings answer a repeat request that already holds the current data with a 304', function (string $path) {
    actingAsMobileUser();
    $product = Product::factory()->create();
    $url = str_replace('{id}', (string) $product->id, $path);

    $etag = $this->getJson($url)->assertOk()->headers->get('ETag');

    expect($etag)->not->toBeNull();

    $this->getJson($url, ['If-None-Match' => $etag])->assertNotModified();
})->with([
    'index' => '/api/v1/products',
    'show' => '/api/v1/products/{id}',
    'pallets' => '/api/v1/products/{id}/pallets',
]);

test('every pallet of a product names the warehouse it is stored in', function () {
    actingAsMobileUser();
    $product = Product::factory()->create();
    $north = Warehouse::factory()->create(['name' => 'North']);
    $south = Warehouse::factory()->create(['name' => 'South']);
    $inNorth = placedPalletIn($north, ['product_id' => $product->id, 'expiration_date' => '2026-09-01']);
    $inSouth = placedPalletIn($south, ['product_id' => $product->id, 'expiration_date' => '2026-09-02']);

    $response = $this->getJson("/api/v1/products/{$product->id}/pallets");

    $response->assertOk();
    $byId = collect($response->json())->keyBy('id');
    expect($byId[$inNorth->id]['warehouse'])->toBe(['id' => $north->id, 'name' => 'North'])
        ->and($byId[$inSouth->id]['warehouse'])->toBe(['id' => $south->id, 'name' => 'South']);
});

test('the pallet listing of a product keeps only the pallets of the warehouse_ids[] given, placed or unplaced', function () {
    actingAsMobileUser();
    $product = Product::factory()->create();
    $north = Warehouse::factory()->create();
    $south = Warehouse::factory()->create();
    $placedNorth = placedPalletIn($north, ['product_id' => $product->id, 'expiration_date' => '2026-09-01']);
    $unplacedNorth = unplacedPalletIn($north, ['product_id' => $product->id, 'expiration_date' => '2026-09-02']);
    placedPalletIn($south, ['product_id' => $product->id, 'expiration_date' => '2026-09-03']); // noise
    unplacedPalletIn($south, ['product_id' => $product->id, 'expiration_date' => '2026-09-04']); // noise
    placedPalletIn($north, ['expiration_date' => '2026-09-05']); // noise: another product

    $filtered = collect($this->getJson("/api/v1/products/{$product->id}/pallets?warehouse_ids[]={$north->id}")->assertOk()->json())->pluck('id')->all();
    $unfiltered = $this->getJson("/api/v1/products/{$product->id}/pallets")->assertOk()->json();

    expect($filtered)->toBe([$placedNorth->id, $unplacedNorth->id])
        ->and($unfiltered)->toHaveCount(4);
});

test('the pallet listing of a product rejects an unknown or malformed warehouse filter', function (string $query, string $field) {
    actingAsMobileUser();
    $product = Product::factory()->create();

    $this->getJson("/api/v1/products/{$product->id}/pallets?{$query}")->assertUnprocessable()->assertJsonValidationErrors([$field]);
})->with([
    'unknown warehouse' => ['warehouse_ids[]=999999', 'warehouse_ids.0'],
    'not an array' => ['warehouse_ids=1', 'warehouse_ids'],
    'not a number' => ['warehouse_ids[]=north', 'warehouse_ids.0'],
]);

test('unplaced pallets are listed as stock with placed false and no location, after the placed ones on an expiry and arrival tie', function () {
    actingAsMobileUser();
    $product = Product::factory()->boxesCount(20)->create();
    $warehouse = Warehouse::factory()->create(['name' => 'Annex']);
    $placed = placedPalletIn($warehouse, ['product_id' => $product->id, 'expiration_date' => '2026-09-05', 'remaining_boxes' => 9]);
    $unplaced = unplacedPalletIn($warehouse, ['product_id' => $product->id, 'expiration_date' => '2026-09-05', 'remaining_boxes' => 8]);
    $earlierUnplaced = unplacedPalletIn($warehouse, ['product_id' => $product->id, 'expiration_date' => '2026-09-01']);
    $undated = unplacedPalletIn($warehouse, ['product_id' => $product->id, 'expiration_date' => null]);
    // The placed and the unplaced pallet arrived at the same moment.
    backdate($placed, '2026-08-01 10:00:00');
    backdate($unplaced, '2026-08-01 10:00:00');
    backdate($earlierUnplaced, '2026-08-02 10:00:00');
    backdate($undated, '2026-08-03 10:00:00');

    $response = $this->getJson("/api/v1/products/{$product->id}/pallets");

    $response->assertOk();
    expect(collect($response->json())->pluck('id')->all())->toBe([$earlierUnplaced->id, $placed->id, $unplaced->id, $undated->id])
        ->and(collect($response->json())->firstWhere('id', $unplaced->id))->toEqual([
            'id' => $unplaced->id,
            'state' => null,
            'expiration_date' => '2026-09-05',
            'added_at' => '2026-08-01T10:00:00+00:00',
            'cell_entered_at' => null,
            'remaining_boxes' => 8,
            'product_boxes_count' => 20,
            'placed' => false,
            'location' => null,
            'is_active' => null,
            'note' => null,
            'warehouse' => ['id' => $warehouse->id, 'name' => 'Annex'],
        ]);
});

test('a product that is held only as unplaced pallets still lists them', function () {
    actingAsMobileUser();
    $product = Product::factory()->create();
    $pallet = unplacedPalletIn(Warehouse::firstOrCreateDefault(), ['product_id' => $product->id]);

    expect(collect($this->getJson("/api/v1/products/{$product->id}/pallets")->assertOk()->json())->pluck('id')->all())->toBe([$pallet->id]);
});
