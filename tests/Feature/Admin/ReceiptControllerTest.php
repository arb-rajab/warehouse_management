<?php

use App\Models\Pallet;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptFile;
use App\Models\ReceiptPallet;
use App\Models\Warehouse;
use Inertia\Testing\AssertableInertia as Assert;

// --- Access --------------------------------------------------------------------

test('the receipts screen can be rendered', function () {
    actingAsAdmin();

    $this->get('/admin/receipts')->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Receipts/Index')
    );
});

test('the receipt screen can be rendered', function () {
    actingAsAdmin();
    $receipt = Receipt::factory()->create();

    $this->get("/admin/receipts/{$receipt->id}")->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Receipts/Show')
    );
});

test('an unauthenticated caller is redirected to login when viewing the receipts', function (string $uri) {
    $receipt = Receipt::factory()->create();

    $this->get(str_replace('{receipt}', (string) $receipt->id, $uri))->assertRedirect(route('login'));
})->with(['list' => '/admin/receipts', 'detail' => '/admin/receipts/{receipt}']);

test('a mobile app user cannot view the receipts', function (string $uri) {
    actingAsMobilePanelUser();
    $receipt = Receipt::factory()->create();

    $this->get(str_replace('{receipt}', (string) $receipt->id, $uri))->assertForbidden();
})->with(['list' => '/admin/receipts', 'detail' => '/admin/receipts/{receipt}']);

test('the admin receipts pages offer no way to change a receipt', function (string $method, string $uri) {
    actingAsAdmin();
    $receipt = Receipt::factory()->create();

    $this->call($method, str_replace('{receipt}', (string) $receipt->id, $uri))->assertStatus(405);

    expect(Receipt::query()->count())->toBe(1);
})->with([
    'create' => ['POST', '/admin/receipts'],
    'update' => ['PUT', '/admin/receipts/{receipt}'],
    'delete' => ['DELETE', '/admin/receipts/{receipt}'],
]);

test('viewing a receipt that does not exist is a 404', function () {
    actingAsAdmin();

    $this->get('/admin/receipts/999999')->assertNotFound();
});

// --- List ----------------------------------------------------------------------

test('the receipt list shows every property the table renders for a receipt', function () {
    $admin = actingAsAdmin();
    $warehouse = Warehouse::factory()->create(['name' => 'Annex']);
    $receipt = Receipt::factory()->for($warehouse)->create(['note' => 'Dock 4', 'created_by' => $admin->id]);
    ReceiptFile::factory()->for($receipt)->count(2)->create();
    ReceiptPallet::factory()->for($receipt)->count(3)->create();

    $this->get('/admin/receipts')->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Receipts/Index')
            ->has('receipts.data', 1)
            ->has('receipts.data.0', fn (Assert $row) => $row
                ->where('id', $receipt->id)
                ->where('warehouse', ['id' => $warehouse->id, 'name' => 'Annex'])
                ->where('note', 'Dock 4')
                ->where('status', 'draft')
                ->where('pallets_count', 3)
                ->where('files_count', 2)
                ->where('created_by', ['id' => $admin->id, 'name' => $admin->name])
                ->where('finalized_by', null)
                ->where('finalized_at', null)
                ->where('created_at', $receipt->created_at->toIso8601String())
            )
            ->where('filters', ['per_page' => 20])
            ->where('filterOptions.warehouses', [])
    );
});

test('a finalized receipt in the list names who finalized it and when', function () {
    actingAsAdmin();
    $receipt = Receipt::factory()->finalized()->create();

    $this->get('/admin/receipts')->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('receipts.data.0.status', 'finalized')
            ->where('receipts.data.0.finalized_by', ['id' => $receipt->finalizedBy->id, 'name' => $receipt->finalizedBy->name])
            ->where('receipts.data.0.finalized_at', $receipt->finalized_at->toIso8601String())
    );
});

test('the receipt list counts each receipts own pallets and files', function () {
    actingAsAdmin();
    $first = backdate(Receipt::factory()->create(), '2026-11-02 10:00:00');
    $second = backdate(Receipt::factory()->create(), '2026-11-01 10:00:00');
    ReceiptFile::factory()->for($first)->count(3)->create();
    ReceiptPallet::factory()->for($first)->count(1)->create();
    ReceiptFile::factory()->for($second)->count(1)->create();
    ReceiptPallet::factory()->for($second)->count(4)->create();

    $this->get('/admin/receipts')->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('receipts.data.0.files_count', 3)
            ->where('receipts.data.0.pallets_count', 1)
            ->where('receipts.data.1.files_count', 1)
            ->where('receipts.data.1.pallets_count', 4)
    );
});

test('the receipt list is newest first by default and sort_direction=asc reverses it', function () {
    actingAsAdmin();
    $oldest = backdate(Receipt::factory()->create(), '2026-11-01 09:00:00');
    $tiedFirst = backdate(Receipt::factory()->create(), '2026-11-05 09:00:00');
    $tiedSecond = backdate(Receipt::factory()->create(), '2026-11-05 09:00:00');
    $newest = backdate(Receipt::factory()->create(), '2026-11-08 09:00:00');

    $idsOf = fn ($response) => array_column($response->inertiaProps('receipts.data'), 'id');

    $default = $this->get('/admin/receipts')->assertOk();
    $descending = $this->get('/admin/receipts?sort_direction=desc')->assertOk();
    $ascending = $this->get('/admin/receipts?sort_direction=asc')->assertOk();

    expect($idsOf($default))->toBe([$newest->id, $tiedSecond->id, $tiedFirst->id, $oldest->id])
        ->and($idsOf($descending))->toBe([$newest->id, $tiedSecond->id, $tiedFirst->id, $oldest->id])
        ->and($idsOf($ascending))->toBe([$oldest->id, $tiedFirst->id, $tiedSecond->id, $newest->id]);
    $ascending->assertInertia(fn (Assert $page) => $page->where('filters.sort_direction', 'asc'));
});

test('the receipt list filters by warehouse_ids[] and echoes only the selected warehouses as options', function () {
    actingAsAdmin();
    $north = Warehouse::factory()->create(['name' => 'North']);
    $south = Warehouse::factory()->create(['name' => 'South']);
    $inNorth = Receipt::factory()->for($north)->create();
    Receipt::factory()->for($south)->create(); // noise
    Receipt::factory()->create(); // noise

    $this->get("/admin/receipts?warehouse_ids[]={$north->id}")->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('receipts.data', 1)
            ->where('receipts.data.0.id', $inNorth->id)
            ->where('filters.warehouse_ids', [(string) $north->id])
            ->where('filterOptions.warehouses', [['id' => $north->id, 'name' => 'North']])
    );
});

test('the receipt list filters by status', function () {
    actingAsAdmin();
    $draft = Receipt::factory()->create();
    $finalized = Receipt::factory()->finalized()->create();

    $this->get('/admin/receipts?status=draft')->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('receipts.data', 1)->where('receipts.data.0.id', $draft->id)->where('filters.status', 'draft')
    );
    $this->get('/admin/receipts?status=finalized')->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('receipts.data', 1)->where('receipts.data.0.id', $finalized->id)
    );
    $this->get('/admin/receipts')->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('receipts.data', 2)
    );
});

test('the receipt list combines the warehouse and status filters', function () {
    actingAsAdmin();
    $north = Warehouse::factory()->create();
    $match = Receipt::factory()->for($north)->finalized()->create();
    Receipt::factory()->for($north)->create(); // noise
    Receipt::factory()->finalized()->create(); // noise

    $this->get("/admin/receipts?warehouse_ids[]={$north->id}&status=finalized")->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('receipts.data', 1)->where('receipts.data.0.id', $match->id)
    );
});

test('the receipt list respects a per_page query parameter', function () {
    actingAsAdmin();
    Receipt::factory()->count(12)->create();

    $this->get('/admin/receipts?per_page=10')->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('receipts.data', 10)->where('receipts.meta.total', 12)->where('filters.per_page', 10)
    );
});

test('the receipt list rejects a status, sort direction, per_page or warehouse that is not allowed', function (string $query, string $field) {
    actingAsAdmin();

    $this->get("/admin/receipts?{$query}")->assertSessionHasErrors($field);
})->with([
    'status' => ['status=archived', 'status'],
    'sort direction' => ['sort_direction=sideways', 'sort_direction'],
    'per page' => ['per_page=999', 'per_page'],
    'warehouse' => ['warehouse_ids[]=999999', 'warehouse_ids.0'],
]);

test('the receipt list paginates instead of returning every receipt at once', function () {
    actingAsAdmin();
    Receipt::factory()->count(25)->create();

    assertInertiaPaginates($this->get('/admin/receipts'), 'receipts', 20, 25, 'Admin/Receipts/Index');
});

test('the receipt list loads its relations eagerly, so many receipts by different users do not lazy load', function () {
    actingAsAdmin();
    Receipt::factory()->count(4)->finalized()->for(Warehouse::factory()->create())->create();
    Receipt::factory()->count(3)->create();

    $this->get('/admin/receipts')->assertOk()->assertInertia(fn (Assert $page) => $page->has('receipts.data', 7));
});

// --- Detail --------------------------------------------------------------------

test('a draft receipt shows its files with their admin download url and its pallets in typed order, with no storage path or id of a pallet or entry', function () {
    $admin = actingAsAdmin();
    $warehouse = Warehouse::factory()->create(['name' => 'Annex']);
    $widgets = Product::factory()->imageUrl(null)->boxesCount(10)->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    $gadgets = Product::factory()->imageUrl(null)->boxesCount(20)->create(['name' => 'Gadgets', 'ar_name' => 'أدوات']);
    $receipt = Receipt::factory()->for($warehouse)->create(['note' => 'Dock 4', 'created_by' => $admin->id]);
    $photo = ReceiptFile::factory()->for($receipt)->create(['original_name' => 'front.jpg', 'size' => 2048]);
    $pdf = ReceiptFile::factory()->for($receipt)->pdf()->create(['original_name' => 'back.pdf', 'size' => 4096]);
    // Inserted out of order: position decides, not the row id.
    ReceiptPallet::factory()->for($receipt)->create(['product_id' => $gadgets->id, 'boxes' => 7, 'expiration_date' => null, 'position' => 1]);
    ReceiptPallet::factory()->for($receipt)->create(['product_id' => $widgets->id, 'boxes' => 12, 'expiration_date' => '2027-03-04', 'position' => 0]);

    $response = $this->get("/admin/receipts/{$receipt->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Receipts/Show')
            ->has('receipt', fn (Assert $props) => $props
                ->where('id', $receipt->id)
                ->where('warehouse', ['id' => $warehouse->id, 'name' => 'Annex'])
                ->where('note', 'Dock 4')
                ->where('status', 'draft')
                ->where('pallets_count', 2)
                ->where('files_count', 2)
                ->where('created_by', ['id' => $admin->id, 'name' => $admin->name])
                ->where('finalized_by', null)
                ->where('finalized_at', null)
                ->where('created_at', $receipt->created_at->toIso8601String())
                ->where('files', [
                    ['id' => $photo->id, 'original_name' => 'front.jpg', 'mime_type' => 'image/jpeg', 'size' => 2048, 'url' => route('admin.receipts.files.show', ['receipt' => $receipt->id, 'file' => $photo->id]), 'created_at' => $photo->created_at->toIso8601String()],
                    ['id' => $pdf->id, 'original_name' => 'back.pdf', 'mime_type' => 'application/pdf', 'size' => 4096, 'url' => route('admin.receipts.files.show', ['receipt' => $receipt->id, 'file' => $pdf->id]), 'created_at' => $pdf->created_at->toIso8601String()],
                ])
                ->where('pallets', [
                    ['product_id' => $widgets->id, 'product' => ['id' => $widgets->id, 'name' => 'Widgets', 'ar_name' => 'ودجات', 'image_url' => null, 'boxes_count' => 10, 'active' => true], 'boxes' => 12, 'expiration_date' => '2027-03-04'],
                    ['product_id' => $gadgets->id, 'product' => ['id' => $gadgets->id, 'name' => 'Gadgets', 'ar_name' => 'أدوات', 'image_url' => null, 'boxes_count' => 20, 'active' => true], 'boxes' => 7, 'expiration_date' => null],
                ])
            )
    );

    $json = json_encode($response->inertiaProps('receipt'));
    expect($json)->not->toContain('"path"')->not->toContain('receipt-bills')->not->toContain('pallet_id');
});

test('the download urls of the admin pages point at the session route, not the bearer one', function () {
    actingAsAdmin();
    $receipt = Receipt::factory()->create();
    $file = ReceiptFile::factory()->for($receipt)->create();

    $url = $this->get("/admin/receipts/{$receipt->id}")->inertiaProps('receipt.files.0.url');

    expect($url)->toBe(route('admin.receipts.files.show', ['receipt' => $receipt->id, 'file' => $file->id]))
        ->and($url)->toContain('/admin/receipts/')->not->toContain('/api/');
});

test('a finalized receipt shows who finalized it, the same pallets as written, and no id of the pallets it created', function () {
    actingAsAdmin();
    $product = Product::factory()->create();
    $receipt = Receipt::factory()->finalized()->create();
    ReceiptFile::factory()->for($receipt)->create();
    $pallet = Pallet::factory()->unplaced()->create(['receipt_id' => $receipt->id, 'product_id' => $product->id]);
    ReceiptPallet::factory()->for($receipt)->create(['product_id' => $product->id, 'boxes' => 9, 'position' => 0, 'pallet_id' => $pallet->id]);

    $response = $this->get("/admin/receipts/{$receipt->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('receipt.status', 'finalized')
            ->where('receipt.finalized_by', ['id' => $receipt->finalizedBy->id, 'name' => $receipt->finalizedBy->name])
            ->where('receipt.finalized_at', $receipt->finalized_at->toIso8601String())
            ->has('receipt.pallets', 1)
            ->has('receipt.pallets.0', fn (Assert $entry) => $entry
                ->where('product_id', $product->id)
                ->where('boxes', 9)
                ->where('expiration_date', ReceiptPallet::query()->first()->expiration_date?->toDateString())
                ->has('product')
            )
    );

    expect(json_encode($response->inertiaProps('receipt')))->not->toContain('pallet_id')->not->toContain('"path"');
});

test('a receipt with no files and no pallets shows both lists empty', function () {
    actingAsAdmin();
    $receipt = Receipt::factory()->create(['note' => null]);

    $this->get("/admin/receipts/{$receipt->id}")->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('receipt.note', null)
            ->where('receipt.files', [])
            ->where('receipt.pallets', [])
            ->where('receipt.files_count', 0)
            ->where('receipt.pallets_count', 0)
    );
});

test('a receipt shows only its own files and pallets', function () {
    actingAsAdmin();
    $receipt = Receipt::factory()->create();
    ReceiptFile::factory()->for($receipt)->create();
    ReceiptPallet::factory()->for($receipt)->create(['boxes' => 3]);
    $other = Receipt::factory()->create(); // noise
    ReceiptFile::factory()->for($other)->count(2)->create();
    ReceiptPallet::factory()->for($other)->count(2)->create();

    $this->get("/admin/receipts/{$receipt->id}")->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('receipt.files', 1)->has('receipt.pallets', 1)->where('receipt.pallets.0.boxes', 3)
    );
});
