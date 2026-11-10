<?php

use App\Models\Pallet;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptFile;
use App\Models\ReceiptPallet;
use App\Models\Row;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

test('creating a pallet directly (bypassing PalletActionService) still invalidates the dashboard stats cache', function () {
    actingAsMobileUser();

    // Prime the cache with no pallets yet.
    $before = $this->getJson('/api/v1/dashboard');
    $before->assertOk();
    expect($before->json('stats.expiring.windows.0.count'))->toBe(0);

    // A write that never goes through PalletActionService (e.g. a seeder/import),
    // so CellStatusLogObserver's flush() never fires for it.
    Pallet::factory()->create(['expiration_date' => '2026-08-20']);

    $after = $this->getJson('/api/v1/dashboard');
    $after->assertOk();
    expect($after->json('stats.expiring.windows.0.count'))->toBe(1);
});

test('deleting a pallet directly invalidates the dashboard stats cache', function () {
    actingAsMobileUser();

    $pallet = Pallet::factory()->create(['expiration_date' => '2026-08-20']);

    $before = $this->getJson('/api/v1/dashboard');
    $before->assertOk();
    expect($before->json('stats.expiring.windows.0.count'))->toBe(1);

    $pallet->delete();

    $after = $this->getJson('/api/v1/dashboard');
    $after->assertOk();
    expect($after->json('stats.expiring.windows.0.count'))->toBe(0);
});

test('updating a pallet\'s expiration date directly (bypassing PalletActionService, e.g. the admin edit action) invalidates the dashboard stats cache', function () {
    actingAsMobileUser();

    // Outside the 1-month window (until 2026-09-13), so it doesn't count yet.
    $pallet = Pallet::factory()->create(['expiration_date' => '2026-10-01']);

    $before = $this->getJson('/api/v1/dashboard');
    $before->assertOk();
    expect($before->json('stats.expiring.windows.0.count'))->toBe(0);

    $pallet->update(['expiration_date' => '2026-08-20']);

    $after = $this->getJson('/api/v1/dashboard');
    $after->assertOk();
    expect($after->json('stats.expiring.windows.0.count'))->toBe(1);
});

test('updating only a pallet\'s remaining_boxes directly invalidates the dashboard stats cache', function () {
    actingAsMobileUser();

    $pallet = Pallet::factory()->create(['expiration_date' => '2026-08-20', 'remaining_boxes' => 5]);

    $before = $this->getJson('/api/v1/dashboard');
    $before->assertOk();
    expect($before->json('stats.expiring.windows.0.count'))->toBe(1);

    // Bypasses PalletObserver::created() entirely, so this second expiring
    // pallet exists in the database but the cache doesn't know about it yet
    // — only a real flush would surface it below.
    Pallet::withoutEvents(fn () => Pallet::factory()->create(['expiration_date' => '2026-08-20']));

    $pallet->update(['remaining_boxes' => 2]);

    // The remaining_boxes-only update above must have flushed the cache on
    // its own for the dashboard to already reflect the second pallet here —
    // proving it isn't just picking it up from an unrelated flush elsewhere.
    $after = $this->getJson('/api/v1/dashboard');
    $after->assertOk();
    expect($after->json('stats.expiring.windows.0.count'))->toBe(2);
});

test('updating a pallet via the real admin edit endpoint invalidates the dashboard stats cache', function () {
    actingAsAdmin();

    $product = Product::factory()->create();
    // Outside the 1-month window (until 2026-09-13), so it doesn't count yet.
    $pallet = Pallet::factory()->create(['product_id' => $product->id, 'expiration_date' => '2026-10-01', 'remaining_boxes' => 5]);

    $before = $this->get('/admin');
    $before->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('stats.expiring.windows.0.count', 0)
    );

    $this->put("/admin/pallets/{$pallet->id}", [
        'product_id' => $product->id,
        'expiration_date' => '2026-08-20',
        'remaining_boxes' => 5,
    ]);

    $after = $this->get('/admin');
    $after->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('stats.expiring.windows.0.count', 1)
    );
});

test('finalizing a receipt creates unplaced pallets, which flush the cache so the low-stock card sees the new stock', function () {
    actingAsAdminMobileUser();
    $product = Product::factory()->minimumPallets(1)->create();

    $before = $this->getJson('/api/v1/dashboard');
    expect($before->json('stats.low_stock.count'))->toBe(1);

    $receipt = Receipt::factory()->create();
    ReceiptFile::factory()->for($receipt)->create();
    ReceiptPallet::factory()->for($receipt)->create(['product_id' => $product->id]);
    $this->postJson("/api/v1/receipts/{$receipt->id}/finalize")->assertOk();

    expect($this->getJson('/api/v1/dashboard')->json('stats.low_stock.count'))->toBe(0);
});

test('placing an unplaced pallet flushes the cache: the expiry card already counts it, the occupancy card sees the cell taken', function () {
    actingAsMobileUser();
    $pallet = unplacedPalletIn(Warehouse::firstOrCreateDefault(), ['expiration_date' => '2026-08-20']);
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);

    // Primed while unplaced: the expiry card already counts it, no cell is full.
    $before = $this->getJson('/api/v1/dashboard');
    expect($before->json('stats.expiring.windows.0.count'))->toBe(1)
        ->and($before->json('stats.occupancy.empty'))->toBe(1)
        ->and($before->json('stats.unplaced_pallets.count'))->toBe(1);

    $this->postJson("/api/v1/pallets/{$pallet->id}/place", ['row_letter' => $row->letter, 'cell_number' => 1, 'flat_number' => 1])->assertOk();

    $after = $this->getJson('/api/v1/dashboard');
    expect($after->json('stats.expiring.windows.0.count'))->toBe(1)
        ->and($after->json('stats.occupancy.empty'))->toBe(0)
        ->and($after->json('stats.unplaced_pallets.count'))->toBe(0);
});
