<?php

use Illuminate\Support\Facades\Schema;

test('cities has the expected columns', function () {
    expect(Schema::hasTable('cities'))->toBeTrue();
    expect(Schema::hasColumns('cities', [
        'id', 'name', 'state_id', 'cost', 'status', 'created_at', 'updated_at', 'deleted_at',
    ]))->toBeTrue();
});
