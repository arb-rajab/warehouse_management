<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('customer_package_payments has the expected columns', function () {
    expect(Schema::hasTable('customer_package_payments'))->toBeTrue();
    expect(Schema::hasColumns('customer_package_payments', [
        'id', 'user_id', 'customer_package_id', 'payment_method', 'payment_details',
        'approval', 'offline_payment', 'reciept', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('customer_package_payments')->insert([
        'user_id' => 1,
        'customer_package_id' => 1,
        'payment_method' => 'bank_transfer',
        'payment_details' => 'account 12345',
        'approval' => 0,
        'offline_payment' => 1,
        'reciept' => 'receipts/1.png',
    ]);

    $this->assertDatabaseHas('customer_package_payments', [
        'user_id' => 1,
        'payment_method' => 'bank_transfer',
        'reciept' => 'receipts/1.png',
    ]);
});
