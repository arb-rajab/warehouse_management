<?php

use Illuminate\Support\Facades\Schema;

test('customer_package_payments table has the expected columns', function () {
    expect(Schema::hasTable('customer_package_payments'))->toBeTrue();
    expect(Schema::hasColumns('customer_package_payments', [
        'id', 'user_id', 'customer_package_id', 'payment_method', 'payment_details',
        'approval', 'offline_payment', 'reciept', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
