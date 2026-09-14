<?php

use App\Models\CellVerificationRound;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;
use App\Models\User;
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
    $product = Product::factory()->imageUrl('https://cdn.example.com/widgets.png')->create([
        'name' => 'Widgets',
        'ar_name' => 'ودجات',
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
            'product_ar_name' => 'ودجات',
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

test('listing every row with its cells can be filtered by product_status=active, excluding cells with an inactive product and empty cells', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['cells_count' => 3, 'flats_count' => 1]);
    $activeProduct = Product::factory()->create();
    $inactiveProduct = Product::factory()->inactive()->create();
    $activeCell = $row->cells()->where('cell_number', 1)->first();
    $inactiveCell = $row->cells()->where('cell_number', 2)->first();
    // cell_number 3 stays empty
    Pallet::factory()->create(['cell_id' => $activeCell->id, 'product_id' => $activeProduct->id]);
    Pallet::factory()->create(['cell_id' => $inactiveCell->id, 'product_id' => $inactiveProduct->id]);

    $response = $this->getJson('/api/v1/rows/full?product_status=active');

    $response->assertOk();
    $rowPayload = collect($response->json())->firstWhere('id', $row->id);
    expect(collect($rowPayload['cells'])->pluck('id')->all())->toBe([$activeCell->id]);
});

test('listing every row with its cells can be filtered by product_status=inactive, excluding cells with an active product and empty cells', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['cells_count' => 3, 'flats_count' => 1]);
    $activeProduct = Product::factory()->create();
    $inactiveProduct = Product::factory()->inactive()->create();
    $activeCell = $row->cells()->where('cell_number', 1)->first();
    $inactiveCell = $row->cells()->where('cell_number', 2)->first();
    // cell_number 3 stays empty
    Pallet::factory()->create(['cell_id' => $activeCell->id, 'product_id' => $activeProduct->id]);
    Pallet::factory()->create(['cell_id' => $inactiveCell->id, 'product_id' => $inactiveProduct->id]);

    $response = $this->getJson('/api/v1/rows/full?product_status=inactive');

    $response->assertOk();
    $rowPayload = collect($response->json())->firstWhere('id', $row->id);
    expect(collect($rowPayload['cells'])->pluck('id')->all())->toBe([$inactiveCell->id]);
});

test('an invalid product_status is rejected on the full row listing', function () {
    actingAsMobileUser();

    $response = $this->getJson('/api/v1/rows/full?product_status=bogus');

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['product_status']);
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

test('an authenticated worker can list rows frozen by any unfinished round, not just their own', function () {
    actingAsMobileUser();

    $frozenByOther = Row::factory()->create(['letter' => 'B']);
    CellVerificationRound::factory()->for(User::factory())->covering($frozenByOther)->create();

    $completedRound = Row::factory()->create(['letter' => 'C']);
    CellVerificationRound::factory()->covering($completedRound)->completed()->create();

    $unfrozen = Row::factory()->create(['letter' => 'A']);

    $response = $this->getJson('/api/v1/rows/frozen');

    $response->assertOk();
    expect(collect($response->json())->pluck('id'))
        ->toContain($frozenByOther->id)
        ->not->toContain($completedRound->id)
        ->not->toContain($unfrozen->id);
});

test('listing frozen rows returns letters in order rather than creation order', function () {
    actingAsMobileUser();

    $rowB = Row::factory()->create(['letter' => 'B']);
    $rowA = Row::factory()->create(['letter' => 'A']);
    CellVerificationRound::factory()->covering($rowB, $rowA)->create();

    $response = $this->getJson('/api/v1/rows/frozen');

    $response->assertOk();
    expect(collect($response->json())->pluck('letter')->all())->toBe(['A', 'B']);
});

test('an unauthenticated caller cannot list frozen rows', function () {
    $response = $this->getJson('/api/v1/rows/frozen');

    $response->assertUnauthorized();
});
