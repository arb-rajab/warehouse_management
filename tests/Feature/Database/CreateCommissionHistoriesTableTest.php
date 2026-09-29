<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('commission_histories has the expected columns', function () {
    expect(Schema::hasTable('commission_histories'))->toBeTrue();
    expect(Schema::hasColumns('commission_histories', [
        'id', 'order_id', 'order_detail_id', 'seller_id', 'admin_commission', 'seller_earning', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('commission_histories')->insert([
        'order_id' => 1,
        'order_detail_id' => 1,
        'seller_id' => 1,
        'admin_commission' => 5.00,
        'seller_earning' => 45.00,
    ]);

    $this->assertDatabaseHas('commission_histories', [
        'order_id' => 1,
        'admin_commission' => 5.00,
        'seller_earning' => 45.00,
    ]);
});
