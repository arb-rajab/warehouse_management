<?php

use Illuminate\Support\Facades\Schema;

test('home_categories table has the expected columns', function () {
    expect(Schema::hasTable('home_categories'))->toBeTrue();
    expect(Schema::hasColumns('home_categories', [
        'id', 'category_id', 'subsubcategories', 'status', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
