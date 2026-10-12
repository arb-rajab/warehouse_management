<?php

use Illuminate\Support\Facades\Schema;

function loadCreateCacheTableMigration(): object
{
    return require database_path('migrations/0001_01_01_000001_create_cache_table.php');
}

test('down() drops the wms_ prefixed tables', function () {
    loadCreateCacheTableMigration()->down();

    expect(Schema::hasTable('wms_cache'))->toBeFalse();
    expect(Schema::hasTable('wms_cache_locks'))->toBeFalse();
});

test('down() falls back to the legacy names once a rename migration has un-prefixed them', function () {
    Schema::rename('wms_cache', 'cache');
    Schema::rename('wms_cache_locks', 'cache_locks');

    loadCreateCacheTableMigration()->down();

    expect(Schema::hasTable('cache'))->toBeFalse();
    expect(Schema::hasTable('cache_locks'))->toBeFalse();
    expect(Schema::hasTable('wms_cache'))->toBeFalse();
});
