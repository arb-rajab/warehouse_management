<?php

use Illuminate\Support\Facades\Schema;

test('the product_stocks table has the expected columns', function () {
    expect(Schema::hasTable('product_stocks'))->toBeTrue();
    expect(Schema::hasColumns('product_stocks', [
        'id', 'product_id', 'variant', 'sku', 'price', 'qty', 'image', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
