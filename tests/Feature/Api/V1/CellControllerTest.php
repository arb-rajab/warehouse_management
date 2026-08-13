<?php

use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;

test('an authenticated worker can list a row cells with every property the app reads', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 2, 'flats_count' => 1]);
    $product = Product::factory()->create([
        'name' => 'Widgets',
        'image_url' => 'https://cdn.example.com/widgets.png',
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
        'pallet' => [
            'id' => $pallet->id,
            'product_name' => 'Widgets',
            'product_image_url' => 'https://cdn.example.com/widgets.png',
            'expiration_date' => $pallet->expiration_date->toDateString(),
            'added_at' => $pallet->created_at->toIso8601String(),
            'is_stale' => false,
        ],
    ]);
    expect(collect($response->json('data'))->firstWhere('id', $emptyCell->id))->toEqual([
        'id' => $emptyCell->id,
        'row_letter' => 'Z',
        'cell_number' => 2,
        'flat_number' => 1,
        'state' => 'empty',
        'pallet' => null,
    ]);
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

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(20);
    expect($response->json('meta.total'))->toBe(25);
});

test('an unauthenticated caller cannot list a row cells', function () {
    $row = Row::factory()->create();

    $response = $this->getJson("/api/v1/rows/{$row->letter}/cells");

    $response->assertUnauthorized();
});

test('an authenticated worker can look up a cell by its coordinates with every property the app reads', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 3, 'flats_count' => 2]);
    $product = Product::factory()->create([
        'name' => 'Widgets',
        'image_url' => 'https://cdn.example.com/widgets.png',
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
        'pallet' => [
            'id' => $pallet->id,
            'product_name' => 'Widgets',
            'product_image_url' => 'https://cdn.example.com/widgets.png',
            'expiration_date' => $pallet->expiration_date->toDateString(),
            'added_at' => $pallet->created_at->toIso8601String(),
            'is_stale' => false,
        ],
    ]);
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
