<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadDropRaizerAdminMigration(): object
{
    return require database_path('migrations/2026_11_01_120000_drop_raizer_admin_from_users_table.php');
}

test('users has no raizer_admin column after migrating', function () {
    expect(Schema::hasColumn('users', 'raizer_admin'))->toBeFalse();
});

test('the drop is skipped when the column is already gone', function () {
    DB::table('users')->insert(['name' => 'Jane Doe']);

    loadDropRaizerAdminMigration()->up();

    expect(Schema::hasColumn('users', 'raizer_admin'))->toBeFalse()
        ->and(DB::table('users')->where('name', 'Jane Doe')->exists())->toBeTrue();
});

test('rolling back restores a nullable column, and migrating again drops it with the rows kept', function () {
    $migration = loadDropRaizerAdminMigration();

    $migration->down();
    expect(Schema::hasColumn('users', 'raizer_admin'))->toBeTrue();

    DB::table('users')->insert(['name' => 'Jane Doe', 'raizer_admin' => true]);
    expect(DB::table('users')->where('name', 'Jane Doe')->value('raizer_admin'))->toBe(1);

    $migration->up();

    expect(Schema::hasColumn('users', 'raizer_admin'))->toBeFalse()
        ->and(DB::table('users')->where('name', 'Jane Doe')->exists())->toBeTrue();
});
