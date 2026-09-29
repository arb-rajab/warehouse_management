<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('coupons table has the expected columns', function () {
    expect(Schema::hasTable('coupons'))->toBeTrue();
    expect(Schema::hasColumns('coupons', [
        'id', 'user_id', 'type', 'code', 'details', 'discount', 'discount_type',
        'start_date', 'end_date', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('coupons')->insert([
        'user_id' => 1,
        'type' => 'percent',
        'code' => 'SAVE10',
        'details' => 'Save 10%',
        'discount' => 10.00,
        'discount_type' => 'percent',
        'start_date' => now()->timestamp,
        'end_date' => now()->addWeek()->timestamp,
    ]);

    $this->assertDatabaseHas('coupons', [
        'code' => 'SAVE10',
        'discount' => 10.00,
    ]);
});
