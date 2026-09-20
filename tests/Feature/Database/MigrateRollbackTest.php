<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

test('migrate:rollback leaves no orphan tables and migrate runs cleanly again', function () {
    Artisan::call('migrate:rollback', ['--force' => true]);

    // Only the migration repository itself should remain.
    expect(Schema::getTableListing(schemaQualified: false))->toBe(['wms_migrations']);

    Artisan::call('migrate', ['--force' => true]);

    expect(Schema::hasTable('wms_users'))->toBeTrue();
    expect(Schema::hasTable('users'))->toBeFalse();
});

test('migrate:refresh runs cleanly', function () {
    Artisan::call('migrate:refresh', ['--force' => true]);

    expect(Schema::hasTable('wms_users'))->toBeTrue();
});
