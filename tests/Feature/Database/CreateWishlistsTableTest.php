<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the wishlists table has the expected columns', function () {
    expect(Schema::hasTable('wishlists'))->toBeTrue();
    expect(Schema::hasColumns('wishlists', [
        'id', 'user_id', 'product_id', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('wishlists')->insert([
        'user_id' => 1,
        'product_id' => 1,
    ]);

    $this->assertDatabaseHas('wishlists', [
        'user_id' => 1,
        'product_id' => 1,
    ]);
});
