<?php

use App\Enums\CellLogAction;
use App\Models\CellStatusLog;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\SeedsCellStatusLogFixtures;

uses(SeedsCellStatusLogFixtures::class);

test('the mobile dashboard shows cell occupancy counts by state', function () {
    actingAsMobileUser();
    $this->seedOccupancyFixture();

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
    $this->seedExpiringWindowsFixture();

    $response = $this->getJson('/api/v1/dashboard');

    $response->assertOk();
    expect($response->json('stats.expiring.expired'))->toBe(1);
    expect($response->json('stats.expiring.windows'))->toHaveCount(4);
    expect($response->json('stats.expiring.windows.0'))->toEqual([
        'months' => 1,
        'days' => 31,
        'until' => '2026-09-13',
        'count' => 1,
    ]);
    expect($response->json('stats.expiring.windows.2'))->toEqual([
        'months' => 4,
        'days' => 122,
        'until' => '2026-12-13',
        'count' => 2,
    ]);

    Carbon::setTestNow();
});

test('a caller-chosen expiring_days widens or narrows the mobile dashboard custom expiring-soon window', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsMobileUser();
    $this->seedCustomExpiringWindowFixture();

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
    $this->seedActivityWindowsFixture();

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

    ['matchingProduct' => $matchingProduct] = $this->seedDashboardProductFilterFixture();

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

test('a product_status of active narrows the mobile dashboard to active products, excluding inactive ones', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsMobileUser();

    $activeProduct = Product::factory()->create();
    $inactiveProduct = Product::factory()->inactive()->create();
    CellStatusLog::factory()->create(['product_id' => $activeProduct->id, 'action' => CellLogAction::Stored]);
    CellStatusLog::factory()->create(['product_id' => $inactiveProduct->id, 'action' => CellLogAction::Stored]);

    $response = $this->getJson('/api/v1/dashboard?product_status=active');

    $response->assertOk();
    expect($response->json('stats.activity_today.stored'))->toBe(1);
    expect($response->json('filters.product_id'))->toBeNull();
    expect($response->json('filters.product_status'))->toBe('active');

    Carbon::setTestNow();
});

test('a product_status of inactive narrows the mobile dashboard to inactive products, excluding active ones', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsMobileUser();

    $activeProduct = Product::factory()->create();
    $inactiveProduct = Product::factory()->inactive()->create();
    CellStatusLog::factory()->create(['product_id' => $activeProduct->id, 'action' => CellLogAction::Stored]);
    CellStatusLog::factory()->create(['product_id' => $inactiveProduct->id, 'action' => CellLogAction::Stored]);

    $response = $this->getJson('/api/v1/dashboard?product_status=inactive');

    $response->assertOk();
    expect($response->json('stats.activity_today.stored'))->toBe(1);
    expect($response->json('filters.product_id'))->toBeNull();
    expect($response->json('filters.product_status'))->toBe('inactive');

    Carbon::setTestNow();
});

test('a product_status with zero matching products narrows the mobile dashboard to nothing rather than falling back to unfiltered', function () {
    actingAsMobileUser();
    Product::factory()->create();
    CellStatusLog::factory()->create(['action' => CellLogAction::Stored]);

    $response = $this->getJson('/api/v1/dashboard?product_status=inactive');

    $response->assertOk();
    expect($response->json('stats.activity_today.stored'))->toBe(0);
});

test('sending both product_status and product_id together is rejected on the mobile dashboard', function () {
    actingAsMobileUser();
    $product = Product::factory()->create();

    $response = $this->getJson("/api/v1/dashboard?product_status=active&product_id[]={$product->id}");

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['product_status']);
});

test('an invalid product_status is rejected on the mobile dashboard', function () {
    actingAsMobileUser();

    $response = $this->getJson('/api/v1/dashboard?product_status=bogus');

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['product_status']);
});

test('product_status is silently ignored on the admin dashboard', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsAdmin();

    $activeProduct = Product::factory()->create();
    $inactiveProduct = Product::factory()->inactive()->create();
    CellStatusLog::factory()->create(['product_id' => $activeProduct->id, 'action' => CellLogAction::Stored]);
    CellStatusLog::factory()->create(['product_id' => $inactiveProduct->id, 'action' => CellLogAction::Stored]);

    $response = $this->get('/admin?product_status=active');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('stats.activity_today.stored', 2)
    );

    Carbon::setTestNow();
});

test('the mobile dashboard exposes today\'s date and the week start, and does not include filterOptions', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsMobileUser();
    Product::factory()->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);

    $response = $this->getJson('/api/v1/dashboard');

    $response->assertOk();
    expect($response->json('today'))->toBe('2026-08-13');
    expect($response->json('weekStart'))->toBe('2026-08-10');
    expect($response->json('filterOptions'))->toBeNull();

    Carbon::setTestNow();
});

test('an unauthenticated caller cannot view the mobile dashboard', function () {
    $response = $this->getJson('/api/v1/dashboard');

    $response->assertUnauthorized();
});
