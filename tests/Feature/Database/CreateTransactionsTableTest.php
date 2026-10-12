<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the transactions table has the expected columns', function () {
    expect(Schema::hasTable('transactions'))->toBeTrue();
    expect(Schema::hasColumns('transactions', [
        'id', 'user_id', 'gateway', 'payment_type', 'additional_content',
        'mpesa_request', 'mpesa_receipt', 'status', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('transactions')->insert([
        'user_id' => 1,
        'gateway' => 'wallet',
    ]);

    $this->assertDatabaseHas('transactions', [
        'user_id' => 1,
        'gateway' => 'wallet',
        'status' => 0,
    ]);
});
