<?php

use Illuminate\Support\Facades\DB;

/**
 * The seller area failed on every page that reads the seller's own shop:
 * the User -> StoreUser rename had turned StoreUser::shop()'s inferred
 * foreign key into `shops.store_user_id` (see StoreUserRelationsTest). This
 * page also renders the seller layout, which read `Language::first()->rtl`
 * with no language row and flash messages as a Collection.
 */
test("the seller's product list renders with the seller's own shop", function () {
    $seller = createStoreUser('seller');
    $otherSeller = createStoreUser('seller');
    DB::table('shops')->insert(['user_id' => $seller->id, 'name' => 'Mine Shop', 'slug' => 'mine-shop']);
    DB::table('shops')->insert(['user_id' => $otherSeller->id, 'name' => 'Theirs Shop', 'slug' => 'theirs-shop']);
    actingAsStoreUser($seller);

    $this->get('/temp/seller/products')
        ->assertOk()
        ->assertSee('Mine Shop')
        ->assertDontSee('Theirs Shop');
});
