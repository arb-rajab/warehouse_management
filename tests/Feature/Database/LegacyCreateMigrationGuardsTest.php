<?php

use Illuminate\Support\Facades\Schema;

/**
 * Every legacy create migration whose table the store's own database already
 * has, keyed by migration file name.
 *
 * @return array<string, array{string, string}>
 */
function legacyCreateMigrations(): array
{
    $migrations = [];

    foreach (glob(__DIR__.'/../../../database/migrations/2026_09_28_*_create_*_table.php') as $path) {
        if (preg_match("/Schema::create\('(\w+)'/", file_get_contents($path), $match)) {
            $migrations[basename($path)] = [$path, $match[1]];
        }
    }

    return $migrations;
}

dataset('legacy create migrations', fn () => legacyCreateMigrations());

test('there are legacy create migrations to guard', function () {
    expect(legacyCreateMigrations())->toHaveCount(74);
});

test('up() on an existing table is a no-op', function (string $path, string $table) {
    Schema::dropIfExists($table);
    Schema::create($table, function ($blueprint) {
        $blueprint->string('store_only_column')->nullable();
    });

    (require $path)->up();

    expect(Schema::getColumnListing($table))->toBe(['store_only_column']);
})->with('legacy create migrations');

test('down() in production does not drop the table', function (string $path, string $table) {
    app()->instance('env', 'production');
    (require $path)->down();
    app()->instance('env', 'testing');

    expect(Schema::hasTable($table))->toBeTrue();
})->with('legacy create migrations');

test('down() outside production still drops the table', function () {
    [$path, $table] = legacyCreateMigrations()['2026_09_28_003003_create_orders_table.php'];

    (require $path)->down();

    expect(Schema::hasTable($table))->toBeFalse();
});
