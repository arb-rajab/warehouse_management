<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

function loadCreateProductCollectionsTableMigration(): object
{
    return require database_path('migrations/2026_09_30_000012_create_product_collections_table.php');
}

test('the migration leaves an existing product_collections table alone', function () {
    // The store's own database already has this table, with its own data.
    Schema::dropIfExists('product_collections');
    Schema::create('product_collections', function (Blueprint $table) {
        $table->string('store_only_column')->nullable();
    });

    loadCreateProductCollectionsTableMigration()->up();

    expect(Schema::hasColumn('product_collections', 'store_only_column'))->toBeTrue()
        ->and(Schema::hasColumn('product_collections', 'name'))->toBeFalse();
});

test('the migration creates the expected columns', function () {
    expect(Schema::hasColumns('product_collections', [
        'id', 'name', 'serial', 'amount', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

test('the migration skips dropping the product_collections table in production', function () {
    app()->instance('env', 'production');
    loadCreateProductCollectionsTableMigration()->down();
    app()->instance('env', 'testing');

    expect(Schema::hasTable('product_collections'))->toBeTrue();
});
