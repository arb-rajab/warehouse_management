<?php

use Illuminate\Support\Facades\Schema;

test('customer_product_translations table has the expected columns', function () {
    expect(Schema::hasTable('customer_product_translations'))->toBeTrue();
    expect(Schema::hasColumns('customer_product_translations', [
        'id', 'customer_product_id', 'name', 'unit', 'description', 'lang', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
