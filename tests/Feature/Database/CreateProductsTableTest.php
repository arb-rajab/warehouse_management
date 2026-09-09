<?php

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadCreateProductsTableMigration(): object
{
    return require database_path('migrations/2026_08_08_154136_create_products_table.php');
}

test('the migration leaves an existing products table alone', function () {
    // The shared-database case: in production `products` belongs to the store
    // app, with 72 columns this app never reads. The migration must not try to
    // create over it — which would fail outright — nor alter it.
    Schema::dropIfExists('products');
    Schema::create('products', function (Blueprint $table) {
        $table->integer('id')->autoIncrement();
        $table->string('name');
        $table->string('store_only_column')->nullable();
    });

    loadCreateProductsTableMigration()->up();

    expect(Schema::hasColumn('products', 'store_only_column'))->toBeTrue();
    expect(Schema::hasColumn('products', 'thumbnail_img'))->toBeFalse();
});

test('the migration creates the products table when none exists', function () {
    Schema::dropIfExists('products');

    loadCreateProductsTableMigration()->up();

    expect(Schema::hasTable('products'))->toBeTrue();
});

test('the migration creates the expected columns, with a required name and nullable thumbnail_img', function () {
    Schema::dropIfExists('products');
    loadCreateProductsTableMigration()->up();

    expect(Schema::hasColumns('products', ['id', 'name', 'ar_name', 'thumbnail_img', 'created_at', 'updated_at']))->toBeTrue();
    // `image_url` is derived from the thumbnail's upload row, not stored —
    // the store app has no URL column at all.
    expect(Schema::hasColumn('products', 'image_url'))->toBeFalse();
    // `boxes_count` lives in this app's own wms_product_settings table.
    expect(Schema::hasColumn('products', 'boxes_count'))->toBeFalse();

    expect(fn () => DB::table('products')->insert(['created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);

    DB::table('products')->insert(['name' => 'Widgets', 'thumbnail_img' => null, 'created_at' => now(), 'updated_at' => now()]);
    expect(DB::table('products')->where('name', 'Widgets')->exists())->toBeTrue();
    // `ar_name` is NOT NULL on the store's table; the stand-in defaults it to
    // the empty string so a row with no Arabic name is still insertable.
    expect(DB::table('products')->where('name', 'Widgets')->value('ar_name'))->toBe('');

    DB::table('products')->insert(['name' => 'Gadgets', 'ar_name' => 'أدوات', 'created_at' => now(), 'updated_at' => now()]);
    expect(DB::table('products')->where('name', 'Gadgets')->value('ar_name'))->toBe('أدوات');
});

test('the stand-in mirrors the store\'s NOT NULL timestamps with a current default', function () {
    // The store's `created_at`/`updated_at` are NOT NULL DEFAULT
    // current_timestamp(), not Laravel's nullable `timestamps()`. A row
    // inserted without them must therefore come back populated, not null.
    Schema::dropIfExists('products');
    loadCreateProductsTableMigration()->up();

    DB::table('products')->insert(['name' => 'Widgets']);

    $product = DB::table('products')->where('name', 'Widgets')->first();
    expect($product->created_at)->not->toBeNull();
    expect($product->updated_at)->not->toBeNull();
});

test('the migration skips dropping the products table in production', function () {
    Schema::dropIfExists('products');
    loadCreateProductsTableMigration()->up();

    app()->instance('env', 'production');
    loadCreateProductsTableMigration()->down();
    app()->instance('env', 'testing');

    expect(Schema::hasTable('products'))->toBeTrue();
});

test('products.id and every product_id FK are declared as signed integers, not bigints', function () {
    // The store owns `products` and its `id` is a signed int(11), so every FK
    // to it has to be a plain `integer` column rather than `foreignId()`'s
    // bigint unsigned, or the constraint cannot be created against the live
    // table. Asserted against the migration source because sqlite reports both
    // as "integer" — there is no schema-level way to catch a regression here
    // on the test connection.
    $columns = [
        '2026_08_08_154136_create_products_table.php' => ["\$table->integer('id')->autoIncrement();"],
        '2026_08_08_154139_create_pallets_table.php' => ["\$table->integer('product_id');"],
        '2026_08_10_010000_create_cell_status_logs_table.php' => ["\$table->integer('product_id')->nullable();"],
        '2026_09_05_000001_create_cell_verification_reports_table.php' => [
            "\$table->integer('expected_product_id')->nullable();",
            "\$table->integer('reported_product_id')->nullable();",
        ],
        '2026_09_08_000001_create_uploads_table.php' => ["\$table->integer('id')->autoIncrement();"],
        '2026_09_08_000002_create_wms_product_settings_table.php' => ["\$table->integer('product_id')->primary();"],
    ];

    foreach ($columns as $migration => $declarations) {
        $source = file_get_contents(database_path('migrations/'.$migration));

        foreach ($declarations as $declaration) {
            expect($source)->toContain($declaration);
        }

        expect($source)->not->toContain("foreignId('product_id')");
    }
});
