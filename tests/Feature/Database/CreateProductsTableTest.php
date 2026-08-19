<?php

use Illuminate\Support\Facades\Schema;

function loadCreateProductsTableMigration(): object
{
    return require database_path('migrations/2026_08_08_154136_create_products_table.php');
}

test('the migration skips creating the products table in production', function () {
    Schema::dropIfExists('products');

    app()->instance('env', 'production');
    loadCreateProductsTableMigration()->up();
    app()->instance('env', 'testing');

    expect(Schema::hasTable('products'))->toBeFalse();
});

test('the migration creates the products table outside production', function () {
    Schema::dropIfExists('products');

    loadCreateProductsTableMigration()->up();

    expect(Schema::hasTable('products'))->toBeTrue();
});

test('the migration skips dropping the products table in production', function () {
    Schema::dropIfExists('products');
    loadCreateProductsTableMigration()->up();

    app()->instance('env', 'production');
    loadCreateProductsTableMigration()->down();
    app()->instance('env', 'testing');

    expect(Schema::hasTable('products'))->toBeTrue();
});
