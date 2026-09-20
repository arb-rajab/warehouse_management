<?php

use Illuminate\Support\Facades\Schema;
use Spatie\Health\Models\HealthCheckResultHistoryItem;

function loadCreateHealthTablesMigration(): object
{
    return require database_path('migrations/2026_08_16_151529_create_health_tables.php');
}

test('down() drops the health history table on the health connection', function () {
    $historyItem = new HealthCheckResultHistoryItem;
    $connection = $historyItem->getConnectionName();
    $tableName = $historyItem->getTable();

    // Health runs on its own sqlite connection, migrated independently of
    // RefreshDatabase's usual connection — (re)create it explicitly here
    // rather than assume the suite's migration already left it in place.
    Schema::connection($connection)->dropIfExists($tableName);
    loadCreateHealthTablesMigration()->up();

    expect(Schema::connection($connection)->hasTable($tableName))->toBeTrue();

    loadCreateHealthTablesMigration()->down();

    expect(Schema::connection($connection)->hasTable($tableName))->toBeFalse();
});
