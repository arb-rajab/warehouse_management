<?php

use Illuminate\Support\Facades\Schema;

test('flash_deal_products table has the expected columns', function () {
    expect(Schema::hasTable('flash_deal_products'))->toBeTrue();
    expect(Schema::hasColumns('flash_deal_products', [
        'id', 'flash_deal_id', 'product_id', 'discount', 'discount_type', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
