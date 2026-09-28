<?php

use Illuminate\Support\Facades\Schema;

test('the role_translations table has the expected columns', function () {
    expect(Schema::hasTable('role_translations'))->toBeTrue();
    expect(Schema::hasColumns('role_translations', [
        'id', 'role_id', 'name', 'lang', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
