<?php

use App\Enums\CellState;
use App\Models\Cell;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('an authenticated admin can view the cell list with every property the table renders', function () {
    Carbon::setTestNow('2026-08-01 10:00:00');
    actingAsAdmin();

    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    $product = Product::factory()->create([
        'name' => 'Widgets',
        'image_url' => 'https://cdn.example.com/widgets.png',
    ]);
    $pallet = Pallet::factory()->create([
        'product_id' => $product->id,
        'cell_id' => $cell->id,
        'expiration_date' => '2026-09-01',
    ]);

    $response = $this->get('/admin/cells');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Cells/Index')
            ->has('cells.data', 1)
            ->has('cells.data.0', fn (Assert $cellProp) => $cellProp
                ->where('id', $cell->id)
                ->where('row_letter', 'A')
                ->where('cell_number', $cell->cell_number)
                ->where('flat_number', $cell->flat_number)
                ->where('state', 'full')
                ->has('pallet', fn (Assert $palletProp) => $palletProp
                    ->where('id', $pallet->id)
                    ->where('product_name', 'Widgets')
                    ->where('product_image_url', 'https://cdn.example.com/widgets.png')
                    ->where('expiration_date', '2026-09-01')
                    ->where('added_at', $pallet->created_at->toIso8601String())
                    ->where('is_stale', false)
                )
            )
            ->has('filterOptions.rows', 1)
            ->where('filterOptions.maxColumnNumber', 1)
            ->has('filterOptions.states', 3)
    );

    Carbon::setTestNow();
});

test('the cell list shows a null pallet for an empty cell', function () {
    actingAsAdmin();
    Cell::factory()->create(['state' => CellState::Empty]);

    $response = $this->get('/admin/cells');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('cells.data.0', fn (Assert $cellProp) => $cellProp
            ->where('pallet', null)
            ->etc()
        )
    );
});

test('the cell list paginates instead of returning everything at once', function () {
    actingAsAdmin();

    Cell::factory()->count(30)->create();

    $response = $this->get('/admin/cells');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Cells/Index')
            ->has('cells.data', 25)
            ->where('cells.meta.total', 30)
    );
});

test('a mobile app user cannot view the cell list', function () {
    $mobileUser = User::factory()->mobileUser()->create();

    $response = $this->actingAs($mobileUser)->get('/admin/cells');

    $response->assertForbidden();
});

test('an unauthenticated caller is redirected to login', function () {
    $response = $this->get('/admin/cells');

    $response->assertRedirect(route('login'));
});

test('the cell list can be filtered by state, excluding cells in other states', function () {
    actingAsAdmin();

    $matching = Cell::factory()->create(['state' => CellState::Opened]);
    Cell::factory()->create(['state' => CellState::Empty]);

    $response = $this->get('/admin/cells?state=opened');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('cells.data', 1)
            ->where('cells.data.0.id', $matching->id)
    );
});

test('the cell list can be filtered to stale cells, excluding fresh pallets and empty cells', function () {
    actingAsAdmin();

    $stalePallet = Pallet::factory()->stale()->create();
    Pallet::factory()->create();
    Cell::factory()->create(['state' => CellState::Empty]);

    $response = $this->get('/admin/cells?stale=1');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('cells.data', 1)
            ->where('cells.data.0.id', $stalePallet->cell_id)
    );
});

test('the cell list can be filtered by row, excluding cells in other rows', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $otherRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);

    $matching = $row->cells()->first();
    $otherRow->cells()->first();

    $response = $this->get("/admin/cells?row_id={$row->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('cells.data', 1)
            ->where('cells.data.0.id', $matching->id)
    );
});

test('the cell list can be filtered by column number, excluding cells in other columns', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);
    $matching = $row->cells()->where('cell_number', 1)->first();
    $row->cells()->where('cell_number', 2)->first();

    $response = $this->get('/admin/cells?column_number=1');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('cells.data', 1)
            ->where('cells.data.0.id', $matching->id)
    );
});

test('the cell list can be filtered by pallet expiration date range, excluding cells outside it and cells with no pallet', function () {
    actingAsAdmin();

    $matchingPallet = Pallet::factory()->create(['expiration_date' => '2026-06-15']);
    $outOfRangePallet = Pallet::factory()->create(['expiration_date' => '2026-01-01']);
    Cell::factory()->create();

    $response = $this->get('/admin/cells?expiration_date_from=2026-06-01&expiration_date_to=2026-06-30');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('cells.data', 1)
            ->where('cells.data.0.id', $matchingPallet->cell_id)
    );

    expect($outOfRangePallet->cell_id)->not->toBe($matchingPallet->cell_id);
});

test('the cell list defaults to ordering by coordinates when no sort is requested', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $row->cells()->delete();

    $now = now();
    Cell::insert([
        ['row_id' => $row->id, 'cell_number' => 2, 'flat_number' => 1, 'state' => CellState::Empty->value, 'created_at' => $now, 'updated_at' => $now],
        ['row_id' => $row->id, 'cell_number' => 1, 'flat_number' => 1, 'state' => CellState::Empty->value, 'created_at' => $now, 'updated_at' => $now],
    ]);

    $response = $this->get('/admin/cells');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('cells.data', 2)
            ->where('cells.data.0.cell_number', 1)
            ->where('cells.data.1.cell_number', 2)
    );
});

test('the cell list can be sorted by pallet expiration date, with a direction', function () {
    actingAsAdmin();

    $soonPallet = Pallet::factory()->create(['expiration_date' => '2026-06-01']);
    $latePallet = Pallet::factory()->create(['expiration_date' => '2026-12-01']);

    $response = $this->get('/admin/cells?sort_by=expiration_date&sort_direction=asc');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('cells.data', 2)
            ->where('cells.data.0.id', $soonPallet->cell_id)
            ->where('cells.data.1.id', $latePallet->cell_id)
    );

    $descResponse = $this->get('/admin/cells?sort_by=expiration_date&sort_direction=desc');

    $descResponse->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('cells.data', 2)
            ->where('cells.data.0.id', $latePallet->cell_id)
            ->where('cells.data.1.id', $soonPallet->cell_id)
    );
});
