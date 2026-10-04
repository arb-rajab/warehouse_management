<?php

use App\Models\StoreUser;
use Illuminate\Support\Facades\Schema;

function verifiedCustomer(?string $lastOrderAt, ?string $verifiedAt, string $userType = 'customer', bool $verified = true): StoreUser
{
    $user = createStoreUser($userType);
    $user->forceFill([
        'admin_verified' => $verified,
        'last_order_at' => $lastOrderAt,
        'admin_verified_at' => $verifiedAt,
    ])->save();

    return $user;
}

test('verified customers who have not ordered for 30 days are unverified in one pass', function () {
    $lapsedBuyer = verifiedCustomer(now()->subDays(31)->toDateString(), now()->subDays(90)->toDateString());
    $lapsedNeverOrdered = verifiedCustomer(null, now()->subDays(40)->toDateString());

    $recentBuyer = verifiedCustomer(now()->subDays(10)->toDateString(), now()->subDays(90)->toDateString());
    $newlyVerified = verifiedCustomer(null, now()->subDays(5)->toDateString());
    $oldAdmin = verifiedCustomer(now()->subDays(60)->toDateString(), now()->subDays(90)->toDateString(), 'admin');
    $alreadyUnverified = verifiedCustomer(now()->subDays(60)->toDateString(), null, 'customer', false);

    $this->artisan('check:customers')
        ->expectsOutputToContain('unverified for not placing orders for the last 30 days: 2')
        ->assertSuccessful();

    foreach ([$lapsedBuyer, $lapsedNeverOrdered] as $user) {
        $fresh = $user->fresh();
        expect((bool) $fresh->admin_verified)->toBeFalse()
            ->and($fresh->last_order_at)->toBeNull()
            ->and($fresh->admin_verified_at)->toBeNull();
    }

    expect((bool) $recentBuyer->fresh()->admin_verified)->toBeTrue()
        ->and((bool) $newlyVerified->fresh()->admin_verified)->toBeTrue()
        ->and((bool) $oldAdmin->fresh()->admin_verified)->toBeTrue()
        ->and($alreadyUnverified->fresh()->last_order_at)->not->toBeNull();
});

test('it exits non-zero when it fails', function () {
    Schema::drop('users');

    $this->artisan('check:customers')->assertFailed();
});
