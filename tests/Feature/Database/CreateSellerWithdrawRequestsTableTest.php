<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('seller_withdraw_requests has the expected columns', function () {
    expect(Schema::hasTable('seller_withdraw_requests'))->toBeTrue();
    expect(Schema::hasColumns('seller_withdraw_requests', [
        'id', 'user_id', 'amount', 'message', 'status', 'viewed', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('seller_withdraw_requests')->insert([
        'user_id' => 1,
        'amount' => 100.00,
    ]);

    $this->assertDatabaseHas('seller_withdraw_requests', [
        'user_id' => 1,
        'amount' => 100.00,
    ]);
});
