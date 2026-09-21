<?php

use App\Enums\CellLogAction;
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

test('an authenticated worker can list cell logs with every property the app reads', function () {
    Carbon::setTestNow('2026-08-01 10:00:00');
    actingAsMobileUser();
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

    $response = $this->getJson('/api/v1/cell-logs');

    $response->assertOk();
    expect($response->json('data.0'))->toEqual([
        'id' => $log->id,
        'action' => 'transferred_out',
        'from_state' => 'full',
        'to_state' => 'empty',
        'note' => 'Consolidating stock.',
        'boxes_count' => 7,
        'cell' => [
            'row_letter' => 'A',
            'cell_number' => 1,
            'flat_number' => 2,
        ],
        'related_cell' => [
            'row_letter' => 'B',
            'cell_number' => 2,
            'flat_number' => 1,
        ],
        'product' => [
            'id' => $product->id,
            'name' => 'Widgets',
            'ar_name' => 'ودجات',
            'image_url' => 'https://cdn.example.com/widgets.png',
            'boxes_count' => 10,
            'active' => true,
        ],
        'pallet' => [
            'id' => $pallet->id,
            'expiration_date' => '2026-09-01',
        ],
        'user' => [
            'id' => $mover->id,
            'name' => 'Bob Mover',
        ],
        'created_at' => $log->created_at->toIso8601String(),
        'next_log_at' => null,
        'duration_seconds' => 0,
    ]);

    Carbon::setTestNow();
});

test('the cell log listing withholds flag data from a worker, even for a flagged entry', function () {
    actingAsMobileUser();
    $log = CellStatusLog::factory()->create();
    CellStatusLogFlag::factory()->create(['cell_status_log_id' => $log->id]);

    $response = $this->getJson('/api/v1/cell-logs');

    $response->assertOk();
    expect($response->json('data.0'))->not->toHaveKey('flagged');
    expect($response->json('data.0'))->not->toHaveKey('flags');
});

test('the cell log listing includes the flagged boolean for an admin caller, but never the underlying flags array', function () {
    // forListing() deliberately never eager-loads WITH_FLAG_DETAILS on this
    // endpoint (only the admin panel's own listing does), so `flags` stays
    // absent for every viewer via whenLoaded() — only `flagged` itself
    // (computed off the withCount('flags') alias added for an admin caller)
    // actually varies by viewer here.
    actingAsAdminMobileUser();
    $log = CellStatusLog::factory()->create();
    CellStatusLogFlag::factory()->create(['cell_status_log_id' => $log->id]);

    $response = $this->getJson('/api/v1/cell-logs');

    $response->assertOk();
    expect($response->json('data.0.flagged'))->toBeTrue();
    expect($response->json('data.0'))->not->toHaveKey('flags');
});

test('the cell log listing computes flagged for an admin caller without an exists query per row', function () {
    actingAsAdminMobileUser();
    CellStatusLog::factory()->count(10)->create()->each(
        fn (CellStatusLog $log) => CellStatusLogFlag::factory()->create(['cell_status_log_id' => $log->id])
    );

    DB::enableQueryLog();
    $response = $this->getJson('/api/v1/cell-logs');
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('flagged')->unique()->all())->toBe([true]);
    // The eager-loaded WITH_DETAILS relations plus withCount('flags') and
    // pagination put this in the low teens regardless of row count; a
    // per-row exists() fallback would add one query per row on top of that
    // (21+ for these 10 rows), so 15 comfortably separates the two.
    expect($queryCount)->toBeLessThan(15);
});

test('the flagged filter is ignored for a worker, so it cannot be used to reveal which entries are flagged', function () {
    actingAsMobileUser();
    $flagged = CellStatusLog::factory()->create();
    CellStatusLogFlag::factory()->create(['cell_status_log_id' => $flagged->id]);
    $unflagged = CellStatusLog::factory()->create();

    $response = $this->getJson('/api/v1/cell-logs?flagged=true');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id')->all())
        ->toEqualCanonicalizing([$flagged->id, $unflagged->id]);
});

test('the cell log listing includes the next same-pallet log timestamp as next_log_at and the seconds between them', function () {
    actingAsMobileUser();
    $pallet = Pallet::factory()->create();

    $stored = backdate(CellStatusLog::factory()->create(['pallet_id' => $pallet->id, 'action' => CellLogAction::Stored]), '2026-08-01 10:00:00');
    $opened = backdate(CellStatusLog::factory()->create(['pallet_id' => $pallet->id, 'action' => CellLogAction::Opened]), '2026-08-01 12:00:00');

    $response = $this->getJson('/api/v1/cell-logs');

    $response->assertOk();
    $storedEntry = collect($response->json('data'))->firstWhere('id', $stored->id);

    expect($storedEntry['next_log_at'])->toBe($opened->created_at->toIso8601String());
    expect($storedEntry['duration_seconds'])->toBe(7200);
});

test('the cell log listing skips the paired transferred-in log when computing next_log_at and duration_seconds for a transferred-out entry', function () {
    actingAsMobileUser();
    $pallet = Pallet::factory()->create();
    $sourceCell = Cell::factory()->create();
    $destinationCell = Cell::factory()->create();

    [$transferredOut] = createTransferPair($pallet, $sourceCell, $destinationCell, '2026-08-01 10:00:00', '2026-08-01 10:00:01');

    $emptied = backdate(CellStatusLog::factory()->create([
        'pallet_id' => $pallet->id,
        'cell_id' => $destinationCell->id,
        'action' => CellLogAction::Emptied,
    ]), '2026-08-01 14:00:01');

    $response = $this->getJson('/api/v1/cell-logs');

    $response->assertOk();
    $transferredOutEntry = collect($response->json('data'))->firstWhere('id', $transferredOut->id);

    expect($transferredOutEntry['next_log_at'])->toBe($emptied->created_at->toIso8601String());
    expect($transferredOutEntry['duration_seconds'])->toBe(14401);
});

test('the cell log listing shows a pallet id with a null expiration date once the pallet has been emptied and deleted', function () {
    actingAsMobileUser();
    $pallet = Pallet::factory()->create();
    $palletId = $pallet->id;

    CellStatusLog::factory()->create([
        'action' => CellLogAction::Emptied,
        'pallet_id' => $palletId,
    ]);

    $pallet->delete();

    $response = $this->getJson('/api/v1/cell-logs');

    $response->assertOk();
    expect($response->json('data.0.pallet'))->toEqual([
        'id' => $palletId,
        'expiration_date' => null,
    ]);
});

test('the cell log listing paginates instead of returning everything at once', function () {
    actingAsMobileUser();
    $this->seedPaginationOverflowFixture(perPage: 20);

    $response = $this->getJson('/api/v1/cell-logs');

    assertJsonListingPaginates($response, total: 25);
});

test('next_log_at is found even when the next log for the same pallet falls on a different page', function () {
    actingAsMobileUser();
    $pallet = Pallet::factory()->create();

    $older = backdate(CellStatusLog::factory()->create(['pallet_id' => $pallet->id]), '2026-08-01 08:00:00');
    $newer = backdate(CellStatusLog::factory()->create(['pallet_id' => $pallet->id]), '2026-08-01 09:00:00');

    // 19 unrelated logs, all newer than $newer, so the listing (ordered by
    // latest()) puts $newer plus these 19 on page 1 (20 rows) and pushes
    // $older, the single oldest row, onto page 2 by itself.
    collect(range(1, 19))->each(function (int $i) {
        backdate(CellStatusLog::factory()->create(), sprintf('2026-08-01 10:%02d:00', $i));
    });

    $response = $this->getJson('/api/v1/cell-logs?page=2');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($older->id);
    expect($response->json('data.0.next_log_at'))->toBe($newer->created_at->toIso8601String());
    expect($response->json('data.0.duration_seconds'))->toBe(3600);
});

test('next_log_at is found even when the next log for the same pallet is excluded by the current filters', function () {
    actingAsMobileUser();
    $pallet = Pallet::factory()->create();

    $stored = backdate(CellStatusLog::factory()->create(['pallet_id' => $pallet->id, 'action' => CellLogAction::Stored]), '2026-08-01 10:00:00');
    $opened = backdate(CellStatusLog::factory()->create(['pallet_id' => $pallet->id, 'action' => CellLogAction::Opened]), '2026-08-01 12:00:00');

    $response = $this->getJson('/api/v1/cell-logs?action[]=stored');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($stored->id);
    expect($response->json('data.0.next_log_at'))->toBe($opened->created_at->toIso8601String());
    expect($response->json('data.0.duration_seconds'))->toBe(7200);
});

test('an unauthenticated caller cannot list cell logs', function () {
    $response = $this->getJson('/api/v1/cell-logs');

    $response->assertUnauthorized();
});

test('the cell log listing can be filtered by product, excluding entries for other products', function () {
    actingAsMobileUser();
    ['product' => $product, 'matching' => $matching] = $this->seedProductFilterFixture();

    $response = $this->getJson("/api/v1/cell-logs?product_id[]={$product->id}");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
});

test('the cell log listing can be filtered by multiple products at once, excluding entries for the remaining product', function () {
    actingAsMobileUser();
    [
        'product' => $product,
        'otherProduct' => $otherProduct,
        'matchingA' => $matchingA,
        'matchingB' => $matchingB,
    ] = $this->seedMultipleProductFilterFixture();

    $response = $this->getJson("/api/v1/cell-logs?product_id[]={$product->id}&product_id[]={$otherProduct->id}");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
    expect(collect($response->json('data'))->pluck('id')->all())->toEqual([$matchingB->id, $matchingA->id]);
});

test('the cell log listing can be filtered by product_status=active, excluding entries for inactive products', function () {
    actingAsMobileUser();
    $activeProduct = Product::factory()->create();
    $inactiveProduct = Product::factory()->inactive()->create();

    $matching = CellStatusLog::factory()->create(['product_id' => $activeProduct->id]);
    CellStatusLog::factory()->create(['product_id' => $inactiveProduct->id]);

    $response = $this->getJson('/api/v1/cell-logs?product_status=active');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
});

test('the cell log listing can be filtered by product_status=inactive, excluding entries for active products', function () {
    actingAsMobileUser();
    $activeProduct = Product::factory()->create();
    $inactiveProduct = Product::factory()->inactive()->create();

    CellStatusLog::factory()->create(['product_id' => $activeProduct->id]);
    $matching = CellStatusLog::factory()->create(['product_id' => $inactiveProduct->id]);

    $response = $this->getJson('/api/v1/cell-logs?product_status=inactive');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
});

test('a product_status with zero matching products returns nothing rather than falling back to unfiltered cell logs', function () {
    actingAsMobileUser();
    $product = Product::factory()->create();
    CellStatusLog::factory()->create(['product_id' => $product->id]);

    $response = $this->getJson('/api/v1/cell-logs?product_status=inactive');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(0);
});

test('sending both product_status and product_id together is rejected on the mobile cell log listing', function () {
    actingAsMobileUser();
    $product = Product::factory()->create();

    $response = $this->getJson("/api/v1/cell-logs?product_status=active&product_id[]={$product->id}");

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['product_status']);
});

test('an invalid product_status is rejected on the mobile cell log listing', function () {
    actingAsMobileUser();

    $response = $this->getJson('/api/v1/cell-logs?product_status=bogus');

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['product_status']);
});

test('product_status is silently ignored on the admin cell log listing', function () {
    actingAsAdmin();
    $activeProduct = Product::factory()->create();
    $inactiveProduct = Product::factory()->inactive()->create();

    CellStatusLog::factory()->create(['product_id' => $activeProduct->id]);
    CellStatusLog::factory()->create(['product_id' => $inactiveProduct->id]);

    $response = $this->get('/admin/cell-logs?product_status=active');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 2)
    );
});

test('the cell log listing can be filtered by pallet, excluding entries for other pallets, even after the pallet is deleted', function () {
    actingAsMobileUser();
    ['palletId' => $palletId, 'matching' => $matching] = $this->seedPalletFilterFixture();

    $response = $this->getJson("/api/v1/cell-logs?pallet_id={$palletId}");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
});

test('the cell log listing can be filtered by row, excluding entries for other rows', function () {
    actingAsMobileUser();
    ['row' => $row, 'matching' => $matching] = $this->seedRowFilterFixture();

    $response = $this->getJson("/api/v1/cell-logs?row_id={$row->id}");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
});

test('the cell log listing can be filtered by column number, excluding entries for other columns', function () {
    actingAsMobileUser();
    ['matching' => $matching] = $this->seedColumnFilterFixture();

    $response = $this->getJson('/api/v1/cell-logs?column_number=1');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
});

test('the cell log listing can be filtered by who did it, excluding entries by other users', function () {
    actingAsMobileUser();
    ['mover' => $mover, 'matching' => $matching] = $this->seedUserFilterFixture();

    $response = $this->getJson("/api/v1/cell-logs?user_id[]={$mover->id}");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
});

test('the cell log listing can be filtered by multiple users at once, excluding entries by the remaining user', function () {
    actingAsMobileUser();
    [
        'mover' => $mover,
        'otherMover' => $otherMover,
        'matchingA' => $matchingA,
        'matchingB' => $matchingB,
    ] = $this->seedMultipleUserFilterFixture();

    $response = $this->getJson("/api/v1/cell-logs?user_id[]={$mover->id}&user_id[]={$otherMover->id}");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
    expect(collect($response->json('data'))->pluck('id')->all())->toEqual([$matchingB->id, $matchingA->id]);
});

test('the cell log listing can be filtered by status change, excluding entries for other actions', function () {
    actingAsMobileUser();
    ['matching' => $matching] = $this->seedActionFilterFixture();

    $response = $this->getJson('/api/v1/cell-logs?action[]=opened');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
});

test('the cell log listing can be filtered by multiple status changes at once, excluding entries for the remaining action', function () {
    actingAsMobileUser();
    ['opened' => $opened, 'emptied' => $emptied] = $this->seedMultipleActionFilterFixture();

    $response = $this->getJson('/api/v1/cell-logs?action[]=opened&action[]=emptied');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
    expect(collect($response->json('data'))->pluck('id')->all())->toEqual([$emptied->id, $opened->id]);
});

test('the cell log listing can be filtered by a date range, excluding entries outside it', function () {
    actingAsMobileUser();
    ['matching' => $matching] = $this->seedDateRangeFilterFixture();

    $response = $this->getJson('/api/v1/cell-logs?date_from=2026-06-01&date_to=2026-06-30');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
});

test('filtering cell logs with an invalid date range is rejected', function () {
    actingAsMobileUser();

    $response = $this->getJson('/api/v1/cell-logs?date_from=2026-06-30&date_to=2026-06-01');

    $response->assertUnprocessable();
});

test('the cell log listing can be filtered by created_within_days, excluding entries older than that window', function () {
    Carbon::setTestNow('2026-08-15 12:00:00');
    actingAsMobileUser();
    ['withinWindow' => $withinWindow] = $this->seedCreatedWithinDaysFixture();

    $response = $this->getJson('/api/v1/cell-logs?created_within_days=7');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($withinWindow->id);

    Carbon::setTestNow();
});

test('filtering cell logs by created_within_days together with a date range is rejected', function () {
    actingAsMobileUser();

    $response = $this->getJson('/api/v1/cell-logs?created_within_days=7&date_from=2026-06-01');

    $response->assertUnprocessable();
});

test('the cell log listing can be filtered by pallet expiration date range, excluding entries outside it', function () {
    actingAsMobileUser();
    ['matching' => $matching] = $this->seedPalletExpirationRangeFixture();

    $response = $this->getJson('/api/v1/cell-logs?expiration_date_from=2026-06-01&expiration_date_to=2026-06-30');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
});

test('filtering cell logs with an invalid expiration date range is rejected', function () {
    actingAsMobileUser();

    $response = $this->getJson('/api/v1/cell-logs?expiration_date_from=2026-06-30&expiration_date_to=2026-06-01');

    $response->assertUnprocessable();
});

test('the cell log listing can be filtered by expires_within_days, excluding pallets expiring after that window', function () {
    Carbon::setTestNow('2026-08-15 12:00:00');
    actingAsMobileUser();
    ['matching' => $matching] = $this->seedExpiresWithinDaysFixture();

    $response = $this->getJson('/api/v1/cell-logs?expires_within_days=7');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);

    Carbon::setTestNow();
});

test('filtering cell logs by expires_within_days together with an expiration date range is rejected', function () {
    actingAsMobileUser();

    $response = $this->getJson('/api/v1/cell-logs?expires_within_days=7&expiration_date_from=2026-06-01');

    $response->assertUnprocessable();
});

test('the cell log listing can be sorted by log date ascending', function () {
    actingAsMobileUser();
    ['older' => $older, 'newer' => $newer] = $this->seedCreatedAtOrderFixture();

    $response = $this->getJson('/api/v1/cell-logs?sort_by=created_at&sort_direction=asc');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id')->all())->toEqual([$older->id, $newer->id]);
});

test('the cell log listing can be sorted by pallet expiration date', function () {
    actingAsMobileUser();
    ['soon' => $soon, 'late' => $late] = $this->seedExpirationDateOrderFixture();

    $response = $this->getJson('/api/v1/cell-logs?sort_by=expiration_date&sort_direction=asc');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id')->all())->toEqual([$soon->id, $late->id]);
});
