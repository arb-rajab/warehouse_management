<?php

use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;
use Illuminate\Support\Facades\DB;

test('an authenticated worker can list rows with every property the app reads', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 3, 'flats_count' => 2]);
    $otherRow = Row::factory()->create(['letter' => 'Y', 'cells_count' => 1, 'flats_count' => 1]);

    $response = $this->getJson('/api/v1/rows');

    $response->assertOk();
    expect(collect($response->json('data'))->firstWhere('id', $row->id))->toEqual([
        'id' => $row->id,
        'letter' => 'Z',
        'cells_count' => 3,
        'flats_count' => 2,
        'has_pallets' => false,
    ]);
    expect(collect($response->json('data'))->pluck('letter'))->toContain('Y');
    expect($otherRow->id)->not->toBeNull();
});

test('the row listing computes has_pallets without an exists query per row', function () {
    actingAsMobileUser();

    Row::factory()->count(5)->create(['cells_count' => 1, 'flats_count' => 1])->each(function (Row $row) {
        Pallet::factory()->create(['cell_id' => $row->cells()->first()->id]);
    });

    DB::enableQueryLog();
    $response = $this->getJson('/api/v1/rows');
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('has_pallets')->unique()->all())->toBe([true]);
    expect($queryCount)->toBeLessThan(5);
});

test('the row listing paginates instead of returning everything at once', function () {
    actingAsMobileUser();

    Row::factory()->count(25)->create();

    $response = $this->getJson('/api/v1/rows');

    assertJsonListingPaginates($response, total: 25);
});

test('an unauthenticated caller cannot list rows', function () {
    $response = $this->getJson('/api/v1/rows');

    $response->assertUnauthorized();
});

test('an authenticated worker can list every row with its cells and their pallets', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 2, 'flats_count' => 1]);
    $product = Product::factory()->create([
        'name' => 'Widgets',
        'image_url' => 'https://cdn.example.com/widgets.png',
    ]);
    $occupiedCell = $row->cells()->where('cell_number', 1)->first();
    $emptyCell = $row->cells()->where('cell_number', 2)->first();
    $pallet = Pallet::factory()->create(['cell_id' => $occupiedCell->id, 'product_id' => $product->id]);

    $response = $this->getJson('/api/v1/rows/full');

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
            'product_image_url' => 'https://cdn.example.com/widgets.png',
            'expiration_date' => $pallet->expiration_date->toDateString(),
            'added_at' => $pallet->created_at->toIso8601String(),
            'is_stale' => null,
            'remaining_boxes' => $pallet->remaining_boxes,
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

test('listing every row with its cells computes has_pallets without an exists query per row', function () {
    actingAsMobileUser();

    Row::factory()->count(10)->create(['cells_count' => 1, 'flats_count' => 1])->each(function (Row $row) {
        Pallet::factory()->create(['cell_id' => $row->cells()->first()->id]);
    });
    Row::factory()->count(2)->create(['cells_count' => 1, 'flats_count' => 1]);

    DB::enableQueryLog();
    $response = $this->getJson('/api/v1/rows/full');
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertOk();
    $payload = collect($response->json());
    expect($payload->where('has_pallets', true))->toHaveCount(10);
    expect($payload->where('has_pallets', false))->toHaveCount(2);

    // A per-row exists query would put this at 12+ on top of the eager loads;
    // the subquery keeps it flat regardless of how many rows come back.
    expect($queryCount)->toBeLessThan(12);
});

test('listing every row with its cells computes is_stale from a caller-supplied stale_after_days instead of a fixed threshold', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->where('cell_number', 1)->first();
    Pallet::factory()->stale()->create(['cell_id' => $cell->id]);

    $response = $this->getJson('/api/v1/rows/full?stale_after_days=5');

    $response->assertOk();
    $rowPayload = collect($response->json())->firstWhere('id', $row->id);
    expect(collect($rowPayload['cells'])->firstWhere('id', $cell->id)['pallet']['is_stale'])->toBeTrue();
});

test('listing every row with its cells excludes cells belonging to a different row', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);
    Row::factory()->create(['cells_count' => 3, 'flats_count' => 1]);

    $response = $this->getJson('/api/v1/rows/full');

    $response->assertOk();
    $rowPayload = collect($response->json())->firstWhere('id', $row->id);
    expect($rowPayload['cells'])->toHaveCount(2);
});

test('listing every row with its cells returns everything without pagination', function () {
    actingAsMobileUser();

    Row::factory()->count(25)->create(['cells_count' => 1, 'flats_count' => 1]);

    $response = $this->getJson('/api/v1/rows/full');

    $response->assertOk();
    expect($response->json())->toHaveCount(25);
});

test('an unauthenticated caller cannot list every row with its cells', function () {
    $response = $this->getJson('/api/v1/rows/full');

    $response->assertUnauthorized();
});
