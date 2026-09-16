<?php

use App\Models\Pallet;
use App\Models\Product;
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

    // Outside the 7-day window (until 2026-08-20), so it doesn't count yet.
    $pallet = Pallet::factory()->create(['expiration_date' => '2026-09-12']);

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
    // Outside the 7-day window (until 2026-08-20), so it doesn't count yet.
    $pallet = Pallet::factory()->create(['product_id' => $product->id, 'expiration_date' => '2026-09-12', 'remaining_boxes' => 5]);

    $before = $this->get('/admin');
    $before->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('stats.expiring.windows.0.count', 0)
    );

    $this->put("/admin/pallets/{$pallet->id}/update", [
        'product_id' => $product->id,
        'expiration_date' => '2026-08-20',
        'remaining_boxes' => 5,
    ]);

    $after = $this->get('/admin');
    $after->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('stats.expiring.windows.0.count', 1)
    );
});
