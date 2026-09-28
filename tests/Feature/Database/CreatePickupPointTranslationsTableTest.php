<?php

use Illuminate\Support\Facades\Schema;

test('the pickup_point_translations table has the expected columns', function () {
    expect(Schema::hasTable('pickup_point_translations'))->toBeTrue();
    expect(Schema::hasColumns('pickup_point_translations', [
        'id', 'pickup_point_id', 'name', 'address', 'lang', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
