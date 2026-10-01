<?php

use App\Models\StoreUser;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent derives a model's default foreign key from its class name, so the
 * legacy User -> StoreUser rename quietly changed it from `user_id` to
 * `store_user_id`, a column no table has. Every relation on the store user
 * that names no key broke with it: the whole seller area failed on
 * `shops.store_user_id`, the storefront on `wishlists.store_user_id`.
 * StoreUser::getForeignKey() restores the old name.
 */
test('the store user keeps the user_id foreign key its old class name produced', function () {
    expect((new StoreUser)->getForeignKey())->toBe('user_id');
});

test("a seller's shop resolves by user_id", function () {
    $seller = createStoreUser('seller');
    $otherSeller = createStoreUser('seller');
    $shopId = DB::table('shops')->insertGetId(['user_id' => $seller->id, 'name' => 'Mine']);
    DB::table('shops')->insert(['user_id' => $otherSeller->id, 'name' => 'Theirs']);

    expect($seller->shop->id)->toBe($shopId);
});

test("a customer's addresses, carts, orders, wishlist and uploads resolve by user_id, and no one else's", function () {
    $customer = createStoreUser('customer');
    $otherCustomer = createStoreUser('customer');

    foreach ([$customer, $otherCustomer] as $owner) {
        DB::table('addresses')->insert(['user_id' => $owner->id, 'address' => 'Street 1']);
        DB::table('carts')->insert(['user_id' => $owner->id, 'product_id' => 1, 'quantity' => 1]);
        DB::table('orders')->insert(['user_id' => $owner->id, 'shipping_type' => 'home_delivery', 'date' => 0]);
        DB::table('wishlists')->insert(['user_id' => $owner->id, 'product_id' => 1]);
        DB::table('uploads')->insert(['user_id' => $owner->id, 'file_name' => 'uploads/all/'.$owner->id.'.png']);
    }

    expect($customer->addresses->pluck('user_id')->all())->toBe([$customer->id])
        ->and($customer->carts->pluck('user_id')->all())->toBe([$customer->id])
        ->and($customer->orders->pluck('user_id')->all())->toBe([$customer->id])
        ->and($customer->wishlists->pluck('user_id')->all())->toBe([$customer->id])
        ->and($customer->uploads->pluck('file_name')->all())->toBe(['uploads/all/'.$customer->id.'.png']);
});
