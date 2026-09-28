<?php

use Illuminate\Support\Facades\Schema;

test('countries has the expected columns', function () {
    expect(Schema::hasTable('countries'))->toBeTrue();
    expect(Schema::hasColumns('countries', [
        'id', 'code', 'name', 'zone_id', 'status', 'created_at', 'updated_at', 'deleted_at',
    ]))->toBeTrue();
});
