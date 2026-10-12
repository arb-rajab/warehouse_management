<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('payments has the expected columns', function () {
    expect(Schema::hasTable('payments'))->toBeTrue();
    expect(Schema::hasColumns('payments', [
        'id', 'seller_id', 'amount', 'payment_details', 'payment_method', 'txn_code', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('payments')->insert([
        'seller_id' => 1,
    ]);

    $this->assertDatabaseHas('payments', [
        'seller_id' => 1,
        'amount' => 0.00,
    ]);
});
