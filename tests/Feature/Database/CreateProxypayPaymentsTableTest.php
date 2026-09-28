<?php

use Illuminate\Support\Facades\Schema;

test('the proxypay_payments table has the expected columns', function () {
    expect(Schema::hasTable('proxypay_payments'))->toBeTrue();
    expect(Schema::hasColumns('proxypay_payments', [
        'id', 'payment_type', 'reference_id', 'order_id', 'package_id', 'user_id', 'amount', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
