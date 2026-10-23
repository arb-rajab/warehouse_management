<?php

use Illuminate\Support\Facades\Schema;
use Spatie\Health\Models\HealthCheckResultHistoryItem;
use Symfony\Component\Process\Process;

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

/**
 * The health table lives on its own SQLite connection, so `migrate --pretend`
 * only stayed read-only if the migrator knew to pretend on that connection.
 * Before the migration named it, a pretend run really created the table and the
 * real migrate after it failed with "table already exists". These tests run
 * the actual artisan commands against scratch SQLite files, because the
 * migrator's connection handling is the thing under test.
 */

/**
 * @param  array<string, string>  $databases
 * @param  list<string>  $command
 */
function artisanAgainst(array $databases, array $command): Process
{
    $process = new Process([PHP_BINARY, 'artisan', ...$command, '--force', '--no-interaction'], base_path(), [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $databases['default'],
        'TELESCOPE_DB_DATABASE' => $databases['telescope'],
        'PULSE_DB_DATABASE' => $databases['pulse'],
        'HEALTH_DB_DATABASE' => $databases['health'],
        'TELESCOPE_ENABLED' => 'false',
        'PULSE_ENABLED' => 'false',
        'CACHE_STORE' => 'array',
    ]);
    $process->setTimeout(240);
    $process->run();

    return $process;
}

/**
 * @return list<string>
 */
function tablesIn(string $sqliteFile): array
{
    $pdo = new PDO('sqlite:'.$sqliteFile);

    return $pdo->query("select name from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name")
        ->fetchAll(PDO::FETCH_COLUMN);
}

test('the health migration runs on the health connection', function () {
    expect(loadCreateHealthTablesMigration()->getConnection())->toBe('health');
});

test('migrate --pretend writes nothing but the migration table, and the real migrate after it succeeds', function () {
    $databases = [];

    foreach (['default', 'telescope', 'pulse', 'health'] as $name) {
        $databases[$name] = tempnam(sys_get_temp_dir(), "wms-pretend-{$name}-");
    }

    try {
        $pretend = artisanAgainst($databases, ['migrate', '--pretend']);

        expect($pretend->isSuccessful())->toBeTrue($pretend->getErrorOutput().$pretend->getOutput())
            ->and($pretend->getOutput())->toContain('health_check_result_history_items')
            ->and(tablesIn($databases['health']))->toBe([])
            ->and(tablesIn($databases['telescope']))->toBe([])
            ->and(tablesIn($databases['pulse']))->toBe([])
            // Laravel's own prepareDatabase() installs the migration repository
            // even when pretending; nothing else may appear.
            ->and(tablesIn($databases['default']))->toBe(['wms_migrations']);

        $migrate = artisanAgainst($databases, ['migrate']);

        expect($migrate->isSuccessful())->toBeTrue($migrate->getErrorOutput().$migrate->getOutput())
            ->and(tablesIn($databases['health']))->toBe(['health_check_result_history_items'])
            ->and(tablesIn($databases['default']))->not->toContain('health_check_result_history_items');
    } finally {
        array_map('unlink', array_filter($databases, 'file_exists'));
    }
});
