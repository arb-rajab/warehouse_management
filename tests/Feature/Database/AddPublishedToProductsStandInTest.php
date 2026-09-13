<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadAddPublishedToProductsStandInMigration(): object
{
    return require database_path('migrations/2026_09_13_000004_add_published_to_products_stand_in.php');
}

/**
 * Rebuilds the pre-`published` stand-in shape. This is what staging and older
 * local checkouts still carry: `create_products_table` was edited to build the
 * column, but a database that already recorded that migration never re-runs it.
 */
function revertProductsToStandInWithoutPublished(): void
{
    Schema::table('products', function (Blueprint $table) {
        $table->dropColumn('published');
    });
}

test('the migration leaves a products table that already has published alone', function () {
    // The production case: `products` is the store's own table and already
    // carries `published`. Re-adding it would fail outright, and this app
    // must never alter a table it only reads.
    expect(Schema::hasColumn('products', 'published'))->toBeTrue();

    DB::table('products')->insert([
        'name' => 'Widgets', 'ar_name' => 'ودجات', 'published' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    loadAddPublishedToProductsStandInMigration()->up();

    expect(Schema::hasColumn('products', 'published'))->toBeTrue();
    expect(DB::table('products')->where('name', 'Widgets')->value('published'))->toBe(0);
});

test('the migration adds published to a stand-in that predates it, defaulting existing rows to active', function () {
    revertProductsToStandInWithoutPublished();
    DB::table('products')->insert([
        'name' => 'Widgets', 'ar_name' => 'ودجات', 'created_at' => now(), 'updated_at' => now(),
    ]);

    loadAddPublishedToProductsStandInMigration()->up();

    expect(Schema::hasColumn('products', 'published'))->toBeTrue();
    expect(DB::table('products')->where('name', 'Widgets')->value('published'))->toBe(1);
});

test('the added column stores and returns a deactivated product, with noise from an active one', function () {
    revertProductsToStandInWithoutPublished();
    loadAddPublishedToProductsStandInMigration()->up();

    DB::table('products')->insert([
        'name' => 'Widgets', 'ar_name' => 'ودجات', 'published' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('products')->insert([
        'name' => 'Gadgets', 'ar_name' => 'أدوات', 'published' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(DB::table('products')->where('name', 'Widgets')->value('published'))->toBe(0);
    expect(DB::table('products')->where('name', 'Gadgets')->value('published'))->toBe(1);
});

test('rolling the migration back drops the column outside production', function () {
    loadAddPublishedToProductsStandInMigration()->down();

    expect(Schema::hasColumn('products', 'published'))->toBeFalse();
});

test('the migration skips dropping published from the products table in production', function () {
    app()->instance('env', 'production');
    loadAddPublishedToProductsStandInMigration()->down();
    app()->instance('env', 'testing');

    expect(Schema::hasColumn('products', 'published'))->toBeTrue();
});
