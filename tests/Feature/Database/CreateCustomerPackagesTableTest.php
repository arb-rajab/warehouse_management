<?php

use Illuminate\Support\Facades\Schema;

test('customer_packages table has the expected columns', function () {
    expect(Schema::hasTable('customer_packages'))->toBeTrue();
    expect(Schema::hasColumns('customer_packages', [
        'id', 'name', 'amount', 'product_upload', 'logo', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
