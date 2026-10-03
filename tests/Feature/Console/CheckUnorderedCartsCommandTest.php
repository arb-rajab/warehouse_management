<?php

use App\Models\Cart;
use App\Models\StoreUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function staleCartFor(StoreUser $user, int $daysOld = 6): Cart
{
    $cart = Cart::create(['user_id' => $user->id, 'product_id' => 1, 'quantity' => 1]);
    DB::table('carts')->where('id', $cart->id)->update(['updated_at' => now()->subDays($daysOld)->toDateTimeString()]);

    return $cart;
}

test('a customer with stale carts is reported once to every admin, however many carts they hold', function () {
    $admin = createStoreUser('admin');
    $otherAdmin = createStoreUser('admin');
    $customer = createStoreUser('customer');
    staleCartFor($customer);
    staleCartFor($customer);

    $this->artisan('check:unordered-carts')
        ->expectsOutputToContain('1 notifications have sent successfully')
        ->assertSuccessful();

    expect($admin->notifications)->toHaveCount(1)
        ->and($otherAdmin->notifications)->toHaveCount(1)
        ->and($admin->notifications->first()->data['user_id'])->toBe($customer->id)
        ->and($admin->notifications->first()->data['user_name'])->toBe($customer->name)
        ->and(Cart::where('user_id', $customer->id)->where('is_admin_notified', true)->count())->toBe(2);

    // Every stale cart is flagged, so a second run has nothing left to report.
    $this->artisan('check:unordered-carts')->assertSuccessful();

    expect($admin->fresh()->notifications)->toHaveCount(1);
});

test('fresh carts, representatives and already notified carts are left out', function () {
    $admin = createStoreUser('admin');
    $freshCustomer = createStoreUser('customer');
    $rep = createStoreUser('customer');
    $rep->forceFill(['is_rep' => true])->save();
    $notifiedCustomer = createStoreUser('customer');

    $freshCart = staleCartFor($freshCustomer, 2);
    $repCart = staleCartFor($rep);
    $notifiedCart = staleCartFor($notifiedCustomer);
    $notifiedCart->forceFill(['is_admin_notified' => true])->save();
    DB::table('carts')->where('id', $notifiedCart->id)->update(['updated_at' => now()->subDays(6)->toDateTimeString()]);

    $this->artisan('check:unordered-carts')->assertSuccessful();

    expect($admin->notifications)->toHaveCount(0)
        ->and($freshCart->fresh()->is_admin_notified)->toBeFalsy()
        ->and($repCart->fresh()->is_admin_notified)->toBeFalsy();
});

test('it exits non-zero when it fails', function () {
    Schema::drop('carts');

    $this->artisan('check:unordered-carts')->assertFailed();
});
