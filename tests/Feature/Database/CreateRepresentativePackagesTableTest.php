<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

function loadCreateRepresentativePackagesTableMigration(): object
{
    return require database_path('migrations/2026_09_30_000003_create_representative_packages_table.php');
}

test('the migration leaves an existing representative_packages table alone', function () {
    // The store's own database already has this table, with its own data.
    Schema::dropIfExists('representative_packages');
    Schema::create('representative_packages', function (Blueprint $table) {
        $table->string('store_only_column')->nullable();
    });

    loadCreateRepresentativePackagesTableMigration()->up();

    expect(Schema::hasColumn('representative_packages', 'store_only_column'))->toBeTrue()
        ->and(Schema::hasColumn('representative_packages', 'name'))->toBeFalse();
});

test('the migration creates the expected columns', function () {
    expect(Schema::hasColumns('representative_packages', [
        'id', 'name', 'percentage', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

test('the migration skips dropping the representative_packages table in production', function () {
    app()->instance('env', 'production');
    loadCreateRepresentativePackagesTableMigration()->down();
    app()->instance('env', 'testing');

    expect(Schema::hasTable('representative_packages'))->toBeTrue();
});
