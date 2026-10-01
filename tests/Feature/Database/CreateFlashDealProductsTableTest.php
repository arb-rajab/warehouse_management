<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('flash_deal_products has the expected columns', function () {
    expect(Schema::hasTable('flash_deal_products'))->toBeTrue();
    expect(Schema::hasColumns('flash_deal_products', [
        'id', 'flash_deal_id', 'product_id', 'discount', 'discount_type', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('flash_deal_products')->insert([
        'flash_deal_id' => 1,
        'product_id' => 1,
    ]);

    $this->assertDatabaseHas('flash_deal_products', [
        'flash_deal_id' => 1,
        'product_id' => 1,
        'discount' => 0.00,
    ]);
});
