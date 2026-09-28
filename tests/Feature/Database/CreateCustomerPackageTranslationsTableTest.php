<?php

use Illuminate\Support\Facades\Schema;

test('customer_package_translations table has the expected columns', function () {
    expect(Schema::hasTable('customer_package_translations'))->toBeTrue();
    expect(Schema::hasColumns('customer_package_translations', [
        'id', 'customer_package_id', 'name', 'lang', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
