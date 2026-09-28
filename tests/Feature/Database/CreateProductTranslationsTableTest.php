<?php

use Illuminate\Support\Facades\Schema;

test('the product_translations table has the expected columns', function () {
    expect(Schema::hasTable('product_translations'))->toBeTrue();
    expect(Schema::hasColumns('product_translations', [
        'id', 'product_id', 'name', 'unit', 'description', 'lang', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
