<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('coupon_usages table has the expected columns', function () {
    expect(Schema::hasTable('coupon_usages'))->toBeTrue();
    expect(Schema::hasColumns('coupon_usages', [
        'id', 'user_id', 'coupon_id', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('coupon_usages')->insert([
        'user_id' => 1,
        'coupon_id' => 1,
    ]);

    $this->assertDatabaseHas('coupon_usages', [
        'user_id' => 1,
        'coupon_id' => 1,
    ]);
});
