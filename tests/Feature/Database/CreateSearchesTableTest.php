<?php

use Illuminate\Support\Facades\Schema;

test('the searches table has the expected columns', function () {
    expect(Schema::hasTable('searches'))->toBeTrue();
    expect(Schema::hasColumns('searches', [
        'id', 'query', 'count', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
