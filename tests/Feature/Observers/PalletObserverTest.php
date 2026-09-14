<?php

use App\Models\Pallet;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

test('creating a pallet directly (bypassing PalletActionService) still invalidates the dashboard stats cache', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
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
    Carbon::setTestNow('2026-08-13 10:00:00');
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
