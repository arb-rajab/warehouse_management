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
use Inertia\Testing\AssertableInertia as Assert;

test('an authenticated admin can view the cell log with every property the table renders', function () {
    Carbon::setTestNow('2026-08-01 10:00:00');
    actingAsAdmin();
    $mover = User::factory()->mobileUser()->create(['name' => 'Bob Mover']);

    $fromRow = Row::factory()->create(['letter' => 'A', 'cells_count' => 2, 'flats_count' => 2]);
    $toRow = Row::factory()->create(['letter' => 'B', 'cells_count' => 2, 'flats_count' => 2]);
    $fromCell = $fromRow->cells()->where('cell_number', 1)->where('flat_number', 2)->first();
    $toCell = $toRow->cells()->where('cell_number', 2)->where('flat_number', 1)->first();

    $product = Product::factory()->create([
        'name' => 'Widgets',
        'image_url' => 'https://cdn.example.com/widgets.png',
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
                    ->where('image_url', 'https://cdn.example.com/widgets.png')
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
            ->has('filterOptions.actions', 5)
    );

    Carbon::setTestNow();
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

    CellStatusLog::factory()->count(30)->create();

    $response = $this->get('/admin/cell-logs');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/CellStatusLogs/Index')
            ->has('logs.data', 25)
            ->where('logs.meta.total', 30)
    );
});

test('next_log_at is found even when the next log for the same pallet falls on a different page', function () {
    actingAsAdmin();
    $pallet = Pallet::factory()->create();

    $older = backdate(CellStatusLog::factory()->create(['pallet_id' => $pallet->id]), '2026-08-01 08:00:00');
    $newer = backdate(CellStatusLog::factory()->create(['pallet_id' => $pallet->id]), '2026-08-01 09:00:00');

    // 24 unrelated logs, all newer than $newer, so the listing (ordered by
    // latest()) puts $newer plus these 24 on page 1 (25 rows) and pushes
    // $older, the single oldest row, onto page 2 by itself.
    collect(range(1, 24))->each(function (int $i) {
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
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();

    $matching = CellStatusLog::factory()->create(['product_id' => $product->id]);
    CellStatusLog::factory()->create(['product_id' => $otherProduct->id]);

    $response = $this->get("/admin/cell-logs?product_id[]={$product->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
    );
});

test('the cell log can be filtered by multiple products at once, excluding entries for the remaining product', function () {
    actingAsAdmin();
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();
    $thirdProduct = Product::factory()->create();

    $matchingA = CellStatusLog::factory()->create(['product_id' => $product->id]);
    $matchingB = CellStatusLog::factory()->create(['product_id' => $otherProduct->id]);
    CellStatusLog::factory()->create(['product_id' => $thirdProduct->id]);

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
    $pallet = Pallet::factory()->create();
    $palletId = $pallet->id;
    $otherPallet = Pallet::factory()->create();

    $matching = CellStatusLog::factory()->create(['pallet_id' => $palletId]);
    CellStatusLog::factory()->create(['pallet_id' => $otherPallet->id]);

    $pallet->delete();

    $response = $this->get("/admin/cell-logs?pallet_id={$palletId}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
    );
});

test('the cell log can be filtered by row, excluding entries for other rows', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $otherRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    $otherCell = $otherRow->cells()->first();

    $matching = CellStatusLog::factory()->create(['cell_id' => $cell->id]);
    CellStatusLog::factory()->create(['cell_id' => $otherCell->id]);

    $response = $this->get("/admin/cell-logs?row_id={$row->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
    );
});

test('the cell log can be filtered by column number, excluding entries for other columns', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);
    $columnOneCell = $row->cells()->where('cell_number', 1)->first();
    $columnTwoCell = $row->cells()->where('cell_number', 2)->first();

    $matching = CellStatusLog::factory()->create(['cell_id' => $columnOneCell->id]);
    CellStatusLog::factory()->create(['cell_id' => $columnTwoCell->id]);

    $response = $this->get('/admin/cell-logs?column_number=1');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
    );
});

test('the cell log can be filtered by who did it, excluding entries by other users', function () {
    actingAsAdmin();
    $mover = User::factory()->create();
    $otherMover = User::factory()->create();

    $matching = CellStatusLog::factory()->create(['user_id' => $mover->id]);
    CellStatusLog::factory()->create(['user_id' => $otherMover->id]);

    $response = $this->get("/admin/cell-logs?user_id[]={$mover->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
    );
});

test('the cell log can be filtered by multiple users at once, excluding entries by the remaining user', function () {
    actingAsAdmin();
    $mover = User::factory()->create();
    $otherMover = User::factory()->create();
    $thirdMover = User::factory()->create();

    $matchingA = CellStatusLog::factory()->create(['user_id' => $mover->id]);
    $matchingB = CellStatusLog::factory()->create(['user_id' => $otherMover->id]);
    CellStatusLog::factory()->create(['user_id' => $thirdMover->id]);

    $response = $this->get("/admin/cell-logs?user_id[]={$mover->id}&user_id[]={$otherMover->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 2)
            ->where('logs.data.0.id', $matchingB->id)
            ->where('logs.data.1.id', $matchingA->id)
    );
});

test('the cell log can be filtered by status change, excluding entries for other actions', function () {
    actingAsAdmin();

    $matching = CellStatusLog::factory()->create(['action' => CellLogAction::Opened]);
    CellStatusLog::factory()->create(['action' => CellLogAction::Emptied]);

    $response = $this->get('/admin/cell-logs?action[]=opened');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
    );
});

test('the cell log can be filtered by multiple status changes at once, excluding entries for the remaining action', function () {
    actingAsAdmin();

    $opened = CellStatusLog::factory()->create(['action' => CellLogAction::Opened]);
    $emptied = CellStatusLog::factory()->create(['action' => CellLogAction::Emptied]);
    CellStatusLog::factory()->create(['action' => CellLogAction::Stored]);

    $response = $this->get('/admin/cell-logs?action[]=opened&action[]=emptied');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 2)
            ->where('logs.data.0.id', $emptied->id)
            ->where('logs.data.1.id', $opened->id)
    );
});

test('the cell log can be filtered by a date range, excluding entries outside it', function () {
    actingAsAdmin();

    $matching = CellStatusLog::factory()->create();
    $matching->forceFill(['created_at' => '2026-06-15'])->save();

    $outOfRange = CellStatusLog::factory()->create();
    $outOfRange->forceFill(['created_at' => '2026-01-01'])->save();

    $response = $this->get('/admin/cell-logs?date_from=2026-06-01&date_to=2026-06-30');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
    );
});

test('the cell log can be filtered by created_within_days, excluding entries older than that window', function () {
    Carbon::setTestNow('2026-08-15 12:00:00');
    actingAsAdmin();

    $withinWindow = backdate(CellStatusLog::factory()->create(), '2026-08-10 00:00:00');
    backdate(CellStatusLog::factory()->create(), '2026-08-01 00:00:00');

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

test('the cell log can be filtered by pallet expiration date range, excluding entries outside it', function () {
    actingAsAdmin();

    $matchingPallet = Pallet::factory()->create(['expiration_date' => '2026-06-15']);
    $outOfRangePallet = Pallet::factory()->create(['expiration_date' => '2026-01-01']);

    $matching = CellStatusLog::factory()->create(['pallet_id' => $matchingPallet->id]);
    CellStatusLog::factory()->create(['pallet_id' => $outOfRangePallet->id]);

    $response = $this->get('/admin/cell-logs?expiration_date_from=2026-06-01&expiration_date_to=2026-06-30');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
    );
});

test('the cell log can be filtered by expires_within_days, excluding pallets expiring after that window', function () {
    Carbon::setTestNow('2026-08-15 12:00:00');
    actingAsAdmin();

    $withinWindowPallet = Pallet::factory()->create(['expiration_date' => '2026-08-20']);
    $outOfRangePallet = Pallet::factory()->create(['expiration_date' => '2026-09-01']);

    $matching = CellStatusLog::factory()->create(['pallet_id' => $withinWindowPallet->id]);
    CellStatusLog::factory()->create(['pallet_id' => $outOfRangePallet->id]);

    $response = $this->get('/admin/cell-logs?expires_within_days=7');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
    );

    Carbon::setTestNow();
});

test('filtering the cell log by expires_within_days together with an expiration date range is rejected', function () {
    actingAsAdmin();

    $response = $this->get('/admin/cell-logs?expires_within_days=7&expiration_date_from=2026-06-01');

    $response->assertSessionHasErrors('expires_within_days');
});

test('the cell log defaults to newest-first when no sort is requested', function () {
    actingAsAdmin();

    $older = backdate(CellStatusLog::factory()->create(), '2026-08-01 10:00:00');
    $newer = backdate(CellStatusLog::factory()->create(), '2026-08-01 12:00:00');

    $response = $this->get('/admin/cell-logs');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 2)
            ->where('logs.data.0.id', $newer->id)
            ->where('logs.data.1.id', $older->id)
    );
});

test('the cell log can be sorted by log date ascending', function () {
    actingAsAdmin();

    $older = backdate(CellStatusLog::factory()->create(), '2026-08-01 10:00:00');
    $newer = backdate(CellStatusLog::factory()->create(), '2026-08-01 12:00:00');

    $response = $this->get('/admin/cell-logs?sort_by=created_at&sort_direction=asc');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 2)
            ->where('logs.data.0.id', $older->id)
            ->where('logs.data.1.id', $newer->id)
    );
});

test('the cell log can be sorted by pallet expiration date, with a direction, placing null expiration dates first ascending', function () {
    actingAsAdmin();

    $soonPallet = Pallet::factory()->create(['expiration_date' => '2026-06-01']);
    $latePallet = Pallet::factory()->create(['expiration_date' => '2026-12-01']);

    $noExpiration = CellStatusLog::factory()->create(['pallet_id' => null]);
    $soon = CellStatusLog::factory()->create(['pallet_id' => $soonPallet->id]);
    $late = CellStatusLog::factory()->create(['pallet_id' => $latePallet->id]);

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

    $flagged = CellStatusLog::factory()->create();
    CellStatusLogFlag::factory()->create(['cell_status_log_id' => $flagged->id]);

    CellStatusLog::factory()->create();

    $response = $this->get('/admin/cell-logs?flagged=true');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $flagged->id)
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

    $response->assertRedirect();
    $freshFlag = $flag->fresh();
    expect($freshFlag->acknowledged_at)->not->toBeNull();
    expect($freshFlag->acknowledged_by)->toBe($admin->id);

    // Already-acknowledged flags are left untouched, not re-stamped.
    expect($alreadyAcknowledged->fresh()->acknowledged_at->toDateTimeString())->toBe('2026-08-01 00:00:00');
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
