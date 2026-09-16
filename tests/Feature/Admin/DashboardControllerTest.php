<?php

use App\Models\Pallet;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\SeedsCellStatusLogFixtures;

uses(SeedsCellStatusLogFixtures::class);

test('the dashboard shows cell occupancy counts by state', function () {
    actingAsAdmin();
    $this->seedOccupancyFixture();

    $response = $this->get('/admin');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Dashboard/Index')
            ->where('stats.occupancy.empty', 2)
            ->where('stats.occupancy.full', 1)
            ->where('stats.occupancy.opened', 1)
    );
});

test('the dashboard shows expired and per-window expiring-soon pallet counts, excluding pallets outside each window', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsAdmin();
    $this->seedExpiringWindowsFixture();

    $response = $this->get('/admin');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('stats.expiring.expired', 1)
            ->has('stats.expiring.windows', 4)
            ->where('stats.expiring.windows.0.days', 7)
            ->where('stats.expiring.windows.0.until', '2026-08-20')
            ->where('stats.expiring.windows.0.count', 1)
            ->where('stats.expiring.windows.2.days', 30)
            ->where('stats.expiring.windows.2.until', '2026-09-12')
            ->where('stats.expiring.windows.2.count', 2)
    );

    Carbon::setTestNow();
});

test('the 7-day window includes a pallet expiring exactly 7 days out and excludes one expiring 8 days out', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsAdmin();

    Pallet::factory()->create(['expiration_date' => '2026-08-20']);
    Pallet::factory()->create(['expiration_date' => '2026-08-21']);

    $response = $this->get('/admin');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('stats.expiring.windows.0.count', 1)
    );

    Carbon::setTestNow();
});

test('the expired count excludes a pallet expiring today, but the 7-day window includes it', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsAdmin();

    Pallet::factory()->create(['expiration_date' => '2026-08-13']);

    $response = $this->get('/admin');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('stats.expiring.expired', 0)
            ->where('stats.expiring.windows.0.count', 1)
    );

    Carbon::setTestNow();
});

test('a pallet with no expiration date is excluded from the expired and expiring-soon counts, without throwing', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsAdmin();

    Pallet::factory()->create(['expiration_date' => null]);

    $response = $this->get('/admin');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('stats.expiring.expired', 0)
            ->where('stats.expiring.windows.0.count', 0)
            ->where('stats.expiring.custom.count', 0)
    );

    Carbon::setTestNow();
});

test('a caller-chosen expiring_days widens or narrows the custom expiring-soon window, independent of the fixed windows', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsAdmin();
    $this->seedCustomExpiringWindowFixture();

    $narrow = $this->get('/admin?expiring_days=7');
    $narrow->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('stats.expiring.custom.days', 7)
            ->where('stats.expiring.custom.until', '2026-08-20')
            ->where('stats.expiring.custom.count', 0)
            ->where('stats.expiring.windows.0.days', 7)
    );

    $wide = $this->get('/admin?expiring_days=30');
    $wide->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('stats.expiring.custom.days', 30)
            ->where('stats.expiring.custom.until', '2026-09-12')
            ->where('stats.expiring.custom.count', 1)
    );

    Carbon::setTestNow();
});

test('the custom expiring-soon window defaults to 45 days, distinct from the 7-day fixed window', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsAdmin();

    $response = $this->get('/admin');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('stats.expiring.custom.days', 45)
            ->where('stats.expiring.custom.until', '2026-09-27')
    );

    Carbon::setTestNow();
});

test('an invalid expiring_days is rejected', function () {
    actingAsAdmin();

    $response = $this->get('/admin?expiring_days=0');

    $response->assertInvalid(['expiring_days']);
});

test("the dashboard counts today's and this week's activity per action, merging transfers, and excludes entries outside each window", function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsAdmin();
    $this->seedActivityWindowsFixture();

    $response = $this->get('/admin');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('stats.activity_today.stored', 1)
            ->where('stats.activity_today.opened', 1)
            ->where('stats.activity_today.emptied', 1)
            ->where('stats.activity_today.transferred', 2)
            ->where('stats.activity_week.stored', 2)
            ->where('stats.activity_week.opened', 1)
            ->where('stats.activity_week.emptied', 1)
            ->where('stats.activity_week.transferred', 2)
            ->where('weekStart', '2026-08-10')
    );

    Carbon::setTestNow();
});

test('a product filter narrows occupancy (empty forced to zero), expiring, and activity counts to that product, excluding other products', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsAdmin();

    ['matchingProduct' => $matchingProduct, 'matchingPallet' => $matchingPallet] = $this->seedDashboardProductFilterFixture();

    $response = $this->get("/admin?product_id[]={$matchingProduct->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('stats.occupancy.empty', 0)
            ->where('stats.occupancy.full', 1)
            ->where('stats.occupancy.opened', 1)
            ->where('stats.expiring.windows.0.count', 1)
            ->where('stats.activity_today.stored', 1)
            ->where('stats.activity_week.stored', 1)
            ->where('filters.product_id', [(int) $matchingProduct->id])
    );

    expect($matchingPallet->product_id)->toBe($matchingProduct->id);

    Carbon::setTestNow();
});

test('the dashboard has no pre-selected products for the product filter when nothing is selected', function () {
    actingAsAdmin();
    Product::factory()->create(['name' => 'Widgets']);

    $response = $this->get('/admin');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('filterOptions.products', 0)
    );
});

test('the dashboard hydrates only the selected product ids for the product filter, not every product', function () {
    actingAsAdmin();
    $selected = Product::factory()->create(['name' => 'Widgets']);
    Product::factory()->create(['name' => 'Unselected Gadgets']);

    $response = $this->get("/admin?product_id[]={$selected->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('filterOptions.products', 1)
            ->where('filterOptions.products.0.id', $selected->id)
            ->where('filterOptions.products.0.name', 'Widgets')
    );
});

test("the dashboard exposes today's date and the week start", function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsAdmin();

    $response = $this->get('/admin');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('today', '2026-08-13')
            ->where('weekStart', '2026-08-10')
    );

    Carbon::setTestNow();
});

test('a mobile app user cannot view the dashboard', function () {
    actingAsMobilePanelUser();

    $response = $this->get('/admin');

    $response->assertForbidden();
});

test('an unauthenticated caller is redirected to login when visiting the dashboard', function () {
    $response = $this->get('/admin');

    $response->assertRedirect(route('login'));
});
