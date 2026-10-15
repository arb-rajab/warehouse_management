<?php

use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('the warehouse map screen can be rendered', function () {
    actingAsAdmin();

    $response = $this->get('/admin/warehouse-map');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/WarehouseMap/Index')
    );
});

test('a guest is redirected to the login page from the warehouse map screen', function () {
    $this->get('/admin/warehouse-map')->assertRedirect(route('login'));
});

test('a mobile app user cannot view the warehouse map screen', function () {
    actingAsMobilePanelUser();

    $this->get('/admin/warehouse-map')->assertForbidden();
});

test('the warehouse map rows carry every field the maps read, in the shape of the mobile rows/full endpoint', function () {
    actingAsAdmin();

    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 2, 'flats_count' => 1]);
    $product = Product::factory()->imageUrl('https://cdn.example.com/widgets.png')->boxesCount(48)->create([
        'name' => 'Widgets',
        'ar_name' => 'ودجات',
    ]);
    $occupiedCell = $row->cells()->where('cell_number', 1)->first();
    $emptyCell = $row->cells()->where('cell_number', 2)->first();
    $pallet = Pallet::factory()->create([
        'cell_id' => $occupiedCell->id,
        'product_id' => $product->id,
        'remaining_boxes' => 31,
    ]);

    $response = $this->getJson('/admin/warehouse-map/rows');

    $response->assertOk();
    $rowPayload = collect($response->json())->firstWhere('id', $row->id);

    expect($rowPayload)->toMatchArray([
        'id' => $row->id,
        'letter' => 'Z',
        'cells_count' => 2,
        'flats_count' => 1,
        'has_pallets' => true,
    ]);
    expect(collect($rowPayload['cells'])->firstWhere('id', $occupiedCell->id))->toEqual([
        'id' => $occupiedCell->id,
        'cell_number' => 1,
        'flat_number' => 1,
        'state' => 'full',
        'is_active' => true,
        'pallet' => [
            'id' => $pallet->id,
            'product_id' => $product->id,
            'product_name' => 'Widgets',
            'product_ar_name' => 'ودجات',
            'product_active' => true,
            'product_image_url' => 'https://cdn.example.com/widgets.png',
            'expiration_date' => $pallet->expiration_date->toDateString(),
            'added_at' => $pallet->created_at->toIso8601String(),
            'cell_entered_at' => null,
            'is_stale' => null,
            'remaining_boxes' => 31,
            'product_boxes_count' => 48,
        ],
    ]);
    expect(collect($rowPayload['cells'])->firstWhere('id', $emptyCell->id))->toEqual([
        'id' => $emptyCell->id,
        'cell_number' => 2,
        'flat_number' => 1,
        'state' => 'empty',
        'is_active' => true,
        'pallet' => null,
    ]);
});

test('the warehouse map rows list every row in letter order, each with only its own cells', function () {
    actingAsAdmin();

    $second = Row::factory()->create(['letter' => 'B', 'cells_count' => 3, 'flats_count' => 2]);
    $first = Row::factory()->create(['letter' => 'A', 'cells_count' => 2, 'flats_count' => 1]);

    $response = $this->getJson('/admin/warehouse-map/rows');

    $response->assertOk();
    expect(collect($response->json())->pluck('letter')->all())->toBe(['A', 'B']);
    expect(collect($response->json())->firstWhere('id', $first->id)['cells'])->toHaveCount(2);
    expect(collect($response->json())->firstWhere('id', $second->id)['cells'])->toHaveCount(6);
});

test('the warehouse map rows are not paginated', function () {
    actingAsAdmin();

    Row::factory()->count(25)->create(['cells_count' => 1, 'flats_count' => 1]);

    $response = $this->getJson('/admin/warehouse-map/rows');

    $response->assertOk();
    expect($response->json())->toHaveCount(25);
});

test('the warehouse map rows are loaded without a query per row or per pallet', function () {
    actingAsAdmin();

    Row::factory()->count(6)->create(['cells_count' => 2, 'flats_count' => 1])->each(function (Row $row) {
        $row->cells->each(fn ($cell) => Pallet::factory()->create(['cell_id' => $cell->id]));
    });

    DB::enableQueryLog();
    $response = $this->getJson('/admin/warehouse-map/rows');
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertOk();
    expect($queryCount)->toBeLessThan(15);
});

test('the warehouse map rows answer a repeat request that already holds the current data with a 304', function () {
    actingAsAdmin();
    Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);

    $first = $this->getJson('/admin/warehouse-map/rows');
    $first->assertOk();
    $etag = $first->headers->get('ETag');

    expect($etag)->not->toBeNull();

    $this->getJson('/admin/warehouse-map/rows', ['If-None-Match' => $etag])->assertNotModified();
});

test('the warehouse map rows hand out a fresh body once the warehouse has changed', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);

    $etag = $this->getJson('/admin/warehouse-map/rows')->headers->get('ETag');
    Pallet::factory()->create(['cell_id' => $row->cells()->first()->id]);

    $response = $this->getJson('/admin/warehouse-map/rows', ['If-None-Match' => $etag]);

    $response->assertOk();
    expect($response->headers->get('ETag'))->not->toBe($etag);
});

test('a guest cannot read the warehouse map rows', function () {
    $this->getJson('/admin/warehouse-map/rows')->assertUnauthorized();
});

test('a mobile app user cannot read the warehouse map rows', function () {
    actingAsMobilePanelUser();
    Row::factory()->create();

    $this->getJson('/admin/warehouse-map/rows')->assertForbidden();
});
