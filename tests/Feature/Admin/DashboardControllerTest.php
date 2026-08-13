<?php

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Pallet;
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

test('the dashboard shows expired and expiring-soon pallet counts, excluding pallets outside the range', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsAdmin();

    Pallet::factory()->create(['expiration_date' => '2026-08-12']);
    Pallet::factory()->create(['expiration_date' => '2026-08-16']);
    Pallet::factory()->create(['expiration_date' => '2026-09-12']);

    $response = $this->get('/admin');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('stats.expiring.expired', 1)
            ->where('stats.expiring.soon', 1)
    );

    Carbon::setTestNow();
});

test('the expiring-soon count includes a pallet expiring exactly 7 days out and excludes one expiring 8 days out', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsAdmin();

    Pallet::factory()->create(['expiration_date' => '2026-08-20']);
    Pallet::factory()->create(['expiration_date' => '2026-08-21']);

    $response = $this->get('/admin');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('stats.expiring.soon', 1)
    );

    Carbon::setTestNow();
});

test('the expired count excludes a pallet expiring today, but the expiring-soon count includes it', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsAdmin();

    Pallet::factory()->create(['expiration_date' => '2026-08-13']);

    $response = $this->get('/admin');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('stats.expiring.expired', 0)
            ->where('stats.expiring.soon', 1)
    );

    Carbon::setTestNow();
});

test("the dashboard counts today's activity per action, merging transfers, and excludes older entries", function () {
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

    backdate(CellStatusLog::factory()->create(['cell_id' => $cell->id, 'action' => CellLogAction::Stored]), '2026-08-12 09:00:00');
    backdate(CellStatusLog::factory()->create(['cell_id' => $cell->id, 'action' => CellLogAction::Opened]), '2026-08-06 09:00:00');

    $response = $this->get('/admin');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('stats.activity_today.stored', 1)
            ->where('stats.activity_today.opened', 1)
            ->where('stats.activity_today.emptied', 1)
            ->where('stats.activity_today.transferred', 2)
    );

    Carbon::setTestNow();
});

test('the dashboard shows the stale pallet count, excluding fresh pallets', function () {
    actingAsAdmin();

    Pallet::factory()->stale()->create();
    Pallet::factory()->create();

    $response = $this->get('/admin');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('stats.stale', 1)
    );
});

test("the dashboard exposes today's date and the expiring-soon cutoff date", function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsAdmin();

    $response = $this->get('/admin');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('today', '2026-08-13')
            ->where('expiringSoonUntil', '2026-08-20')
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
