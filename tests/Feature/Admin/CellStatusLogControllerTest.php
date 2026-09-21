<?php

use App\Enums\CellLogAction;
use App\Enums\CellLogFlagReason;
use App\Enums\CellState;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\CellStatusLogFlag;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\SeedsCellStatusLogFixtures;

uses(SeedsCellStatusLogFixtures::class);

test('an authenticated admin can view the cell log with every property the table renders', function () {
    Carbon::setTestNow('2026-08-01 10:00:00');
    actingAsAdmin();
    $mover = User::factory()->mobileUser()->create(['name' => 'Bob Mover']);

    $fromRow = Row::factory()->create(['letter' => 'A', 'cells_count' => 2, 'flats_count' => 2]);
    $toRow = Row::factory()->create(['letter' => 'B', 'cells_count' => 2, 'flats_count' => 2]);
    $fromCell = $fromRow->cells()->where('cell_number', 1)->where('flat_number', 2)->first();
    $toCell = $toRow->cells()->where('cell_number', 2)->where('flat_number', 1)->first();

    $product = Product::factory()->imageUrl('https://cdn.example.com/widgets.png')->boxesCount(10)->create([
        'name' => 'Widgets',
        'ar_name' => 'ودجات',
    ]);
    $pallet = Pallet::factory()->create([
        'product_id' => $product->id,
        'cell_id' => $toCell->id,
        'expiration_date' => '2026-09-01',
    ]);

    $log = CellStatusLog::factory()->create([
        'cell_id' => $fromCell->id,
        'related_cell_id' => $toCell->id,
        'action' => CellLogAction::TransferredOut,
        'from_state' => CellState::Full,
        'to_state' => CellState::Empty,
        'product_id' => $product->id,
        'pallet_id' => $pallet->id,
        'boxes_count' => 7,
        'user_id' => $mover->id,
        'note' => 'Consolidating stock.',
    ]);

    $response = $this->get('/admin/cell-logs');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/CellStatusLogs/Index')
            ->has('logs.data', 1)
            ->has('logs.data.0', fn (Assert $logProp) => $logProp
                ->where('id', $log->id)
                ->where('action', 'transferred_out')
                ->where('from_state', 'full')
                ->where('to_state', 'empty')
                ->where('note', 'Consolidating stock.')
                ->where('boxes_count', 7)
                ->where('created_at', $log->created_at->toIso8601String())
                ->has('cell', fn (Assert $cell) => $cell
                    ->where('row_letter', 'A')
                    ->where('cell_number', 1)
                    ->where('flat_number', 2)
                )
                ->has('related_cell', fn (Assert $relatedCell) => $relatedCell
                    ->where('row_letter', 'B')
                    ->where('cell_number', 2)
                    ->where('flat_number', 1)
                )
                ->has('product', fn (Assert $productProp) => $productProp
                    ->where('id', $product->id)
                    ->where('name', 'Widgets')
                    ->where('ar_name', 'ودجات')
                    ->where('image_url', 'https://cdn.example.com/widgets.png')
                    ->where('boxes_count', 10)
                    ->where('active', true)
                )
                ->has('pallet', fn (Assert $palletProp) => $palletProp
                    ->where('id', $pallet->id)
                    ->where('expiration_date', '2026-09-01')
                )
                ->has('user', fn (Assert $userProp) => $userProp
                    ->where('id', $mover->id)
                    ->where('name', 'Bob Mover')
                )
                ->where('next_log_at', null)
                ->where('duration_seconds', 0)
                ->where('flagged', false)
                ->where('flags', [])
            )
            ->has('filterOptions.rows', 2)
            ->where('filterOptions.maxColumnNumber', 2)
            ->has('filterOptions.actions', 8)
    );

    Carbon::setTestNow();
});

test('the cell log ships both raw product name columns, falling back to none of them', function () {
    actingAsAdmin();

    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 2, 'flats_count' => 1]);
    $product = Product::factory()->imageUrl(null)->boxesCount(10)->create([
        'name' => 'Widgets',
        'ar_name' => 'ودجات',
    ]);
    // Noise: an untranslated product's log ships an empty ar_name in the same
    // list, which is what the frontend resolver falls back on.
    $untranslated = Product::factory()->imageUrl(null)->boxesCount(4)->create([
        'name' => 'Gadgets',
        'ar_name' => '',
    ]);

    CellStatusLog::factory()->create([
        'cell_id' => $row->cells()->where('cell_number', 1)->first()->id,
        'product_id' => $product->id,
    ]);
    CellStatusLog::factory()->create([
        'cell_id' => $row->cells()->where('cell_number', 2)->first()->id,
        'product_id' => $untranslated->id,
    ]);

    // Filtered one product at a time so the assertion doesn't depend on the
    // listing's default ordering.
    $this->withSession(['locale' => 'ar'])->get("/admin/cell-logs?product_id[]={$product->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.product.name', 'Widgets')
            ->where('logs.data.0.product.ar_name', 'ودجات'));

    $this->withSession(['locale' => 'ar'])->get("/admin/cell-logs?product_id[]={$untranslated->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.product.name', 'Gadgets')
            ->where('logs.data.0.product.ar_name', ''));
});

test('the cell log hydrates the product filter chips with both raw name columns', function () {
    actingAsAdmin();
    $selected = Product::factory()->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    Product::factory()->create(['name' => 'Unselected Gadgets', 'ar_name' => 'أدوات']);

    $response = $this->withSession(['locale' => 'ar'])->get("/admin/cell-logs?product_id[]={$selected->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('filterOptions.products', 1)
            ->where('filterOptions.products.0', ['id' => $selected->id, 'name' => 'Widgets', 'ar_name' => 'ودجات'])
    );
});

test('the cell log hydrates only the selected product ids for the product filter, not every product', function () {
    actingAsAdmin();
    $selected = Product::factory()->create(['name' => 'Widgets']);
    Product::factory()->create(['name' => 'Unselected Gadgets']);

    $response = $this->get("/admin/cell-logs?product_id[]={$selected->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('filterOptions.products', 1)
            ->where('filterOptions.products.0.id', $selected->id)
            ->where('filterOptions.products.0.name', 'Widgets')
    );
});

test('the cell log has no pre-selected products for the product filter when nothing is selected', function () {
    actingAsAdmin();
    Product::factory()->create(['name' => 'Widgets']);

    $response = $this->get('/admin/cell-logs');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('filterOptions.products', 0)
    );
});

test('the cell log includes the next same-pallet log timestamp as next_log_at', function () {
    actingAsAdmin();
    $pallet = Pallet::factory()->create();

    $stored = backdate(CellStatusLog::factory()->create(['pallet_id' => $pallet->id, 'action' => CellLogAction::Stored]), '2026-08-01 10:00:00');
    $opened = backdate(CellStatusLog::factory()->create(['pallet_id' => $pallet->id, 'action' => CellLogAction::Opened]), '2026-08-01 12:00:00');

    $response = $this->get('/admin/cell-logs');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 2)
            ->where('logs.data.1.id', $stored->id)
            ->where('logs.data.1.next_log_at', $opened->created_at->toIso8601String())
            ->where('logs.data.1.duration_seconds', 7200)
    );
});

test('the cell log skips the paired transferred-in log when computing next_log_at for a transferred-out entry', function () {
    actingAsAdmin();
    $pallet = Pallet::factory()->create();
    $sourceCell = Cell::factory()->create();
    $destinationCell = Cell::factory()->create();

    [$transferredOut] = createTransferPair($pallet, $sourceCell, $destinationCell, '2026-08-01 10:00:00', '2026-08-01 10:00:01');

    $emptied = backdate(CellStatusLog::factory()->create([
        'pallet_id' => $pallet->id,
        'cell_id' => $destinationCell->id,
        'action' => CellLogAction::Emptied,
    ]), '2026-08-01 14:00:01');

    $response = $this->get('/admin/cell-logs');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 3)
            ->where('logs.data.2.id', $transferredOut->id)
            ->where('logs.data.2.next_log_at', $emptied->created_at->toIso8601String())
            ->where('logs.data.2.duration_seconds', 14401)
    );
});

test('the cell log paginates instead of returning everything at once', function () {
    actingAsAdmin();
    $this->seedPaginationOverflowFixture(perPage: 20);

    $response = $this->get('/admin/cell-logs');

    assertInertiaPaginates($response, 'logs', 20, 25, 'Admin/CellStatusLogs/Index');
});

test('the cell log respects a per_page query parameter', function () {
    actingAsAdmin();
    $this->seedPaginationOverflowFixture(perPage: 20);

    $response = $this->get('/admin/cell-logs?per_page=10');

    assertInertiaPaginates($response, 'logs', 10, 25, 'Admin/CellStatusLogs/Index');
    $response->assertInertia(fn (Assert $page) => $page->where('filters.per_page', 10));
});

test('the cell log rejects a per_page value outside the allowed options', function () {
    actingAsAdmin();

    $response = $this->get('/admin/cell-logs?per_page=999');

    $response->assertSessionHasErrors('per_page');
});

test('next_log_at is found even when the next log for the same pallet falls on a different page', function () {
    actingAsAdmin();
    $pallet = Pallet::factory()->create();

    $older = backdate(CellStatusLog::factory()->create(['pallet_id' => $pallet->id]), '2026-08-01 08:00:00');
    $newer = backdate(CellStatusLog::factory()->create(['pallet_id' => $pallet->id]), '2026-08-01 09:00:00');

    // 19 unrelated logs, all newer than $newer, so the listing (ordered by
    // latest()) puts $newer plus these 19 on page 1 (20 rows) and pushes
    // $older, the single oldest row, onto page 2 by itself.
    collect(range(1, 19))->each(function (int $i) {
        backdate(CellStatusLog::factory()->create(), sprintf('2026-08-01 10:%02d:00', $i));
    });

    $response = $this->get('/admin/cell-logs?page=2');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $older->id)
            ->where('logs.data.0.next_log_at', $newer->created_at->toIso8601String())
            ->where('logs.data.0.duration_seconds', 3600)
    );
});

test('next_log_at is found even when the next log for the same pallet is excluded by the current filters', function () {
    actingAsAdmin();
    $pallet = Pallet::factory()->create();

    $stored = backdate(CellStatusLog::factory()->create(['pallet_id' => $pallet->id, 'action' => CellLogAction::Stored]), '2026-08-01 10:00:00');
    $opened = backdate(CellStatusLog::factory()->create(['pallet_id' => $pallet->id, 'action' => CellLogAction::Opened]), '2026-08-01 12:00:00');

    $response = $this->get('/admin/cell-logs?action[]=stored');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $stored->id)
            ->where('logs.data.0.next_log_at', $opened->created_at->toIso8601String())
            ->where('logs.data.0.duration_seconds', 7200)
    );
});

test('a mobile app user cannot view the cell log', function () {
    actingAsMobilePanelUser();

    $response = $this->get('/admin/cell-logs');

    $response->assertForbidden();
});

test('an unauthenticated caller is redirected to login', function () {
    $response = $this->get('/admin/cell-logs');

    $response->assertRedirect(route('login'));
});

test('the cell log can be filtered by product, excluding entries for other products', function () {
    actingAsAdmin();
    ['product' => $product, 'matching' => $matching] = $this->seedProductFilterFixture();

    $response = $this->get("/admin/cell-logs?product_id[]={$product->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
    );
});

test('the cell log can be filtered by multiple products at once, excluding entries for the remaining product', function () {
    actingAsAdmin();
    [
        'product' => $product,
        'otherProduct' => $otherProduct,
        'matchingA' => $matchingA,
        'matchingB' => $matchingB,
    ] = $this->seedMultipleProductFilterFixture();

    $response = $this->get("/admin/cell-logs?product_id[]={$product->id}&product_id[]={$otherProduct->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 2)
            ->where('logs.data.0.id', $matchingB->id)
            ->where('logs.data.1.id', $matchingA->id)
    );
});

test('the cell log shows a pallet id with a null expiration date once the pallet has been emptied and deleted', function () {
    actingAsAdmin();
    $pallet = Pallet::factory()->create();
    $palletId = $pallet->id;

    $log = CellStatusLog::factory()->create([
        'action' => CellLogAction::Emptied,
        'pallet_id' => $palletId,
    ]);

    $pallet->delete();

    $response = $this->get('/admin/cell-logs');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data.0', fn (Assert $logProp) => $logProp
            ->where('id', $log->id)
            ->has('pallet', fn (Assert $palletProp) => $palletProp
                ->where('id', $palletId)
                ->where('expiration_date', null)
            )
            ->etc()
        )
    );
});

test('the cell log can be filtered by pallet, excluding entries for other pallets, even after the pallet is deleted', function () {
    actingAsAdmin();
    ['palletId' => $palletId, 'matching' => $matching] = $this->seedPalletFilterFixture();

    $response = $this->get("/admin/cell-logs?pallet_id={$palletId}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
    );
});

test('the cell log can be filtered by row, excluding entries for other rows', function () {
    actingAsAdmin();
    ['row' => $row, 'matching' => $matching] = $this->seedRowFilterFixture();

    $response = $this->get("/admin/cell-logs?row_id={$row->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
    );
});

test('the cell log can be filtered by column number, excluding entries for other columns', function () {
    actingAsAdmin();
    ['matching' => $matching] = $this->seedColumnFilterFixture();

    $response = $this->get('/admin/cell-logs?column_number=1');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
    );
});

test('the cell log can be filtered by who did it, excluding entries by other users', function () {
    actingAsAdmin();
    ['mover' => $mover, 'matching' => $matching] = $this->seedUserFilterFixture();

    $response = $this->get("/admin/cell-logs?user_id[]={$mover->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
    );
});

test('the cell log can be filtered by multiple users at once, excluding entries by the remaining user', function () {
    actingAsAdmin();
    [
        'mover' => $mover,
        'otherMover' => $otherMover,
        'matchingA' => $matchingA,
        'matchingB' => $matchingB,
    ] = $this->seedMultipleUserFilterFixture();

    $response = $this->get("/admin/cell-logs?user_id[]={$mover->id}&user_id[]={$otherMover->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 2)
            ->where('logs.data.0.id', $matchingB->id)
            ->where('logs.data.1.id', $matchingA->id)
    );
});

test('the cell log can be filtered by status change, excluding entries for other actions', function () {
    actingAsAdmin();
    ['matching' => $matching] = $this->seedActionFilterFixture();

    $response = $this->get('/admin/cell-logs?action[]=opened');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
    );
});

test('the cell log can be filtered by multiple status changes at once, excluding entries for the remaining action', function () {
    actingAsAdmin();
    ['opened' => $opened, 'emptied' => $emptied] = $this->seedMultipleActionFilterFixture();

    $response = $this->get('/admin/cell-logs?action[]=opened&action[]=emptied');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 2)
            ->where('logs.data.0.id', $emptied->id)
            ->where('logs.data.1.id', $opened->id)
    );
});

test('the cell log can be filtered by a date range, excluding entries outside it', function () {
    actingAsAdmin();
    ['matching' => $matching] = $this->seedDateRangeFilterFixture();

    $response = $this->get('/admin/cell-logs?date_from=2026-06-01&date_to=2026-06-30');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
    );
});

test('the cell log can be filtered by created_within_days, excluding entries older than that window', function () {
    Carbon::setTestNow('2026-08-15 12:00:00');
    actingAsAdmin();
    ['withinWindow' => $withinWindow] = $this->seedCreatedWithinDaysFixture();

    $response = $this->get('/admin/cell-logs?created_within_days=7');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $withinWindow->id)
    );

    Carbon::setTestNow();
});

test('filtering the cell log by created_within_days together with a date range is rejected', function () {
    actingAsAdmin();

    $response = $this->get('/admin/cell-logs?created_within_days=7&date_from=2026-06-01');

    $response->assertSessionHasErrors('created_within_days');
});

test('an out-of-range sort_direction is rejected on the cell log', function () {
    actingAsAdmin();

    $this->get('/admin/cell-logs?sort_direction=sideways')
        ->assertSessionHasErrors('sort_direction');
});

test('the cell log can be filtered by pallet expiration date range, excluding entries outside it', function () {
    actingAsAdmin();
    ['matching' => $matching] = $this->seedPalletExpirationRangeFixture();

    $response = $this->get('/admin/cell-logs?expiration_date_from=2026-06-01&expiration_date_to=2026-06-30');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
    );
});

test('the cell log can be filtered by expires_within_days, excluding pallets expiring after that window', function () {
    Carbon::setTestNow('2026-08-15 12:00:00');
    actingAsAdmin();
    ['matching' => $matching] = $this->seedExpiresWithinDaysFixture();

    $response = $this->get('/admin/cell-logs?expires_within_days=7');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
    );

    Carbon::setTestNow();
});

test('the pallet expiration filters compare expiration_date directly, without wrapping it in a date() function', function () {
    // expiration_date is already a DATE column — date()/strftime() around it
    // makes the comparison a function of the column, which the index added
    // for it cannot satisfy on MySQL. See .ai/rules/shared-database.md.
    actingAsAdmin();
    ['matching' => $matching] = $this->seedPalletExpirationRangeFixture();

    DB::enableQueryLog();
    $this->get('/admin/cell-logs?expiration_date_from=2026-06-01&expiration_date_to=2026-06-30')->assertOk();
    $queries = collect(DB::getQueryLog())->pluck('query')->implode(' | ');
    DB::disableQueryLog();

    expect($matching)->not->toBeNull();
    expect($queries)->toContain('"expiration_date"')
        ->and($queries)->not->toContain('date("expiration_date")')
        ->and($queries)->not->toContain("strftime('Date', \"expiration_date\")");
});

test('filtering the cell log by expires_within_days together with an expiration date range is rejected', function () {
    actingAsAdmin();

    $response = $this->get('/admin/cell-logs?expires_within_days=7&expiration_date_from=2026-06-01');

    $response->assertSessionHasErrors('expires_within_days');
});

test('the cell log defaults to newest-first when no sort is requested', function () {
    actingAsAdmin();
    ['older' => $older, 'newer' => $newer] = $this->seedCreatedAtOrderFixture();

    $response = $this->get('/admin/cell-logs');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 2)
            ->where('logs.data.0.id', $newer->id)
            ->where('logs.data.1.id', $older->id)
    );
});

test('the cell log can be sorted by log date ascending', function () {
    actingAsAdmin();
    ['older' => $older, 'newer' => $newer] = $this->seedCreatedAtOrderFixture();

    $response = $this->get('/admin/cell-logs?sort_by=created_at&sort_direction=asc');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 2)
            ->where('logs.data.0.id', $older->id)
            ->where('logs.data.1.id', $newer->id)
    );
});

test('the cell log can be sorted by pallet expiration date, with a direction, placing null expiration dates first ascending', function () {
    actingAsAdmin();

    $noExpiration = CellStatusLog::factory()->create(['pallet_id' => null]);
    ['soon' => $soon, 'late' => $late] = $this->seedExpirationDateOrderFixture();

    $response = $this->get('/admin/cell-logs?sort_by=expiration_date&sort_direction=asc');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 3)
            ->where('logs.data.0.id', $noExpiration->id)
            ->where('logs.data.1.id', $soon->id)
            ->where('logs.data.2.id', $late->id)
    );

    $descResponse = $this->get('/admin/cell-logs?sort_by=expiration_date&sort_direction=desc');

    $descResponse->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 3)
            ->where('logs.data.0.id', $late->id)
            ->where('logs.data.1.id', $soon->id)
            ->where('logs.data.2.id', $noExpiration->id)
    );
});

test('the cell log can be filtered by flagged, excluding unflagged entries', function () {
    actingAsAdmin();

    // Pinned inside working hours: CellStatusLogObserver auto-flags any log
    // created outside config('cell_status_log_flags.off_hours'), so the log
    // this test needs to be unflagged is not one in the evening.
    Carbon::setTestNow('2026-08-01 12:00:00');

    $flagged = CellStatusLog::factory()->create();
    CellStatusLogFlag::factory()->create(['cell_status_log_id' => $flagged->id]);

    CellStatusLog::factory()->create();

    $response = $this->get('/admin/cell-logs?flagged=true');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $flagged->id)
    );
});

test('the cell log exposes who acknowledged a flag, and null for an unacknowledged one', function () {
    actingAsAdmin();

    Carbon::setTestNow('2026-08-01 12:00:00');

    $acknowledger = User::factory()->create(['name' => 'Alice Admin']);

    $log = CellStatusLog::factory()->create();
    $unacknowledged = CellStatusLogFlag::factory()->create(['cell_status_log_id' => $log->id, 'reason' => CellLogFlagReason::OffHours]);
    $acknowledged = CellStatusLogFlag::factory()->create([
        'cell_status_log_id' => $log->id,
        'reason' => CellLogFlagReason::QuickFlip,
        'acknowledged_at' => now(),
        'acknowledged_by' => $acknowledger->id,
    ]);

    $response = $this->get('/admin/cell-logs');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data.0.flags', 2)
            ->where('logs.data.0.flags.0.id', $unacknowledged->id)
            ->where('logs.data.0.flags.0.acknowledged_by', null)
            ->where('logs.data.0.flags.1.id', $acknowledged->id)
            ->where('logs.data.0.flags.1.acknowledged_by', ['id' => $acknowledger->id, 'name' => 'Alice Admin'])
    );
});

test('an authenticated admin can acknowledge every unacknowledged flag on a cell log', function () {
    $admin = actingAsAdmin();
    $log = CellStatusLog::factory()->create();
    $flag = CellStatusLogFlag::factory()->create(['cell_status_log_id' => $log->id, 'reason' => CellLogFlagReason::OffHours]);
    $alreadyAcknowledged = CellStatusLogFlag::factory()->create([
        'cell_status_log_id' => $log->id,
        'reason' => CellLogFlagReason::QuickFlip,
        'acknowledged_at' => '2026-08-01 00:00:00',
        'acknowledged_by' => User::factory()->create()->id,
    ]);

    $response = $this->post("/admin/cell-logs/{$log->id}/acknowledge-flags");

    // Returns to the log list by default (no return_to sent).
    $response->assertRedirect(route('admin.cell-logs.index'));
    $freshFlag = $flag->fresh();
    expect($freshFlag->acknowledged_at)->not->toBeNull();
    expect($freshFlag->acknowledged_by)->toBe($admin->id);

    // Already-acknowledged flags are left untouched, not re-stamped.
    expect($alreadyAcknowledged->fresh()->acknowledged_at->toDateTimeString())->toBe('2026-08-01 00:00:00');
});

test('acknowledging flags redirects back with the current filters preserved', function () {
    actingAsAdmin();
    $log = CellStatusLog::factory()->create();
    CellStatusLogFlag::factory()->create(['cell_status_log_id' => $log->id]);

    $response = $this->post("/admin/cell-logs/{$log->id}/acknowledge-flags?flagged=true");

    $response->assertRedirect(route('admin.cell-logs.index', ['flagged' => 'true']));
});

test('acknowledging flags with return_to=user redirects to that user\'s action history instead', function () {
    actingAsAdmin();
    $mover = User::factory()->mobileUser()->create();
    $log = CellStatusLog::factory()->create(['user_id' => $mover->id]);
    CellStatusLogFlag::factory()->create(['cell_status_log_id' => $log->id]);

    $response = $this->post("/admin/cell-logs/{$log->id}/acknowledge-flags?per_page=50", ['return_to' => 'user']);

    $response->assertRedirect(route('admin.users.show', ['user' => $mover->id, 'per_page' => '50']));
});

test('acknowledging flags on a non-existent cell log returns a 404', function () {
    actingAsAdmin();

    $response = $this->post('/admin/cell-logs/999999/acknowledge-flags');

    $response->assertNotFound();
});

test('a mobile app user cannot acknowledge cell log flags', function () {
    actingAsMobilePanelUser();
    $log = CellStatusLog::factory()->create();
    $flag = CellStatusLogFlag::factory()->create(['cell_status_log_id' => $log->id]);

    $response = $this->post("/admin/cell-logs/{$log->id}/acknowledge-flags");

    $response->assertForbidden();
    expect($flag->fresh()->acknowledged_at)->toBeNull();
});

test('an unauthenticated caller cannot acknowledge cell log flags', function () {
    $log = CellStatusLog::factory()->create();
    $flag = CellStatusLogFlag::factory()->create(['cell_status_log_id' => $log->id]);

    $response = $this->post("/admin/cell-logs/{$log->id}/acknowledge-flags");

    $response->assertRedirect(route('login'));
    expect($flag->fresh()->acknowledged_at)->toBeNull();
});
