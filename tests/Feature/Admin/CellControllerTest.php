<?php

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('the warehouse map ships both raw product name columns, whatever the panel locale', function () {
    // `lib/productName.ts` picks the label client-side, so the payload is the
    // same in both locales. See .ai/rules/shared-database.md.
    actingAsAdmin();

    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 2, 'flats_count' => 1]);
    $product = Product::factory()->imageUrl(null)->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    // Noise: an untranslated product in the next cell ships an empty ar_name,
    // which is what the frontend resolver falls back on.
    $untranslated = Product::factory()->imageUrl(null)->create(['name' => 'Gadgets', 'ar_name' => '']);
    Pallet::factory()->create([
        'product_id' => $product->id,
        'cell_id' => $row->cells()->where('cell_number', 1)->first()->id,
    ]);
    Pallet::factory()->create([
        'product_id' => $untranslated->id,
        'cell_id' => $row->cells()->where('cell_number', 2)->first()->id,
    ]);

    $response = $this->withSession(['locale' => 'ar'])->get('/admin/cells');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page
            ->where('cells.0.pallet.product_name', 'Widgets')
            ->where('cells.0.pallet.product_ar_name', 'ودجات')
            ->where('cells.1.pallet.product_name', 'Gadgets')
            ->where('cells.1.pallet.product_ar_name', '')
            // cellHighlightSamples builds on the same shared shape, so it has
            // to carry both columns identically or the flat-tab match badges
            // would disagree with the cells they count.
            ->where('cellHighlightSamples.0.pallet.product_name', 'Widgets')
            ->where('cellHighlightSamples.0.pallet.product_ar_name', 'ودجات')
            ->where('cellHighlightSamples.1.pallet.product_name', 'Gadgets')
            ->where('cellHighlightSamples.1.pallet.product_ar_name', '')
    );
});

test('an authenticated admin can view the warehouse map for the default flat, with every property the map renders', function () {
    Carbon::setTestNow('2026-08-01 10:00:00');
    actingAsAdmin();

    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 2, 'flats_count' => 2]);
    $cell = $row->cells()->where('cell_number', 1)->where('flat_number', 1)->first();
    $product = Product::factory()->imageUrl('https://cdn.example.com/widgets.png')->create([
        'name' => 'Widgets',
        'ar_name' => 'ودجات',
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
                ->where('is_active', true)
                ->has('pallet', fn (Assert $palletProp) => $palletProp
                    ->where('id', $pallet->id)
                    ->where('product_id', $product->id)
                    ->where('product_name', 'Widgets')
                    ->where('product_ar_name', 'ودجات')
                    ->where('product_active', true)
                    ->where('product_image_url', 'https://cdn.example.com/widgets.png')
                    ->where('expiration_date', '2026-09-01')
                    ->where('added_at', $pallet->created_at->toIso8601String())
                    ->where('cell_entered_at', null)
                    ->where('is_stale', null)
                    ->where('remaining_boxes', $pallet->remaining_boxes)
                )
            )
            ->where('today', '2026-08-01')
            ->where('initialHighlight.state', null)
            ->where('initialHighlight.productIds', [])
            ->where('initialHighlight.expiresWithinDays', null)
            ->where('initialHighlight.staleAfterDays', null)
            ->where('initialHighlight.expired', false)
            ->where('initialHighlight.inactive', false)
            ->where('jumpToCell', null)
            ->where('searchError', false)
            ->has('filterOptions.products', 0)
            ->has('cellHighlightSamples', 4)
            ->has('cellHighlightSamples.0', fn (Assert $sampleProp) => $sampleProp
                ->where('cell_id', $cell->id)
                ->where('row_letter', 'A')
                ->where('cell_number', 1)
                ->where('flat_number', 1)
                ->where('state', 'full')
                ->where('is_active', true)
                ->has('pallet', fn (Assert $palletProp) => $palletProp
                    ->where('id', $pallet->id)
                    ->where('product_id', $product->id)
                    ->where('product_name', 'Widgets')
                    ->where('product_ar_name', 'ودجات')
                    ->where('product_image_url', 'https://cdn.example.com/widgets.png')
                    ->where('expiration_date', '2026-09-01')
                    ->where('added_at', $pallet->created_at->toIso8601String())
                    ->where('remaining_boxes', $pallet->remaining_boxes)
                    ->missing('product_active')
                    ->missing('cell_entered_at')
                )
            )
            ->has('cellHighlightSamples.1', fn (Assert $sampleProp) => $sampleProp
                ->where('cell_id', fn (int $cellId) => $cellId > 0)
                ->where('row_letter', 'A')
                ->where('cell_number', 1)
                ->where('flat_number', 2)
                ->where('state', 'empty')
                ->where('is_active', true)
                ->where('pallet', null)
            )
            ->has('cellHighlightSamples.2', fn (Assert $sampleProp) => $sampleProp
                ->where('cell_id', fn (int $cellId) => $cellId > 0)
                ->where('row_letter', 'A')
                ->where('cell_number', 2)
                ->where('flat_number', 1)
                ->where('state', 'empty')
                ->where('is_active', true)
                ->where('pallet', null)
            )
            ->has('cellHighlightSamples.3', fn (Assert $sampleProp) => $sampleProp
                ->where('cell_id', fn (int $cellId) => $cellId > 0)
                ->where('row_letter', 'A')
                ->where('cell_number', 2)
                ->where('flat_number', 2)
                ->where('state', 'empty')
                ->where('is_active', true)
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

test('cellHighlightSamples skips product.published and the cellEnteredLog join, unlike the current flat\'s cells query', function () {
    // cellHighlightSamples only ships CellPalletSummary (no product_active,
    // no cell_entered_at), so it has no need for product.published or the
    // cellEnteredLog "of many" join Cell::WITH_ROW_AND_CONTENTS pulls in for
    // the current flat's `cells` prop — narrowing that away is the whole
    // point, since this query runs once for every cell in the warehouse.
    actingAsAdmin();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $product = Product::factory()->create();
    Pallet::factory()->create(['product_id' => $product->id, 'cell_id' => $row->cells()->first()->id]);

    DB::enableQueryLog();
    $this->get('/admin/cells')->assertOk();
    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();

    // Only the current flat's `cells` query touches cell_status_logs
    // (cellEnteredLog's "of many" join) and selects `published`.
    expect($queries->filter(fn (string $sql) => str_contains($sql, 'cell_status_logs')))->toHaveCount(1);
    expect($queries->filter(fn (string $sql) => str_contains($sql, 'published')))->toHaveCount(1);
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

test('a stale_after_days passed from the dashboard seeds the initial highlight filter', function () {
    actingAsAdmin();
    Row::factory()->create();

    $response = $this->get('/admin/cells?stale_after_days=30');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('initialHighlight.staleAfterDays', 30)
    );
});

test('an invalid stale_after_days passed from the dashboard is rejected', function () {
    actingAsAdmin();

    $response = $this->get('/admin/cells?stale_after_days=-1');

    $response->assertInvalid(['stale_after_days']);
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

test('a highlight-filtered deep-link with no flat_number jumps to the first matching flat, in row/cell-number order', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 2, 'flats_count' => 3]);

    // Deliberately gives the lower cell_number the later flat, so the test
    // proves cell_number outranks flat_number in the tie-break — matching
    // the client's `orderedMatches` sort in Cells/Index.vue.
    Pallet::factory()->create([
        'cell_id' => $row->cells()->where('cell_number', 2)->where('flat_number', 2)->first()->id,
        'expiration_date' => '2026-08-01',
    ]);
    Pallet::factory()->create([
        'cell_id' => $row->cells()->where('cell_number', 1)->where('flat_number', 3)->first()->id,
        'expiration_date' => '2026-08-05',
    ]);

    $response = $this->get('/admin/cells?expired=true');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('flatNumber', 3)
            ->where('jumpToCell.row_letter', 'A')
            ->where('jumpToCell.cell_number', 1)
            ->where('jumpToCell.flat_number', 3)
    );

    Carbon::setTestNow();
});

test('an explicit flat_number is respected even when the highlight filter matches nothing on it', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 2]);
    Pallet::factory()->create([
        'cell_id' => $row->cells()->where('flat_number', 2)->first()->id,
        'expiration_date' => '2026-08-01',
    ]);

    $response = $this->get('/admin/cells?expired=true&flat_number=1');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('flatNumber', 1)->where('jumpToCell', null)
    );

    Carbon::setTestNow();
});

test('a highlight-filtered deep-link lands on flat 1 unchanged when it already holds the first match', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 2]);
    Pallet::factory()->create([
        'cell_id' => $row->cells()->where('flat_number', 1)->first()->id,
        'expiration_date' => '2026-08-01',
    ]);
    // Noise: a later match on another flat that must not win the jump ahead
    // of the one already on the default landing flat.
    Pallet::factory()->create([
        'cell_id' => $row->cells()->where('flat_number', 2)->first()->id,
        'expiration_date' => '2026-08-01',
    ]);

    $response = $this->get('/admin/cells?expired=true');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('flatNumber', 1)->where('jumpToCell', null)
    );

    Carbon::setTestNow();
});

test('a product-id deep-link jumps to the flat holding that product, excluding other products', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 2]);
    $matching = Product::factory()->create();
    $other = Product::factory()->create();
    // Noise: a different product on flat 1, which must not win the jump.
    Pallet::factory()->create([
        'product_id' => $other->id,
        'cell_id' => $row->cells()->where('flat_number', 1)->first()->id,
    ]);
    Pallet::factory()->create([
        'product_id' => $matching->id,
        'cell_id' => $row->cells()->where('flat_number', 2)->first()->id,
    ]);

    $response = $this->get("/admin/cells?product_id[]={$matching->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('flatNumber', 2)->where('jumpToCell.flat_number', 2)
    );
});

test('a state deep-link jumps to the flat holding a cell in that state, excluding other states', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 2]);
    // Noise: flat 1 stays "empty" (the default), which must not match "opened".
    $row->cells()->where('flat_number', 2)->first()->update(['state' => CellState::Opened]);

    $response = $this->get('/admin/cells?state=opened');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('flatNumber', 2)->where('jumpToCell.flat_number', 2)
    );
});

test('an inactive-only deep-link jumps to the flat holding the inactive cell, excluding active ones', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 2]);
    // Noise: flat 1 stays active, which must not match "inactive only".
    $row->cells()->where('flat_number', 2)->first()->update(['is_active' => false]);

    $response = $this->get('/admin/cells?is_active=false');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('flatNumber', 2)->where('jumpToCell.flat_number', 2)
    );
});

test('an expires_within_days deep-link jumps to the flat holding the soon-to-expire pallet, excluding one expiring later', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 2]);
    // Noise: expires well outside the 7-day window.
    Pallet::factory()->create([
        'cell_id' => $row->cells()->where('flat_number', 1)->first()->id,
        'expiration_date' => '2026-09-01',
    ]);
    Pallet::factory()->create([
        'cell_id' => $row->cells()->where('flat_number', 2)->first()->id,
        'expiration_date' => '2026-08-15',
    ]);

    $response = $this->get('/admin/cells?expires_within_days=7');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('flatNumber', 2)->where('jumpToCell.flat_number', 2)
    );

    Carbon::setTestNow();
});

test('a stale_after_days deep-link jumps to the flat holding the stale pallet, excluding a fresh one', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 2]);
    // Noise: a freshly-stored pallet, nowhere near the staleness threshold.
    Pallet::factory()->create([
        'cell_id' => $row->cells()->where('flat_number', 1)->first()->id,
    ]);
    Pallet::factory()->stale()->create([
        'cell_id' => $row->cells()->where('flat_number', 2)->first()->id,
    ]);

    $response = $this->get('/admin/cells?stale_after_days=25');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('flatNumber', 2)->where('jumpToCell.flat_number', 2)
    );
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

test('a location search with no separator auto-splits the last digit as the flat number', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 15, 'flats_count' => 5]);
    $targetCell = $row->cells()->where('cell_number', 12)->where('flat_number', 3)->first();

    $response = $this->get('/admin/cells?search=A123');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('flatNumber', 3)
            ->where('jumpToCell.row_letter', 'A')
            ->where('jumpToCell.cell_number', 12)
            ->where('jumpToCell.flat_number', 3)
            ->where('searchError', false)
    );

    expect($targetCell)->not->toBeNull();
});

test('a no-separator search that does not split falls back to the plain cell-number search', function () {
    actingAsAdmin();
    Row::factory()->create(['letter' => 'C', 'cells_count' => 99, 'flats_count' => 1]);

    $response = $this->get('/admin/cells?search=C99');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('flatNumber', 1)
            ->where('jumpToCell.row_letter', 'C')
            ->where('jumpToCell.cell_number', 99)
            ->where('jumpToCell.flat_number', 1)
            ->where('searchError', false)
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
    actingAsMobilePanelUser();

    $response = $this->get('/admin/cells');

    $response->assertForbidden();
});

test('an unauthenticated caller is redirected to login when viewing the warehouse map', function () {
    $response = $this->get('/admin/cells');

    $response->assertRedirect(route('login'));
});

test('an authenticated user can export a QR code image for a single cell', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();

    $response = $this->get("/admin/cells/{$cell->id}/export-qr");

    $response->assertOk();
    $response->assertHeader('content-type', 'image/svg+xml');

    $svg = $response->getContent();
    // A real QR is embedded as a base64 SVG data URI — regression guard for the
    // QR silently failing to render rather than just the surrounding text.
    expect($svg)->toContain('data:image/svg+xml;base64,');
    expect($svg)->toContain(Cell::slotLabel('Z', $cell->cell_number, $cell->flat_number));

    // Regression guard: the single-cell SVG export and the row-wide PDF sheet
    // (see RowControllerTest) must describe the same cell identically — both
    // build the description from BuildsCellQrLabels::cellQrLabelDescription().
    $description = __('messages.qr_label_description', [
        'row' => 'Z',
        'cell' => $cell->cell_number,
        'flat' => $cell->flat_number,
    ]);
    expect($svg)->toContain($description);
});

test('a single-cell QR export uses the configured QR code size instead of the default', function () {
    actingAsAdmin();
    Setting::factory()->create(['qr_code_width' => 400, 'qr_code_height' => 500]);
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();

    $response = $this->get("/admin/cells/{$cell->id}/export-qr");

    $response->assertOk();
    $svg = $response->getContent();
    // The label canvas width is exactly the configured total-box width, and
    // the embedded QR square is that width minus padding on both sides
    // (padding=20 — see BuildsQrLabels::qrLabelImage()); qr_code_height is a
    // ceiling on the whole label including text, not the QR's own size.
    expect($svg)->toContain('<svg xmlns="http://www.w3.org/2000/svg" width="400"')
        ->and($svg)->toContain('width="360" height="360"/>');
});

test('a mobile app user cannot export a single cells QR code', function () {
    actingAsMobilePanelUser();
    $row = Row::factory()->create();
    $cell = $row->cells()->first();

    $response = $this->get("/admin/cells/{$cell->id}/export-qr");

    $response->assertForbidden();
});

test('an unauthenticated caller is redirected to login when exporting a single cells QR code', function () {
    $row = Row::factory()->create();
    $cell = $row->cells()->first();

    $response = $this->get("/admin/cells/{$cell->id}/export-qr");

    $response->assertRedirect(route('login'));
});

test('exporting a QR code for a non-existent cell returns a 404', function () {
    actingAsAdmin();

    $response = $this->get('/admin/cells/999999/export-qr');

    $response->assertNotFound();
});

test('is_active=0 passed from the dashboard seeds the initial highlight filter as inactive-only', function () {
    actingAsAdmin();
    Row::factory()->create();

    $response = $this->get('/admin/cells?is_active=0');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('initialHighlight.inactive', true)
    );
});

test('an invalid is_active value passed from the dashboard is rejected', function () {
    actingAsAdmin();

    $response = $this->get('/admin/cells?is_active=bogus');

    $response->assertInvalid(['is_active']);
});

test('an authenticated admin can deactivate a cell regardless of its occupancy', function () {
    $admin = actingAsAdmin();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    $product = Product::factory()->create();
    $pallet = Pallet::factory()->create(['product_id' => $product->id, 'cell_id' => $cell->id]);
    $cell->update(['state' => CellState::Full]);

    $response = $this->post("/admin/cells/{$cell->id}/toggle-active", ['note' => 'Sensor malfunction']);

    $response->assertRedirect();
    expect($cell->fresh()->is_active)->toBeFalse();
    expect($cell->fresh()->state)->toBe(CellState::Full);

    $log = CellStatusLog::query()->latest('id')->first();
    expect($log->cell_id)->toBe($cell->id);
    expect($log->action)->toBe(CellLogAction::Deactivated);
    expect($log->from_state)->toBe(CellState::Full);
    expect($log->to_state)->toBe(CellState::Full);
    expect($log->user_id)->toBe($admin->id);
    expect($log->note)->toBe('Sensor malfunction');
    expect($log->product_id)->toBe($product->id);
    expect($log->pallet_id)->toBe($pallet->id);
});

test('an authenticated admin can reactivate a cell, and its occupancy state is never touched by either toggle', function () {
    $admin = actingAsAdmin();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    $product = Product::factory()->create();
    $pallet = Pallet::factory()->create(['product_id' => $product->id, 'cell_id' => $cell->id]);
    $cell->update(['state' => CellState::Opened]);

    CellStatusLog::create([
        'cell_id' => $cell->id,
        'action' => CellLogAction::Opened,
        'from_state' => CellState::Full,
        'to_state' => CellState::Opened,
        'product_id' => $product->id,
        'pallet_id' => $pallet->id,
        'user_id' => $admin->id,
    ]);

    $this->post("/admin/cells/{$cell->id}/toggle-active")->assertRedirect();
    expect($cell->fresh()->is_active)->toBeFalse();
    expect($cell->fresh()->state)->toBe(CellState::Opened);

    $this->post("/admin/cells/{$cell->id}/toggle-active")->assertRedirect();

    expect($cell->fresh()->is_active)->toBeTrue();
    expect($cell->fresh()->state)->toBe(CellState::Opened);

    $log = CellStatusLog::query()->latest('id')->first();
    expect($log->action)->toBe(CellLogAction::Reactivated);
    expect($log->from_state)->toBe(CellState::Opened);
    expect($log->to_state)->toBe(CellState::Opened);
});

test('a mobile app user cannot toggle a cells active status', function () {
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    actingAsMobilePanelUser();

    $response = $this->post("/admin/cells/{$cell->id}/toggle-active");

    $response->assertForbidden();
    expect($cell->fresh()->is_active)->toBeTrue();
});

test('an unauthenticated caller cannot toggle a cells active status', function () {
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();

    $response = $this->post("/admin/cells/{$cell->id}/toggle-active");

    $response->assertRedirect(route('login'));
    expect($cell->fresh()->is_active)->toBeTrue();
});

test('toggling the active status of a non-existent cell returns a 404', function () {
    actingAsAdmin();

    $response = $this->post('/admin/cells/999999/toggle-active');

    $response->assertNotFound();
});

test('toggling a cells active status redirects to the cell map by default', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();

    $response = $this->post("/admin/cells/{$cell->id}/toggle-active");

    $response->assertRedirect(route('admin.cells.index'));
});

test('toggling a cells active status preserves the current map query on redirect', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 2]);
    $cell = $row->cells()->where('flat_number', 2)->first();

    $response = $this->post("/admin/cells/{$cell->id}/toggle-active?flat_number=2");

    $response->assertRedirect('/admin/cells?flat_number=2');
});

test('toggling a cells active status from a row page redirects back to that row page instead of the cell map', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();

    $response = $this->post("/admin/cells/{$cell->id}/toggle-active", ['return_to' => 'row']);

    $response->assertRedirect("/admin/rows/{$row->letter}");
});
