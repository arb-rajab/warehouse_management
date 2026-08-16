<?php

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Models\Product;
use Illuminate\Support\Carbon;

test('the mobile dashboard shows cell occupancy counts by state', function () {
    actingAsMobileUser();
    Cell::factory()->count(2)->create(['state' => CellState::Empty]);
    Cell::factory()->create(['state' => CellState::Opened]);
    Pallet::factory()->create();

    $response = $this->getJson('/api/v1/dashboard');

    $response->assertOk();
    expect($response->json('stats.occupancy'))->toEqual([
        'empty' => 2,
        'full' => 1,
        'opened' => 1,
    ]);
});

test('the mobile dashboard shows expired and per-window expiring-soon pallet counts, excluding pallets outside each window', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsMobileUser();

    Pallet::factory()->create(['expiration_date' => '2026-08-12']);
    Pallet::factory()->create(['expiration_date' => '2026-08-16']);
    Pallet::factory()->create(['expiration_date' => '2026-09-12']);

    $response = $this->getJson('/api/v1/dashboard');

    $response->assertOk();
    expect($response->json('stats.expiring.expired'))->toBe(1);
    expect($response->json('stats.expiring.windows'))->toHaveCount(4);
    expect($response->json('stats.expiring.windows.0'))->toEqual([
        'days' => 7,
        'until' => '2026-08-20',
        'count' => 1,
    ]);
    expect($response->json('stats.expiring.windows.2'))->toEqual([
        'days' => 30,
        'until' => '2026-09-12',
        'count' => 2,
    ]);

    Carbon::setTestNow();
});

test('a caller-chosen expiring_days widens or narrows the mobile dashboard custom expiring-soon window', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsMobileUser();

    Pallet::factory()->create(['expiration_date' => '2026-08-25']);

    $narrow = $this->getJson('/api/v1/dashboard?expiring_days=7');
    $narrow->assertOk();
    expect($narrow->json('stats.expiring.custom'))->toEqual([
        'days' => 7,
        'until' => '2026-08-20',
        'count' => 0,
    ]);

    $wide = $this->getJson('/api/v1/dashboard?expiring_days=30');
    $wide->assertOk();
    expect($wide->json('stats.expiring.custom'))->toEqual([
        'days' => 30,
        'until' => '2026-09-12',
        'count' => 1,
    ]);

    Carbon::setTestNow();
});

test('the mobile dashboard custom expiring-soon window defaults to 45 days', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsMobileUser();

    $response = $this->getJson('/api/v1/dashboard');

    $response->assertOk();
    expect($response->json('stats.expiring.custom.days'))->toBe(45);
    expect($response->json('stats.expiring.custom.until'))->toBe('2026-09-27');

    Carbon::setTestNow();
});

test('an invalid expiring_days is rejected on the mobile dashboard', function () {
    actingAsMobileUser();

    $response = $this->getJson('/api/v1/dashboard?expiring_days=0');

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['expiring_days']);
});

test("the mobile dashboard counts today's and this week's activity per action, merging transfers, and excludes entries outside each window", function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsMobileUser();

    $cell = Cell::factory()->create();
    $pallet = Pallet::factory()->create();
    $sourceCell = Cell::factory()->create();
    $destinationCell = Cell::factory()->create();

    CellStatusLog::factory()->create(['cell_id' => $cell->id, 'action' => CellLogAction::Stored]);
    CellStatusLog::factory()->create(['cell_id' => $cell->id, 'action' => CellLogAction::Opened]);
    CellStatusLog::factory()->create(['cell_id' => $cell->id, 'action' => CellLogAction::Emptied]);
    createTransferPair($pallet, $sourceCell, $destinationCell, '2026-08-13 11:00:00', '2026-08-13 11:00:01');

    // Earlier this week (2026-08-13 is a Thursday; week start is Monday 2026-08-10).
    backdate(CellStatusLog::factory()->create(['cell_id' => $cell->id, 'action' => CellLogAction::Stored]), '2026-08-11 09:00:00');
    // Last week — must be excluded from both today's and this week's counts.
    backdate(CellStatusLog::factory()->create(['cell_id' => $cell->id, 'action' => CellLogAction::Opened]), '2026-08-06 09:00:00');

    $response = $this->getJson('/api/v1/dashboard');

    $response->assertOk();
    expect($response->json('stats.activity_today'))->toEqual([
        'stored' => 1,
        'opened' => 1,
        'emptied' => 1,
        'transferred' => 2,
    ]);
    expect($response->json('stats.activity_week'))->toEqual([
        'stored' => 2,
        'opened' => 1,
        'emptied' => 1,
        'transferred' => 2,
    ]);
    expect($response->json('weekStart'))->toBe('2026-08-10');

    Carbon::setTestNow();
});

test('a product filter narrows the mobile dashboard occupancy (empty forced to zero), expiring, and activity counts to that product, excluding other products', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsMobileUser();

    $matchingProduct = Product::factory()->create();
    $otherProduct = Product::factory()->create();

    Pallet::factory()->create(['product_id' => $matchingProduct->id, 'expiration_date' => '2026-08-14']);
    Pallet::factory()->opened()->create(['product_id' => $matchingProduct->id]);
    Pallet::factory()->create(['product_id' => $otherProduct->id, 'expiration_date' => '2026-08-14']);
    Cell::factory()->create(['state' => CellState::Empty]);

    CellStatusLog::factory()->create(['product_id' => $matchingProduct->id, 'action' => CellLogAction::Stored]);
    CellStatusLog::factory()->create(['product_id' => $otherProduct->id, 'action' => CellLogAction::Stored]);

    $response = $this->getJson("/api/v1/dashboard?product_id[]={$matchingProduct->id}");

    $response->assertOk();
    expect($response->json('stats.occupancy'))->toEqual([
        'empty' => 0,
        'full' => 1,
        'opened' => 1,
    ]);
    expect($response->json('stats.expiring.windows.0.count'))->toBe(1);
    expect($response->json('stats.activity_today.stored'))->toBe(1);
    expect($response->json('stats.activity_week.stored'))->toBe(1);
    expect($response->json('filters.product_id'))->toEqual([(int) $matchingProduct->id]);

    Carbon::setTestNow();
});

test('the mobile dashboard exposes the product list for the product filter, today\'s date, and the week start', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsMobileUser();
    $product = Product::factory()->create(['name' => 'Widgets']);

    $response = $this->getJson('/api/v1/dashboard');

    $response->assertOk();
    expect($response->json('filterOptions.products'))->toEqual([
        ['id' => $product->id, 'name' => 'Widgets'],
    ]);
    expect($response->json('today'))->toBe('2026-08-13');
    expect($response->json('weekStart'))->toBe('2026-08-10');

    Carbon::setTestNow();
});

test('an unauthenticated caller cannot view the mobile dashboard', function () {
    $response = $this->getJson('/api/v1/dashboard');

    $response->assertUnauthorized();
});
