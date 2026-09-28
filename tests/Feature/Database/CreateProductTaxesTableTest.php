<?php

use Illuminate\Support\Facades\Schema;

test('the product_taxes table has the expected columns', function () {
    expect(Schema::hasTable('product_taxes'))->toBeTrue();
    expect(Schema::hasColumns('product_taxes', [
        'id', 'product_id', 'tax_id', 'tax', 'tax_type', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
