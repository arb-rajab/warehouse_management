<?php

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Row;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('a second identical dashboard request is served from cache instead of recomputing the stats', function () {
    actingAsAdmin();

    $this->get('/admin')->assertOk();

    DB::enableQueryLog();
    $this->get('/admin')->assertOk();
    $queries = collect(DB::getQueryLog())->pluck('query')->implode(' | ');
    DB::disableQueryLog();

    expect($queries)->not->toContain('"cells"')
        ->and($queries)->not->toContain('"pallets"')
        ->and($queries)->not->toContain('"cell_status_logs"');
});

test('the dashboard cache is invalidated the moment a cell status log is written, so a new pallet shows up immediately', function () {
    actingAsAdmin();

    $this->get('/admin')->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('stats.occupancy.full', 0)
    );

    $cell = Cell::factory()->create(['state' => CellState::Full]);
    CellStatusLog::factory()->create([
        'cell_id' => $cell->id,
        'action' => CellLogAction::Stored,
        'from_state' => CellState::Empty,
        'to_state' => CellState::Full,
    ]);

    $this->get('/admin')->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('stats.occupancy.full', 1)
    );
});

test('the dashboard cache is invalidated when a row is created, so its new empty cells are counted immediately', function () {
    actingAsAdmin();

    $this->get('/admin')->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('stats.occupancy.empty', 0)
    );

    Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);

    $this->get('/admin')->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('stats.occupancy.empty', 2)
    );
});

test('the dashboard cache is invalidated when a row is resized, so its regenerated cells are counted immediately', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);

    $this->get('/admin')->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('stats.occupancy.empty', 1)
    );

    $this->put("/admin/rows/{$row->letter}", [
        'letter' => $row->letter,
        'cells_count' => 3,
        'flats_count' => 1,
    ])->assertRedirect();

    $this->get('/admin')->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('stats.occupancy.empty', 3)
    );
});

test('the dashboard cache is invalidated when a row is deleted, so its removed cells are no longer counted', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);

    $this->get('/admin')->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('stats.occupancy.empty', 2)
    );

    $this->delete("/admin/rows/{$row->letter}")->assertRedirect();

    $this->get('/admin')->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('stats.occupancy.empty', 0)
    );
});
