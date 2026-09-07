<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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

test('the migration creates the expected columns, with a required name and nullable image_url', function () {
    Schema::dropIfExists('products');
    loadCreateProductsTableMigration()->up();

    expect(Schema::hasColumns('products', ['id', 'name', 'image_url', 'created_at', 'updated_at']))->toBeTrue();

    expect(fn () => DB::table('products')->insert(['created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);

    DB::table('products')->insert(['name' => 'Widgets', 'image_url' => null, 'created_at' => now(), 'updated_at' => now()]);
    expect(DB::table('products')->where('name', 'Widgets')->exists())->toBeTrue();
});

test('the migration skips dropping the products table in production', function () {
    Schema::dropIfExists('products');
    loadCreateProductsTableMigration()->up();

    app()->instance('env', 'production');
    loadCreateProductsTableMigration()->down();
    app()->instance('env', 'testing');

    expect(Schema::hasTable('products'))->toBeTrue();
});
