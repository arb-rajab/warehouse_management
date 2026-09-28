<?php

use Illuminate\Support\Facades\Schema;

test('the product_queries table has the expected columns', function () {
    expect(Schema::hasTable('product_queries'))->toBeTrue();
    expect(Schema::hasColumns('product_queries', [
        'id', 'customer_id', 'seller_id', 'product_id', 'question', 'reply', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
