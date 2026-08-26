<?php

use Illuminate\Support\Facades\Schema;

test('the mobile_app_version_requirements table has the expected columns', function () {
    expect(Schema::hasTable('mobile_app_version_requirements'))->toBeTrue();
    expect(Schema::hasColumns('mobile_app_version_requirements', [
        'id', 'minimum_version', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
