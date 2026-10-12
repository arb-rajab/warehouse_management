<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

function loadCreateOffersCatalogsTableMigration(): object
{
    return require database_path('migrations/2026_09_29_000003_create_offers_catalogs_table.php');
}

test('the migration leaves an existing offers_catalogs table alone', function () {
    // The shared-database case: `offers_catalogs` belongs to the store app,
    // with columns this app never reads.
    Schema::dropIfExists('offers_catalogs');
    Schema::create('offers_catalogs', function (Blueprint $table) {
        $table->id();
        $table->string('store_only_column')->nullable();
    });

    loadCreateOffersCatalogsTableMigration()->up();

    expect(Schema::hasColumn('offers_catalogs', 'store_only_column'))->toBeTrue();
    expect(Schema::hasColumn('offers_catalogs', 'file'))->toBeFalse();
});

test('the migration creates the expected columns', function () {
    expect(Schema::hasColumns('offers_catalogs', ['id', 'file', 'created_at', 'updated_at']))->toBeTrue();
});

test('the migration skips dropping the offers_catalogs table in production', function () {
    Schema::dropIfExists('offers_catalogs');
    loadCreateOffersCatalogsTableMigration()->up();

    app()->instance('env', 'production');
    loadCreateOffersCatalogsTableMigration()->down();
    app()->instance('env', 'testing');

    expect(Schema::hasTable('offers_catalogs'))->toBeTrue();
});
