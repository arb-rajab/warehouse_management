<?php

use Illuminate\Support\Facades\Schema;

test('category_translations has the expected columns', function () {
    expect(Schema::hasTable('category_translations'))->toBeTrue();
    expect(Schema::hasColumns('category_translations', [
        'id', 'category_id', 'name', 'lang', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
