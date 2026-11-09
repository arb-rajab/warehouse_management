<?php

use App\Models\Cell;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptFile;
use App\Models\ReceiptPallet;
use App\Models\Warehouse;

/**
 * One pallet as the mobile app writes it from the bill.
 *
 * @return array{product_id: int, boxes: int, expiration_date: string|null}
 */
function writtenPallet(Product $product, int $boxes = 5, ?string $expirationDate = null): array
{
    return ['product_id' => $product->id, 'boxes' => $boxes, 'expiration_date' => $expirationDate];
}

/**
 * A draft receipt with one bill file and the given pallets typed in order.
 *
 * @param  list<array{product_id: int, boxes: int, expiration_date: string|null}>  $pallets
 */
function draftReceiptWith(array $pallets, ?Warehouse $warehouse = null, int $files = 1): Receipt
{
    $receipt = Receipt::factory()->for($warehouse ?? Warehouse::firstOrCreateDefault())->create(['note' => null]);
    ReceiptFile::factory()->for($receipt)->count($files)->create();

    foreach ($pallets as $position => $pallet) {
        ReceiptPallet::factory()->for($receipt)->create([...$pallet, 'position' => $position]);
    }

    return $receipt;
}

// --- Access ------------------------------------------------------------------

test('a guest is refused every read endpoint of the receiving API', function (string $method, string $uri) {
    $receipt = Receipt::factory()->create();
    $file = ReceiptFile::factory()->for($receipt)->create();

    $this->json($method, str_replace(['{receipt}', '{file}'], [$receipt->id, $file->id], $uri))->assertUnauthorized();
})->with([
    'list' => ['GET', '/api/v1/receipts'],
    'show' => ['GET', '/api/v1/receipts/{receipt}'],
    'file download' => ['GET', '/api/v1/receipts/{receipt}/files/{file}'],
]);

test('a WMS user who is not an admin is refused every read endpoint of the receiving API', function (string $method, string $uri) {
    actingAsMobileUser();
    $receipt = Receipt::factory()->create();
    $file = ReceiptFile::factory()->for($receipt)->create();

    $this->json($method, str_replace(['{receipt}', '{file}'], [$receipt->id, $file->id], $uri))->assertForbidden();
})->with([
    'list' => ['GET', '/api/v1/receipts'],
    'show' => ['GET', '/api/v1/receipts/{receipt}'],
    'file download' => ['GET', '/api/v1/receipts/{receipt}/files/{file}'],
]);

test('creating a receipt is refused to a guest and to a non-admin, and creates nothing', function () {
    $payload = ['warehouse_id' => Warehouse::firstOrCreateDefault()->id];

    $this->postJson('/api/v1/receipts', $payload)->assertUnauthorized();

    actingAsMobileUser();
    $this->postJson('/api/v1/receipts', $payload)->assertForbidden();

    expect(Receipt::query()->count())->toBe(0);
});

test('replacing the pallets is refused to a guest and to a non-admin, and changes nothing', function () {
    $product = Product::factory()->create();
    $receipt = draftReceiptWith([writtenPallet($product, 3)]);
    $payload = ['pallets' => [], 'note' => 'changed'];

    $this->putJson("/api/v1/receipts/{$receipt->id}/pallets", $payload)->assertUnauthorized();

    actingAsMobileUser();
    $this->putJson("/api/v1/receipts/{$receipt->id}/pallets", $payload)->assertForbidden();

    expect($receipt->pallets()->count())->toBe(1)
        ->and($receipt->fresh()->note)->toBeNull();
});

test('finalizing is refused to a guest and to a non-admin, and changes nothing', function () {
    $receipt = draftReceiptWith([writtenPallet(Product::factory()->create())]);

    $this->postJson("/api/v1/receipts/{$receipt->id}/finalize")->assertUnauthorized();

    actingAsMobileUser();
    $this->postJson("/api/v1/receipts/{$receipt->id}/finalize")->assertForbidden();

    expect($receipt->fresh()->isFinalized())->toBeFalse()
        ->and(Pallet::query()->count())->toBe(0);
});

test('every receiving route is declared under role:admin', function () {
    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'api.v1.receipts.'));

    expect($routes)->toHaveCount(8);

    $routes->each(fn ($route) => expect($route->gatherMiddleware())->toContain('auth:sanctum', 'role:admin'));
});

// --- Listing -----------------------------------------------------------------

test('the receipt list carries every property of a receipt, without its files or pallets', function () {
    $admin = actingAsAdminMobileUser();
    $warehouse = Warehouse::factory()->create(['name' => 'Annex']);
    $receipt = Receipt::factory()->for($warehouse)->create(['note' => 'Dock 4', 'created_by' => $admin->id]);
    ReceiptFile::factory()->for($receipt)->count(2)->create();
    ReceiptPallet::factory()->for($receipt)->count(3)->create();

    $response = $this->getJson('/api/v1/receipts');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0'))->toEqual([
            'id' => $receipt->id,
            'warehouse' => ['id' => $warehouse->id, 'name' => 'Annex'],
            'note' => 'Dock 4',
            'status' => 'draft',
            'pallets_count' => 3,
            'files_count' => 2,
            'created_by' => ['id' => $admin->id, 'name' => $admin->name],
            'finalized_by' => null,
            'finalized_at' => null,
            'created_at' => $receipt->created_at->toIso8601String(),
        ]);
});

test('a finalized receipt in the list names who finalized it and when', function () {
    actingAsAdminMobileUser();
    $receipt = Receipt::factory()->finalized()->create();

    $row = $this->getJson('/api/v1/receipts')->assertOk()->json('data.0');

    expect($row['status'])->toBe('finalized')
        ->and($row['finalized_by'])->toEqual(['id' => $receipt->finalizedBy->id, 'name' => $receipt->finalizedBy->name])
        ->and($row['finalized_at'])->toBe($receipt->finalized_at->toIso8601String());
});

test('the receipt list counts each receipts own pallets and files, not its neighbours', function () {
    actingAsAdminMobileUser();
    $first = Receipt::factory()->create();
    $second = Receipt::factory()->create();
    ReceiptFile::factory()->for($first)->count(3)->create();
    ReceiptPallet::factory()->for($first)->count(1)->create();
    ReceiptFile::factory()->for($second)->count(1)->create();
    ReceiptPallet::factory()->for($second)->count(4)->create();

    $rows = collect($this->getJson('/api/v1/receipts')->assertOk()->json('data'))->keyBy('id');

    expect($rows[$first->id]['files_count'])->toBe(3)
        ->and($rows[$first->id]['pallets_count'])->toBe(1)
        ->and($rows[$second->id]['files_count'])->toBe(1)
        ->and($rows[$second->id]['pallets_count'])->toBe(4);
});

test('the receipt list is newest first, breaking a tie by the higher id', function () {
    actingAsAdminMobileUser();
    $oldest = backdate(Receipt::factory()->create(), '2026-11-01 09:00:00');
    $tiedFirst = backdate(Receipt::factory()->create(), '2026-11-05 09:00:00');
    $tiedSecond = backdate(Receipt::factory()->create(), '2026-11-05 09:00:00');
    $newest = backdate(Receipt::factory()->create(), '2026-11-08 09:00:00');

    $ids = collect($this->getJson('/api/v1/receipts')->assertOk()->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$newest->id, $tiedSecond->id, $tiedFirst->id, $oldest->id]);
});

test('the receipt list filters by warehouse_ids[], leaving other warehouses out', function () {
    actingAsAdminMobileUser();
    $north = Warehouse::factory()->create();
    $south = Warehouse::factory()->create();
    $inNorth = Receipt::factory()->for($north)->create();
    $inSouth = Receipt::factory()->for($south)->create();
    $inDefault = Receipt::factory()->create(); // noise

    $one = collect($this->getJson("/api/v1/receipts?warehouse_ids[]={$north->id}")->assertOk()->json('data'))->pluck('id')->all();
    $two = collect($this->getJson("/api/v1/receipts?warehouse_ids[]={$north->id}&warehouse_ids[]={$south->id}")->assertOk()->json('data'))->pluck('id')->sort()->values()->all();
    $all = collect($this->getJson('/api/v1/receipts')->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

    expect($one)->toBe([$inNorth->id])
        ->and($two)->toBe([$inNorth->id, $inSouth->id])
        ->and($all)->toBe([$inNorth->id, $inSouth->id, $inDefault->id]);
});

test('the receipt list filters by status', function () {
    actingAsAdminMobileUser();
    $draft = Receipt::factory()->create();
    $finalized = Receipt::factory()->finalized()->create();

    $drafts = collect($this->getJson('/api/v1/receipts?status=draft')->assertOk()->json('data'))->pluck('id')->all();
    $done = collect($this->getJson('/api/v1/receipts?status=finalized')->assertOk()->json('data'))->pluck('id')->all();
    $both = $this->getJson('/api/v1/receipts')->assertOk()->json('data');

    expect($drafts)->toBe([$draft->id])
        ->and($done)->toBe([$finalized->id])
        ->and($both)->toHaveCount(2);
});

test('the receipt list combines the warehouse and status filters', function () {
    actingAsAdminMobileUser();
    $north = Warehouse::factory()->create();
    $match = Receipt::factory()->for($north)->finalized()->create();
    Receipt::factory()->for($north)->create(); // noise: right warehouse, wrong status
    Receipt::factory()->finalized()->create(); // noise: right status, wrong warehouse

    $ids = collect($this->getJson("/api/v1/receipts?warehouse_ids[]={$north->id}&status=finalized")->assertOk()->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$match->id]);
});

test('the receipt list rejects an unknown status, an unknown warehouse and a malformed warehouse filter', function (string $query, string $field) {
    actingAsAdminMobileUser();
    Receipt::factory()->create();

    $this->getJson("/api/v1/receipts?{$query}")->assertUnprocessable()->assertJsonValidationErrors([$field]);
})->with([
    'unknown status' => ['status=archived', 'status'],
    'unknown warehouse' => ['warehouse_ids[]=999999', 'warehouse_ids.0'],
    'warehouse ids not an array' => ['warehouse_ids=1', 'warehouse_ids'],
]);

test('the receipt list paginates twenty per page and keeps the filters on the next link', function () {
    actingAsAdminMobileUser();
    Receipt::factory()->count(25)->create();

    $response = $this->getJson('/api/v1/receipts?status=draft');

    assertJsonListingPaginates($response, 25);
    assertNextLinkKeepsQuery($response, 'status=draft');

    expect(collect($this->getJson('/api/v1/receipts?status=draft&page=2')->json('data')))->toHaveCount(5);
});

test('the receipt list loads its relations eagerly, so many receipts by different users do not lazy load', function () {
    actingAsAdminMobileUser();
    Receipt::factory()->count(4)->finalized()->for(Warehouse::factory()->create())->create();
    Receipt::factory()->count(3)->create();

    $this->getJson('/api/v1/receipts')->assertOk()->assertJsonCount(7, 'data');
});

// --- Create ------------------------------------------------------------------

test('an admin creates a draft receipt with a note and the pallets typed from the bill, and gets its detail back', function () {
    $admin = actingAsAdminMobileUser();
    $warehouse = Warehouse::factory()->create(['name' => 'Annex']);
    $widgets = Product::factory()->imageUrl('https://cdn.example.com/widgets.png')->boxesCount(10)->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    $gadgets = Product::factory()->imageUrl(null)->boxesCount(20)->create(['name' => 'Gadgets', 'ar_name' => 'أدوات']);

    $response = $this->postJson('/api/v1/receipts', [
        'warehouse_id' => $warehouse->id,
        'note' => 'Truck arrived late',
        'pallets' => [
            ['product_id' => $gadgets->id, 'boxes' => 12, 'expiration_date' => '2027-03-04'],
            ['product_id' => $widgets->id, 'boxes' => 30],
            ['product_id' => $gadgets->id, 'boxes' => 7, 'expiration_date' => null],
        ],
    ]);

    $response->assertCreated();
    $receipt = Receipt::query()->sole();

    expect($response->json())->toEqual([
        'id' => $receipt->id,
        'warehouse' => ['id' => $warehouse->id, 'name' => 'Annex'],
        'note' => 'Truck arrived late',
        'status' => 'draft',
        'pallets_count' => 3,
        'files_count' => 0,
        'created_by' => ['id' => $admin->id, 'name' => $admin->name],
        'finalized_by' => null,
        'finalized_at' => null,
        'created_at' => $receipt->created_at->toIso8601String(),
        'files' => [],
        'pallets' => [
            ['product_id' => $gadgets->id, 'product' => ['id' => $gadgets->id, 'name' => 'Gadgets', 'ar_name' => 'أدوات', 'image_url' => null, 'boxes_count' => 20, 'active' => true], 'boxes' => 12, 'expiration_date' => '2027-03-04'],
            ['product_id' => $widgets->id, 'product' => ['id' => $widgets->id, 'name' => 'Widgets', 'ar_name' => 'ودجات', 'image_url' => 'https://cdn.example.com/widgets.png', 'boxes_count' => 10, 'active' => true], 'boxes' => 30, 'expiration_date' => null],
            ['product_id' => $gadgets->id, 'product' => ['id' => $gadgets->id, 'name' => 'Gadgets', 'ar_name' => 'أدوات', 'image_url' => null, 'boxes_count' => 20, 'active' => true], 'boxes' => 7, 'expiration_date' => null],
        ],
    ]);

    expect($receipt->warehouse_id)->toBe($warehouse->id)
        ->and($receipt->created_by)->toBe($admin->id)
        ->and($receipt->finalized_by)->toBeNull()
        ->and($receipt->pallets()->orderBy('position')->pluck('position')->all())->toBe([0, 1, 2])
        ->and($receipt->pallets()->orderBy('position')->pluck('boxes')->all())->toBe([12, 30, 7]);
});

test('creating a receipt records the pallets as entries only and creates no real pallet', function () {
    actingAsAdminMobileUser();
    $product = Product::factory()->create();

    $this->postJson('/api/v1/receipts', [
        'warehouse_id' => Warehouse::firstOrCreateDefault()->id,
        'pallets' => [writtenPallet($product, 4), writtenPallet($product, 6)],
    ])->assertCreated();

    expect(Pallet::query()->count())->toBe(0)
        ->and(ReceiptPallet::query()->whereNotNull('pallet_id')->count())->toBe(0);
});

test('a receipt can be created with only a warehouse', function () {
    actingAsAdminMobileUser();

    $response = $this->postJson('/api/v1/receipts', ['warehouse_id' => Warehouse::firstOrCreateDefault()->id]);

    $response->assertCreated();
    expect($response->json('note'))->toBeNull()
        ->and($response->json('pallets'))->toBe([])
        ->and($response->json('files'))->toBe([])
        ->and($response->json('pallets_count'))->toBe(0)
        ->and(Receipt::query()->sole()->status->value)->toBe('draft');
});

test('creating a receipt accepts a past expiry and normalizes a datetime to a bare date', function () {
    actingAsAdminMobileUser();
    $product = Product::factory()->create();

    $response = $this->postJson('/api/v1/receipts', [
        'warehouse_id' => Warehouse::firstOrCreateDefault()->id,
        'pallets' => [
            ['product_id' => $product->id, 'boxes' => 1, 'expiration_date' => '2020-01-31'],
            ['product_id' => $product->id, 'boxes' => 1, 'expiration_date' => '2027-06-15 23:59:59'],
        ],
    ]);

    $response->assertCreated();
    expect(array_column($response->json('pallets'), 'expiration_date'))->toBe(['2020-01-31', '2027-06-15'])
        ->and(ReceiptPallet::query()->orderBy('position')->get()->map(fn (ReceiptPallet $entry) => $entry->expiration_date->toDateString())->all())->toBe(['2020-01-31', '2027-06-15']);
});

test('a product that is no longer active can still be received, as pallet store accepts it', function () {
    actingAsAdminMobileUser();
    $inactive = Product::factory()->inactive()->create();

    $this->postJson('/api/v1/receipts', [
        'warehouse_id' => Warehouse::firstOrCreateDefault()->id,
        'pallets' => [writtenPallet($inactive)],
    ])->assertCreated()->assertJsonPath('pallets.0.product.active', false);
});

test('creating a receipt is refused for a missing or unknown warehouse, and creates nothing', function (array $payload, string $field) {
    actingAsAdminMobileUser();

    $this->postJson('/api/v1/receipts', $payload)->assertUnprocessable()->assertJsonValidationErrors([$field]);

    expect(Receipt::query()->count())->toBe(0);
})->with([
    'no warehouse' => [[], 'warehouse_id'],
    'unknown warehouse' => [['warehouse_id' => 999999], 'warehouse_id'],
    'not a number' => [['warehouse_id' => 'north'], 'warehouse_id'],
    'note too long' => [['warehouse_id' => 1, 'note' => str_repeat('a', 1001)], 'note'],
    'note not text' => [['warehouse_id' => 1, 'note' => ['x']], 'note'],
]);

test('creating a receipt rejects a malformed pallet entry, by the field that is wrong, and creates nothing', function (array $entry, string $field) {
    actingAsAdminMobileUser();
    $product = Product::factory()->create();
    $entry = array_map(fn ($value) => $value === 'PRODUCT' ? $product->id : $value, $entry);

    $this->postJson('/api/v1/receipts', [
        'warehouse_id' => Warehouse::firstOrCreateDefault()->id,
        'pallets' => [['product_id' => $product->id, 'boxes' => 2], $entry],
    ])->assertUnprocessable()->assertJsonValidationErrors([$field]);

    expect(Receipt::query()->count())->toBe(0)
        ->and(ReceiptPallet::query()->count())->toBe(0);
})->with([
    'no product' => [['boxes' => 3], 'pallets.1.product_id'],
    'unknown product' => [['product_id' => 999999, 'boxes' => 3], 'pallets.1.product_id'],
    'product not a number' => [['product_id' => 'abc', 'boxes' => 3], 'pallets.1.product_id'],
    'no boxes' => [['product_id' => 'PRODUCT'], 'pallets.1.boxes'],
    'zero boxes' => [['product_id' => 'PRODUCT', 'boxes' => 0], 'pallets.1.boxes'],
    'negative boxes' => [['product_id' => 'PRODUCT', 'boxes' => -4], 'pallets.1.boxes'],
    'fractional boxes' => [['product_id' => 'PRODUCT', 'boxes' => 1.5], 'pallets.1.boxes'],
    'too many boxes' => [['product_id' => 'PRODUCT', 'boxes' => 100001], 'pallets.1.boxes'],
    'invalid expiry' => [['product_id' => 'PRODUCT', 'boxes' => 3, 'expiration_date' => 'next week'], 'pallets.1.expiration_date'],
]);

test('creating a receipt rejects pallets that are not a list of entries', function () {
    actingAsAdminMobileUser();
    $warehouseId = Warehouse::firstOrCreateDefault()->id;

    $this->postJson('/api/v1/receipts', ['warehouse_id' => $warehouseId, 'pallets' => 'many'])
        ->assertUnprocessable()->assertJsonValidationErrors(['pallets']);
    $this->postJson('/api/v1/receipts', ['warehouse_id' => $warehouseId, 'pallets' => ['not an entry']])
        ->assertUnprocessable()->assertJsonValidationErrors(['pallets.0']);

    expect(Receipt::query()->count())->toBe(0);
});

test('creating a receipt rejects more than five hundred pallets', function () {
    actingAsAdminMobileUser();
    $product = Product::factory()->create();

    $this->postJson('/api/v1/receipts', [
        'warehouse_id' => Warehouse::firstOrCreateDefault()->id,
        'pallets' => array_fill(0, 501, writtenPallet($product, 1)),
    ])->assertUnprocessable()->assertJsonValidationErrors(['pallets']);

    expect(Receipt::query()->count())->toBe(0);
});

// --- Detail ------------------------------------------------------------------

test('a receipt detail carries the files with their download url and the pallets in typed order, and no storage path or pallet id', function () {
    actingAsAdminMobileUser();
    $warehouse = Warehouse::factory()->create(['name' => 'Annex']);
    $widgets = Product::factory()->imageUrl(null)->boxesCount(10)->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    $gadgets = Product::factory()->imageUrl(null)->boxesCount(20)->create(['name' => 'Gadgets', 'ar_name' => 'أدوات']);
    $receipt = draftReceiptWith([], $warehouse, files: 0);
    $receipt->update(['note' => 'Dock 4']);
    $photo = ReceiptFile::factory()->for($receipt)->create(['original_name' => 'front.jpg', 'size' => 2048]);
    $pdf = ReceiptFile::factory()->for($receipt)->pdf()->create(['original_name' => 'back.pdf', 'size' => 4096]);
    // Inserted out of order: position decides, not the row id.
    ReceiptPallet::factory()->for($receipt)->create(['product_id' => $gadgets->id, 'boxes' => 7, 'expiration_date' => null, 'position' => 1]);
    ReceiptPallet::factory()->for($receipt)->create(['product_id' => $widgets->id, 'boxes' => 12, 'expiration_date' => '2027-03-04', 'position' => 0]);

    $response = $this->getJson("/api/v1/receipts/{$receipt->id}");

    $response->assertOk();
    expect($response->json())->toEqual([
        'id' => $receipt->id,
        'warehouse' => ['id' => $warehouse->id, 'name' => 'Annex'],
        'note' => 'Dock 4',
        'status' => 'draft',
        'pallets_count' => 2,
        'files_count' => 2,
        'created_by' => ['id' => $receipt->created_by, 'name' => $receipt->createdBy->name],
        'finalized_by' => null,
        'finalized_at' => null,
        'created_at' => $receipt->created_at->toIso8601String(),
        'files' => [
            ['id' => $photo->id, 'original_name' => 'front.jpg', 'mime_type' => 'image/jpeg', 'size' => 2048, 'url' => route('api.v1.receipts.files.show', ['receipt' => $receipt->id, 'file' => $photo->id]), 'created_at' => $photo->created_at->toIso8601String()],
            ['id' => $pdf->id, 'original_name' => 'back.pdf', 'mime_type' => 'application/pdf', 'size' => 4096, 'url' => route('api.v1.receipts.files.show', ['receipt' => $receipt->id, 'file' => $pdf->id]), 'created_at' => $pdf->created_at->toIso8601String()],
        ],
        'pallets' => [
            ['product_id' => $widgets->id, 'product' => ['id' => $widgets->id, 'name' => 'Widgets', 'ar_name' => 'ودجات', 'image_url' => null, 'boxes_count' => 10, 'active' => true], 'boxes' => 12, 'expiration_date' => '2027-03-04'],
            ['product_id' => $gadgets->id, 'product' => ['id' => $gadgets->id, 'name' => 'Gadgets', 'ar_name' => 'أدوات', 'image_url' => null, 'boxes_count' => 20, 'active' => true], 'boxes' => 7, 'expiration_date' => null],
        ],
    ]);

    $body = $response->getContent();
    expect($body)->not->toContain('"path"')->not->toContain('receipt-bills');
});

test('a receipt detail does not show another receipts files or pallets', function () {
    actingAsAdminMobileUser();
    $product = Product::factory()->create();
    $receipt = draftReceiptWith([writtenPallet($product, 3)]);
    draftReceiptWith([writtenPallet($product, 9), writtenPallet($product, 8)], files: 3); // noise

    $response = $this->getJson("/api/v1/receipts/{$receipt->id}")->assertOk();

    expect($response->json('files'))->toHaveCount(1)
        ->and($response->json('pallets'))->toHaveCount(1)
        ->and($response->json('pallets.0.boxes'))->toBe(3);
});

test('a finalized receipt detail names who finalized it and still carries no pallet ids', function () {
    $admin = actingAsAdminMobileUser();
    $product = Product::factory()->create();
    $receipt = draftReceiptWith([writtenPallet($product, 3), writtenPallet($product, 4)]);

    $this->postJson("/api/v1/receipts/{$receipt->id}/finalize")->assertOk();
    $response = $this->getJson("/api/v1/receipts/{$receipt->id}")->assertOk();

    $createdPalletIds = Pallet::query()->pluck('id')->all();

    expect($response->json('status'))->toBe('finalized')
        ->and($response->json('finalized_by'))->toEqual(['id' => $admin->id, 'name' => $admin->name])
        ->and($response->json('finalized_at'))->not->toBeNull();

    foreach ($response->json('pallets') as $entry) {
        expect(array_keys($entry))->toBe(['product_id', 'product', 'boxes', 'expiration_date']);
    }

    foreach ($createdPalletIds as $palletId) {
        expect($response->getContent())->not->toContain("\"pallet_id\":{$palletId}");
    }
});

test('showing a receipt that does not exist is a 404', function () {
    actingAsAdminMobileUser();

    $this->getJson('/api/v1/receipts/999999')->assertNotFound();
});

// --- Replace the pallet list -------------------------------------------------

test('replacing the pallets swaps the whole list for the one sent, in the order sent, leaving other receipts alone', function () {
    actingAsAdminMobileUser();
    $old = Product::factory()->create();
    $new = Product::factory()->create();
    $receipt = draftReceiptWith([writtenPallet($old, 1), writtenPallet($old, 2)]);
    $other = draftReceiptWith([writtenPallet($old, 99)]); // noise

    $response = $this->putJson("/api/v1/receipts/{$receipt->id}/pallets", [
        'pallets' => [writtenPallet($new, 30, '2027-01-01'), writtenPallet($old, 20), writtenPallet($new, 10)],
    ]);

    $response->assertOk();
    expect(collect($response->json('pallets'))->map(fn (array $entry) => [$entry['product_id'], $entry['boxes'], $entry['expiration_date']])->all())
        ->toBe([[$new->id, 30, '2027-01-01'], [$old->id, 20, null], [$new->id, 10, null]])
        ->and($response->json('pallets_count'))->toBe(3)
        ->and($receipt->pallets()->orderBy('position')->pluck('position')->all())->toBe([0, 1, 2])
        ->and($other->pallets()->count())->toBe(1)
        ->and($other->pallets()->value('boxes'))->toBe(99);
});

test('sending an empty list clears the pallets', function () {
    actingAsAdminMobileUser();
    $receipt = draftReceiptWith([writtenPallet(Product::factory()->create()), writtenPallet(Product::factory()->create())]);

    $response = $this->putJson("/api/v1/receipts/{$receipt->id}/pallets", ['pallets' => []]);

    $response->assertOk();
    expect($response->json('pallets'))->toBe([])
        ->and($response->json('pallets_count'))->toBe(0)
        ->and($receipt->pallets()->count())->toBe(0);
});

test('the pallet list must be sent, even when empty', function () {
    actingAsAdminMobileUser();
    $receipt = draftReceiptWith([writtenPallet(Product::factory()->create())]);

    $this->putJson("/api/v1/receipts/{$receipt->id}/pallets", ['note' => 'only a note'])
        ->assertUnprocessable()->assertJsonValidationErrors(['pallets']);

    expect($receipt->pallets()->count())->toBe(1)
        ->and($receipt->fresh()->note)->toBeNull();
});

test('replacing the pallets sets the note when sent, clears it when null, and keeps it when omitted', function () {
    actingAsAdminMobileUser();
    $receipt = draftReceiptWith([]);
    $receipt->update(['note' => 'first']);
    $url = "/api/v1/receipts/{$receipt->id}/pallets";

    $this->putJson($url, ['pallets' => [], 'note' => 'second'])->assertOk()->assertJsonPath('note', 'second');
    expect($receipt->fresh()->note)->toBe('second');

    $this->putJson($url, ['pallets' => []])->assertOk()->assertJsonPath('note', 'second');
    expect($receipt->fresh()->note)->toBe('second');

    $this->putJson($url, ['pallets' => [], 'note' => null])->assertOk()->assertJsonPath('note', null);
    expect($receipt->fresh()->note)->toBeNull();
});

test('replacing the pallets accepts a past expiry', function () {
    actingAsAdminMobileUser();
    $product = Product::factory()->create();
    $receipt = draftReceiptWith([]);

    $this->putJson("/api/v1/receipts/{$receipt->id}/pallets", ['pallets' => [writtenPallet($product, 2, '2019-05-05')]])
        ->assertOk()->assertJsonPath('pallets.0.expiration_date', '2019-05-05');
});

test('replacing the pallets of a finalized receipt is refused with 409 receipt_finalized and changes nothing', function () {
    actingAsAdminMobileUser();
    $product = Product::factory()->create();
    $receipt = draftReceiptWith([writtenPallet($product, 3)]);
    $this->postJson("/api/v1/receipts/{$receipt->id}/finalize")->assertOk();

    $this->putJson("/api/v1/receipts/{$receipt->id}/pallets", ['pallets' => [], 'note' => 'late'])
        ->assertConflict()
        ->assertExactJson(['message' => __('messages.receipt_finalized'), 'error_code' => 'receipt_finalized']);

    expect($receipt->pallets()->count())->toBe(1)
        ->and($receipt->fresh()->note)->toBeNull()
        ->and(Pallet::query()->count())->toBe(1);
});

test('replacing the pallets rejects a malformed entry and keeps the earlier list', function (array $entry, string $field) {
    actingAsAdminMobileUser();
    $product = Product::factory()->create();
    $receipt = draftReceiptWith([writtenPallet($product, 3)]);
    $entry = array_map(fn ($value) => $value === 'PRODUCT' ? $product->id : $value, $entry);

    $this->putJson("/api/v1/receipts/{$receipt->id}/pallets", ['pallets' => [$entry]])
        ->assertUnprocessable()->assertJsonValidationErrors([$field]);

    expect($receipt->pallets()->count())->toBe(1)
        ->and($receipt->pallets()->value('boxes'))->toBe(3);
})->with([
    'no product' => [['boxes' => 3], 'pallets.0.product_id'],
    'unknown product' => [['product_id' => 999999, 'boxes' => 3], 'pallets.0.product_id'],
    'zero boxes' => [['product_id' => 'PRODUCT', 'boxes' => 0], 'pallets.0.boxes'],
    'invalid expiry' => [['product_id' => 'PRODUCT', 'boxes' => 3, 'expiration_date' => 'soon'], 'pallets.0.expiration_date'],
]);

test('replacing the pallets of a receipt that does not exist is a 404', function () {
    actingAsAdminMobileUser();

    $this->putJson('/api/v1/receipts/999999/pallets', ['pallets' => []])->assertNotFound();
});

// --- Finalize ----------------------------------------------------------------

test('finalizing creates exactly one unplaced pallet per entry, links each entry to its pallet and freezes the receipt', function () {
    $admin = actingAsAdminMobileUser();
    $warehouse = Warehouse::factory()->create(['name' => 'Annex']);
    $widgets = Product::factory()->boxesCount(10)->create();
    $gadgets = Product::factory()->boxesCount(20)->create();
    $receipt = draftReceiptWith([
        writtenPallet($gadgets, 12, '2027-03-04'),
        writtenPallet($widgets, 30),
        writtenPallet($gadgets, 7, '2020-01-01'),
    ], $warehouse);

    $response = $this->postJson("/api/v1/receipts/{$receipt->id}/finalize");

    $response->assertOk();
    expect($response->json('status'))->toBe('finalized')
        ->and($response->json('finalized_by'))->toEqual(['id' => $admin->id, 'name' => $admin->name])
        ->and($response->json('finalized_at'))->not->toBeNull()
        ->and($response->json('pallets_count'))->toBe(3)
        ->and($response->json('files_count'))->toBe(1)
        ->and($response->json('warehouse'))->toEqual(['id' => $warehouse->id, 'name' => 'Annex']);

    $pallets = Pallet::query()->orderBy('id')->get();
    expect($pallets)->toHaveCount(3)
        ->and($pallets->pluck('product_id')->all())->toBe([$gadgets->id, $widgets->id, $gadgets->id])
        ->and($pallets->pluck('remaining_boxes')->all())->toBe([12, 30, 7])
        ->and($pallets->map(fn (Pallet $pallet) => $pallet->expiration_date?->toDateString())->all())->toBe(['2027-03-04', null, '2020-01-01'])
        ->and($pallets->pluck('receipt_id')->unique()->all())->toBe([$receipt->id])
        ->and($pallets->pluck('cell_id')->filter()->all())->toBe([])
        ->and(Cell::query()->count())->toBe(0);

    $fresh = $receipt->fresh();
    expect($fresh->isFinalized())->toBeTrue()
        ->and($fresh->finalized_by)->toBe($admin->id)
        ->and($fresh->finalized_at)->not->toBeNull()
        ->and($receipt->pallets()->orderBy('position')->pluck('pallet_id')->all())->toBe($pallets->pluck('id')->all());
});

test('the pallets a receipt created belong to its warehouse in the unplaced listing, and to no other', function () {
    actingAsAdminMobileUser();
    $north = Warehouse::factory()->create();
    $south = Warehouse::factory()->create();
    $receipt = draftReceiptWith([writtenPallet(Product::factory()->create(), 5)], $north);

    $this->postJson("/api/v1/receipts/{$receipt->id}/finalize")->assertOk();

    $inNorth = $this->getJson("/api/v1/pallets/unplaced?warehouse_ids[]={$north->id}")->assertOk();
    $inSouth = $this->getJson("/api/v1/pallets/unplaced?warehouse_ids[]={$south->id}")->assertOk();

    expect($inNorth->json('data'))->toHaveCount(1)
        ->and($inNorth->json('data.0.id'))->toBe(Pallet::query()->sole()->id)
        ->and($inNorth->json('data.0.warehouse.id'))->toBe($north->id)
        ->and($inSouth->json('data'))->toBe([]);
});

test('finalizing is refused with receipt_has_no_files while the bill has no file, and creates nothing', function () {
    actingAsAdminMobileUser();
    $receipt = draftReceiptWith([writtenPallet(Product::factory()->create())], files: 0);

    $this->postJson("/api/v1/receipts/{$receipt->id}/finalize")
        ->assertUnprocessable()
        ->assertExactJson(['message' => __('messages.receipt_has_no_files'), 'error_code' => 'receipt_has_no_files']);

    expect($receipt->fresh()->isFinalized())->toBeFalse()
        ->and(Pallet::query()->count())->toBe(0);
});

test('finalizing is refused with receipt_has_no_pallets while no pallet is recorded, and creates nothing', function () {
    actingAsAdminMobileUser();
    $receipt = draftReceiptWith([]);

    $this->postJson("/api/v1/receipts/{$receipt->id}/finalize")
        ->assertUnprocessable()
        ->assertExactJson(['message' => __('messages.receipt_has_no_pallets'), 'error_code' => 'receipt_has_no_pallets']);

    expect($receipt->fresh()->isFinalized())->toBeFalse()
        ->and($receipt->fresh()->finalized_by)->toBeNull();
});

test('a receipt with neither file nor pallets is refused for its missing file first', function () {
    actingAsAdminMobileUser();
    $receipt = draftReceiptWith([], files: 0);

    $this->postJson("/api/v1/receipts/{$receipt->id}/finalize")
        ->assertUnprocessable()->assertJsonPath('error_code', 'receipt_has_no_files');
});

test('finalizing an already finalized receipt is refused with 409 receipt_finalized and creates no second set of pallets', function () {
    actingAsAdminMobileUser();
    $receipt = draftReceiptWith([writtenPallet(Product::factory()->create(), 5), writtenPallet(Product::factory()->create(), 6)]);
    $this->postJson("/api/v1/receipts/{$receipt->id}/finalize")->assertOk();
    $finalizedAt = $receipt->fresh()->finalized_at;

    $this->postJson("/api/v1/receipts/{$receipt->id}/finalize")
        ->assertConflict()
        ->assertExactJson(['message' => __('messages.receipt_finalized'), 'error_code' => 'receipt_finalized']);

    expect(Pallet::query()->count())->toBe(2)
        ->and($receipt->fresh()->finalized_at->equalTo($finalizedAt))->toBeTrue();
});

test('finalizing a receipt that does not exist is a 404', function () {
    actingAsAdminMobileUser();

    $this->postJson('/api/v1/receipts/999999/finalize')->assertNotFound();
});

test('finalizing one receipt leaves another draft receipts entries without pallets', function () {
    actingAsAdminMobileUser();
    $product = Product::factory()->create();
    $receipt = draftReceiptWith([writtenPallet($product, 5)]);
    $other = draftReceiptWith([writtenPallet($product, 6)]); // noise

    $this->postJson("/api/v1/receipts/{$receipt->id}/finalize")->assertOk();

    expect($other->fresh()->isFinalized())->toBeFalse()
        ->and($other->pallets()->whereNotNull('pallet_id')->count())->toBe(0)
        ->and(Pallet::query()->count())->toBe(1);
});

test('finalizing is atomic: a failure after the first pallet leaves no pallet and a draft', function () {
    actingAsAdminMobileUser();
    $product = Product::factory()->create();
    $receipt = draftReceiptWith([writtenPallet($product, 1), writtenPallet($product, 2), writtenPallet($product, 3)]);

    $created = 0;
    Pallet::creating(function () use (&$created): void {
        if (++$created === 2) {
            throw new RuntimeException('Simulated failure while creating the second pallet.');
        }
    });

    $this->postJson("/api/v1/receipts/{$receipt->id}/finalize")->assertStatus(500);

    expect($created)->toBe(2)
        ->and(Pallet::query()->count())->toBe(0)
        ->and($receipt->fresh()->isFinalized())->toBeFalse()
        ->and($receipt->fresh()->finalized_by)->toBeNull()
        ->and($receipt->fresh()->finalized_at)->toBeNull()
        ->and($receipt->pallets()->whereNotNull('pallet_id')->count())->toBe(0)
        ->and($receipt->pallets()->count())->toBe(3);
});

test('a draft can still be finalized after a failed attempt', function () {
    actingAsAdminMobileUser();
    $receipt = draftReceiptWith([writtenPallet(Product::factory()->create(), 1)]);
    $attempts = 0;
    Pallet::creating(function () use (&$attempts): void {
        if (++$attempts === 1) {
            throw new RuntimeException('Simulated first-attempt failure.');
        }
    });

    $this->postJson("/api/v1/receipts/{$receipt->id}/finalize")->assertStatus(500);
    $this->postJson("/api/v1/receipts/{$receipt->id}/finalize")->assertOk();

    expect(Pallet::query()->count())->toBe(1)
        ->and($receipt->fresh()->isFinalized())->toBeTrue();
});
