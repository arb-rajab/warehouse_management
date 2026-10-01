<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('proxypay_payments has the expected columns', function () {
    expect(Schema::hasTable('proxypay_payments'))->toBeTrue();
    expect(Schema::hasColumns('proxypay_payments', [
        'id', 'payment_type', 'reference_id', 'order_id', 'package_id', 'user_id', 'amount', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('proxypay_payments')->insert([
        'payment_type' => 'order',
        'reference_id' => 'REF123',
        'user_id' => 1,
    ]);

    $this->assertDatabaseHas('proxypay_payments', [
        'reference_id' => 'REF123',
        'user_id' => 1,
        'amount' => 0.00,
    ]);
});
