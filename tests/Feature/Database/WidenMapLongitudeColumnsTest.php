<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * addresses.longitude and shops.delivery_pickup_longitude were decimal(17,15):
 * two digits before the point, so a longitude of 100 or more (Sydney, 151.2;
 * anything east of Thailand or west of Hawaii) failed to save on MySQL. The
 * map widgets fill both from the same Google Maps pin. Runs on SQLite and, in
 * CI's MySQL job and pre_push_check.py's MySQL list, on MySQL.
 */
function loadWidenMapLongitudeColumnsMigration(): object
{
    return require database_path('migrations/2026_10_21_110000_widen_map_longitude_columns.php');
}

function mapColumnType(string $table, string $column): string
{
    return (string) collect(Schema::getColumns($table))->firstWhere('name', $column)['type'];
}

function onMysql(): bool
{
    return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
}

/**
 * Puts a column back to the width it had before the migration, as on a database
 * migrated before 2026-10-21 (and as the legacy store's own tables are).
 */
function narrowMapColumn(string $table, string $column, int $precision = 17, int $scale = 15): void
{
    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->decimal($column, $precision, $scale)->nullable()->change());
}

// MySQL commits DDL at once, so a test that narrows a column must leave it as
// the migration left it for the tests after it.
afterEach(function (): void {
    if (onMysql()) {
        foreach ([['addresses', 'longitude'], ['shops', 'delivery_pickup_longitude']] as [$table, $column]) {
            if (mapColumnType($table, $column) !== 'decimal(18,15)') {
                narrowMapColumn($table, $column, 18, 15);
            }
        }
    }
});

it('stores every valid longitude, east and west of the antimeridian, after migrating', function (float $longitude): void {
    DB::table('addresses')->insert(['user_id' => 1, 'state_id' => 1, 'latitude' => -33.8688, 'longitude' => $longitude]);
    DB::table('shops')->insert(['user_id' => 1, 'name' => 'Shop', 'delivery_pickup_latitude' => -33.8688, 'delivery_pickup_longitude' => $longitude]);

    expect((float) DB::table('addresses')->value('longitude'))->toBe($longitude)
        ->and((float) DB::table('addresses')->value('latitude'))->toBe(-33.8688)
        ->and((float) DB::table('shops')->value('delivery_pickup_longitude'))->toBe($longitude);
})->with([
    'Sydney' => 151.2093,
    'just east of the antimeridian' => -179.9999,
    'the antimeridian' => 180.0,
    'Amsterdam' => 4.9041,
]);

it('widens both longitude columns to decimal(18,15) on MySQL and leaves the latitudes alone', function (): void {
    expect(mapColumnType('addresses', 'longitude'))->toBe('decimal(18,15)')
        ->and(mapColumnType('shops', 'delivery_pickup_longitude'))->toBe('decimal(18,15)')
        ->and(mapColumnType('addresses', 'latitude'))->toBe('decimal(17,15)')
        ->and(mapColumnType('shops', 'delivery_pickup_latitude'))->toBe('decimal(17,15)');

    $nullable = fn (string $table, string $column): bool => collect(Schema::getColumns($table))->firstWhere('name', $column)['nullable'];
    expect($nullable('addresses', 'longitude'))->toBeTrue()
        ->and($nullable('shops', 'delivery_pickup_longitude'))->toBeTrue();
})->skip(fn () => ! onMysql(), 'SQLite keeps no decimal precision.');

it('widens a narrow column and keeps the rows already in it', function (): void {
    narrowMapColumn('addresses', 'longitude');
    narrowMapColumn('shops', 'delivery_pickup_longitude');
    DB::table('addresses')->insert(['user_id' => 7, 'state_id' => 1, 'longitude' => '4.123456789012345']);
    DB::table('addresses')->insert(['user_id' => 8, 'state_id' => 1, 'longitude' => null]);
    DB::table('shops')->insert(['user_id' => 7, 'name' => 'Shop', 'delivery_pickup_longitude' => -99.5]);

    loadWidenMapLongitudeColumnsMigration()->up();

    expect(mapColumnType('addresses', 'longitude'))->toBe('decimal(18,15)')
        ->and(mapColumnType('shops', 'delivery_pickup_longitude'))->toBe('decimal(18,15)')
        ->and(DB::table('addresses')->where('user_id', 7)->value('longitude'))->toBe('4.123456789012345')
        ->and(DB::table('addresses')->where('user_id', 8)->value('longitude'))->toBeNull()
        ->and(DB::table('shops')->where('user_id', 7)->value('delivery_pickup_longitude'))->toBe('-99.500000000000000');
})->skip(fn () => ! onMysql(), 'SQLite keeps no decimal precision.');

it('is idempotent, and leaves a column that is already wider as it is', function (): void {
    $migration = loadWidenMapLongitudeColumnsMigration();
    $migration->up();
    $migration->up();

    expect(mapColumnType('addresses', 'longitude'))->toBe('decimal(18,15)');

    narrowMapColumn('shops', 'delivery_pickup_longitude', 20, 15);
    $migration->up();

    expect(mapColumnType('shops', 'delivery_pickup_longitude'))->toBe('decimal(20,15)');
})->skip(fn () => ! onMysql(), 'SQLite keeps no decimal precision.');

it('keeps a wide longitude when rolled back', function (): void {
    DB::table('addresses')->insert(['user_id' => 1, 'state_id' => 1, 'longitude' => 151.2093]);
    DB::table('shops')->insert(['user_id' => 1, 'name' => 'Shop', 'delivery_pickup_longitude' => -179.9999]);

    loadWidenMapLongitudeColumnsMigration()->down();

    expect((float) DB::table('addresses')->value('longitude'))->toBe(151.2093)
        ->and((float) DB::table('shops')->value('delivery_pickup_longitude'))->toBe(-179.9999);

    if (onMysql()) {
        expect(mapColumnType('addresses', 'longitude'))->toBe('decimal(18,15)');
    }
});

it('runs on SQLite without rebuilding the tables', function (): void {
    DB::table('addresses')->insert(['user_id' => 1, 'state_id' => 1, 'longitude' => 4.9]);
    $before = mapColumnType('addresses', 'longitude');

    $migration = loadWidenMapLongitudeColumnsMigration();
    $migration->up();
    $migration->up();

    expect(mapColumnType('addresses', 'longitude'))->toBe($before)
        ->and(DB::table('addresses')->count())->toBe(1)
        ->and(Schema::hasColumns('shops', ['delivery_pickup_latitude', 'delivery_pickup_longitude']))->toBeTrue();
})->skip(fn () => onMysql(), 'Asserts SQLite behaviour; the MySQL behaviour is covered above.');
