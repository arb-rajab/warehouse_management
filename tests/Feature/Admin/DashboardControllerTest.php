<?php

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('the dashboard shows cell occupancy counts by state', function () {
    actingAsAdmin();
    Cell::factory()->count(2)->create(['state' => CellState::Empty]);
    Cell::factory()->create(['state' => CellState::Opened]);
    Pallet::factory()->create();

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

    Pallet::factory()->create(['expiration_date' => '2026-08-12']);
    Pallet::factory()->create(['expiration_date' => '2026-08-16']);
    Pallet::factory()->create(['expiration_date' => '2026-09-12']);

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

test('a caller-chosen expiring_days widens or narrows the custom expiring-soon window, independent of the fixed windows', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsAdmin();

    Pallet::factory()->create(['expiration_date' => '2026-08-25']);

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

    $matchingProduct = Product::factory()->create();
    $otherProduct = Product::factory()->create();

    $matchingPallet = Pallet::factory()->create(['product_id' => $matchingProduct->id, 'expiration_date' => '2026-08-14']);
    Pallet::factory()->opened()->create(['product_id' => $matchingProduct->id]);
    Pallet::factory()->create(['product_id' => $otherProduct->id, 'expiration_date' => '2026-08-14']);
    Cell::factory()->create(['state' => CellState::Empty]);

    CellStatusLog::factory()->create(['product_id' => $matchingProduct->id, 'action' => CellLogAction::Stored]);
    CellStatusLog::factory()->create(['product_id' => $otherProduct->id, 'action' => CellLogAction::Stored]);

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

test('the dashboard exposes the product list for the product filter', function () {
    actingAsAdmin();
    $product = Product::factory()->create(['name' => 'Widgets']);

    $response = $this->get('/admin');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('filterOptions.products', 1)
            ->where('filterOptions.products.0.id', $product->id)
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
    $mobileUser = User::factory()->mobileUser()->create();

    $response = $this->actingAs($mobileUser)->get('/admin');

    $response->assertForbidden();
});

test('an unauthenticated caller is redirected to login when visiting the dashboard', function () {
    $response = $this->get('/admin');

    $response->assertRedirect(route('login'));
});
