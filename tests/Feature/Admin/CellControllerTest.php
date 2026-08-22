<?php

use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('an authenticated admin can view the warehouse map for the default flat, with every property the map renders', function () {
    Carbon::setTestNow('2026-08-01 10:00:00');
    actingAsAdmin();

    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 2, 'flats_count' => 2]);
    $cell = $row->cells()->where('cell_number', 1)->where('flat_number', 1)->first();
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
            ->has('rows', 1)
            ->where('rows.0.id', $row->id)
            ->where('rows.0.letter', 'A')
            ->where('rows.0.cells_count', 2)
            ->where('rows.0.flats_count', 2)
            ->where('flatNumber', 1)
            ->where('maxFlatNumber', 2)
            ->has('cells', 2)
            ->has('cells.0', fn (Assert $cellProp) => $cellProp
                ->where('id', $cell->id)
                ->where('row_letter', 'A')
                ->where('cell_number', 1)
                ->where('flat_number', 1)
                ->where('state', 'full')
                ->has('pallet', fn (Assert $palletProp) => $palletProp
                    ->where('id', $pallet->id)
                    ->where('product_id', $product->id)
                    ->where('product_name', 'Widgets')
                    ->where('product_image_url', 'https://cdn.example.com/widgets.png')
                    ->where('expiration_date', '2026-09-01')
                    ->where('added_at', $pallet->created_at->toIso8601String())
                    ->where('is_stale', null)
                )
            )
            ->where('today', '2026-08-01')
            ->where('initialHighlight.state', null)
            ->where('initialHighlight.productIds', [])
            ->where('initialHighlight.expiresWithinDays', null)
            ->where('initialHighlight.expired', false)
            ->where('jumpToCell', null)
            ->where('searchError', false)
            ->has('filterOptions.products', 0)
            ->has('cellHighlightSamples', 4)
            ->has('cellHighlightSamples.0', fn (Assert $sampleProp) => $sampleProp
                ->where('row_letter', 'A')
                ->where('cell_number', 1)
                ->where('flat_number', 1)
                ->where('state', 'full')
                ->has('pallet', fn (Assert $palletProp) => $palletProp
                    ->where('product_id', $product->id)
                    ->where('product_name', 'Widgets')
                    ->where('product_image_url', 'https://cdn.example.com/widgets.png')
                    ->where('expiration_date', '2026-09-01')
                    ->where('added_at', $pallet->created_at->toIso8601String())
                )
            )
            ->has('cellHighlightSamples.1', fn (Assert $sampleProp) => $sampleProp
                ->where('row_letter', 'A')
                ->where('cell_number', 1)
                ->where('flat_number', 2)
                ->where('state', 'empty')
                ->where('pallet', null)
            )
            ->has('cellHighlightSamples.2', fn (Assert $sampleProp) => $sampleProp
                ->where('row_letter', 'A')
                ->where('cell_number', 2)
                ->where('flat_number', 1)
                ->where('state', 'empty')
                ->where('pallet', null)
            )
            ->has('cellHighlightSamples.3', fn (Assert $sampleProp) => $sampleProp
                ->where('row_letter', 'A')
                ->where('cell_number', 2)
                ->where('flat_number', 2)
                ->where('state', 'empty')
                ->where('pallet', null)
            )
    );

    Carbon::setTestNow();
});

test('the warehouse map hydrates only the selected product ids for the highlight filter, not every product', function () {
    actingAsAdmin();
    Row::factory()->create();
    $selected = Product::factory()->create(['name' => 'Widgets']);
    Product::factory()->create(['name' => 'Unselected Gadgets']);

    $response = $this->get("/admin/cells?product_id[]={$selected->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('filterOptions.products', 1)
            ->where('filterOptions.products.0.id', $selected->id)
            ->where('filterOptions.products.0.name', 'Widgets')
    );
});

test('the per-flat highlight-match samples cover every flat, unlike the flat-scoped cells prop', function () {
    actingAsAdmin();
    Carbon::setTestNow('2026-08-01 10:00:00');

    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 2]);
    $product = Product::factory()->create();
    $otherFlatCell = $row->cells()->where('flat_number', 2)->first();
    Pallet::factory()->create([
        'product_id' => $product->id,
        'cell_id' => $otherFlatCell->id,
        'expiration_date' => '2026-08-01',
    ]);

    $response = $this->get('/admin/cells');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('cells', 1)
            ->where('cells.0.flat_number', 1)
            ->has('cellHighlightSamples', 2)
            ->where('cellHighlightSamples.1.flat_number', 2)
            ->where('cellHighlightSamples.1.state', 'full')
            ->where('cellHighlightSamples.1.pallet.product_id', $product->id)
    );

    Carbon::setTestNow();
});

test('a state and product_id passed from the dashboard seed the initial highlight filter', function () {
    actingAsAdmin();
    Row::factory()->create();
    $product = Product::factory()->create();

    $response = $this->get("/admin/cells?state=full&product_id[]={$product->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('initialHighlight.state', 'full')
            ->where('initialHighlight.productIds', [(int) $product->id])
    );
});

test('an invalid state passed from the dashboard is rejected', function () {
    actingAsAdmin();

    $response = $this->get('/admin/cells?state=bogus');

    $response->assertInvalid(['state']);
});

test('an expires_within_days passed from the dashboard seeds the initial highlight filter', function () {
    actingAsAdmin();
    Row::factory()->create();

    $response = $this->get('/admin/cells?expires_within_days=7');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('initialHighlight.expiresWithinDays', 7)
    );
});

test('an invalid expires_within_days passed from the dashboard is rejected', function () {
    actingAsAdmin();

    $response = $this->get('/admin/cells?expires_within_days=-1');

    $response->assertInvalid(['expires_within_days']);
});

test('expired passed from the dashboard seeds the initial highlight filter', function () {
    actingAsAdmin();
    Row::factory()->create();

    $response = $this->get('/admin/cells?expired=1');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('initialHighlight.expired', true)
    );
});

test('the literal "true" the dashboard link actually sends seeds the initial highlight filter', function () {
    actingAsAdmin();
    Row::factory()->create();

    $response = $this->get('/admin/cells?expired=true');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('initialHighlight.expired', true)
    );
});

test('an invalid expired value passed from the dashboard is rejected', function () {
    actingAsAdmin();

    $response = $this->get('/admin/cells?expired=bogus');

    $response->assertInvalid(['expired']);
});

test('a location search with a flat number jumps to that exact cell', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 3, 'flats_count' => 3]);
    $targetCell = $row->cells()->where('cell_number', 2)->where('flat_number', 3)->first();

    $response = $this->get('/admin/cells?search='.urlencode('A2·3'));

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('flatNumber', 3)
            ->where('jumpToCell.row_letter', 'A')
            ->where('jumpToCell.cell_number', 2)
            ->where('jumpToCell.flat_number', 3)
            ->where('searchError', false)
    );

    expect($targetCell)->not->toBeNull();
});

test('a location search without a flat number jumps to the lowest matching flat', function () {
    actingAsAdmin();
    Row::factory()->create(['letter' => 'B', 'cells_count' => 2, 'flats_count' => 3]);

    $response = $this->get('/admin/cells?search=B1');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('flatNumber', 1)
            ->where('jumpToCell.row_letter', 'B')
            ->where('jumpToCell.cell_number', 1)
            ->where('jumpToCell.flat_number', 1)
    );
});

test('a search matching nothing reports a searchError without changing the flat', function () {
    actingAsAdmin();
    Row::factory()->create();

    $response = $this->get('/admin/cells?search=NoSuchThing123');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('flatNumber', 1)
            ->where('jumpToCell', null)
            ->where('searchError', true)
    );
});

test('the warehouse map excludes cells belonging to a different flat', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 2]);

    $response = $this->get('/admin/cells');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('cells', 1)
            ->where('cells.0.flat_number', 1)
    );
});

test('the warehouse map can show a different flat via flat_number', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 2]);
    $secondFlatCell = $row->cells()->where('flat_number', 2)->first();

    $response = $this->get('/admin/cells?flat_number=2');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('flatNumber', 2)
            ->has('cells', 1)
            ->where('cells.0.id', $secondFlatCell->id)
    );
});

test('the warehouse map clamps an out-of-range flat_number instead of erroring', function () {
    actingAsAdmin();
    Row::factory()->create(['cells_count' => 1, 'flats_count' => 2]);

    $response = $this->get('/admin/cells?flat_number=99');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('flatNumber', 2)
    );
});

test('the warehouse map maxFlatNumber reflects the tallest row', function () {
    actingAsAdmin();
    Row::factory()->create(['cells_count' => 1, 'flats_count' => 2]);
    Row::factory()->create(['cells_count' => 1, 'flats_count' => 5]);

    $response = $this->get('/admin/cells');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('maxFlatNumber', 5)
    );
});

test('the warehouse map lists rows ordered by letter', function () {
    actingAsAdmin();
    Row::factory()->create(['letter' => 'C']);
    Row::factory()->create(['letter' => 'A']);
    Row::factory()->create(['letter' => 'B']);

    $response = $this->get('/admin/cells');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('rows.0.letter', 'A')
            ->where('rows.1.letter', 'B')
            ->where('rows.2.letter', 'C')
    );
});

test('a mobile app user cannot view the warehouse map', function () {
    $mobileUser = User::factory()->mobileUser()->create();

    $response = $this->actingAs($mobileUser)->get('/admin/cells');

    $response->assertForbidden();
});

test('an unauthenticated caller is redirected to login when viewing the warehouse map', function () {
    $response = $this->get('/admin/cells');

    $response->assertRedirect(route('login'));
});
