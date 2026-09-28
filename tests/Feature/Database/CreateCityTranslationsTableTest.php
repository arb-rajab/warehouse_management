<?php

use Illuminate\Support\Facades\Schema;

test('city_translations has the expected columns', function () {
    expect(Schema::hasTable('city_translations'))->toBeTrue();
    expect(Schema::hasColumns('city_translations', [
        'id', 'city_id', 'name', 'lang', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
