<?php

use App\Models\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadUpgradeLegacyProductsStandInMigration(): object
{
    return require database_path('migrations/2026_09_08_000003_upgrade_legacy_products_stand_in.php');
}

/**
 * Rebuilds the pre-alignment stand-in shape: `image_url` and `boxes_count`
 * columns, no `thumbnail_img`. This is what staging and older local checkouts
 * still carry, since editing an already-run migration changes nothing.
 */
function revertProductsToLegacyStandIn(): void
{
    Schema::table('products', function (Blueprint $table) {
        $table->string('image_url')->nullable();
        $table->unsignedInteger('boxes_count')->default(1);
        $table->dropColumn('thumbnail_img');
    });
}

test('the migration leaves a table without image_url alone', function () {
    // Both the store's real `products` and an already-current stand-in: the
    // store has no column of that name anywhere in its 72.
    expect(Schema::hasColumn('products', 'image_url'))->toBeFalse();

    loadUpgradeLegacyProductsStandInMigration()->up();

    expect(Schema::hasColumn('products', 'thumbnail_img'))->toBeTrue();
    expect(Schema::hasColumn('products', 'image_url'))->toBeFalse();
    expect(Schema::hasColumn('products', 'boxes_count'))->toBeFalse();
});

test('the migration swaps a legacy stand-in over to thumbnail_img', function () {
    revertProductsToLegacyStandIn();
    DB::table('products')->insert([
        'name' => 'Widgets',
        'image_url' => 'https://cdn.example.com/widgets.png',
        'boxes_count' => 7,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    loadUpgradeLegacyProductsStandInMigration()->up();

    expect(Schema::hasColumn('products', 'thumbnail_img'))->toBeTrue();
    expect(Schema::hasColumn('products', 'image_url'))->toBeFalse();
    expect(Schema::hasColumn('products', 'boxes_count'))->toBeFalse();
    // The row survives the column surgery.
    expect(DB::table('products')->where('name', 'Widgets')->exists())->toBeTrue();
});

test('the migration carries existing box counts into wms_product_settings', function () {
    revertProductsToLegacyStandIn();
    DB::table('wms_product_settings')->delete();

    $configured = DB::table('products')->insertGetId([
        'name' => 'Widgets', 'boxes_count' => 7, 'created_at' => now(), 'updated_at' => now(),
    ]);
    // Noise: a second product with a different count, proving each row carries
    // its own value rather than one being applied to all.
    $other = DB::table('products')->insertGetId([
        'name' => 'Gadgets', 'boxes_count' => 3, 'created_at' => now(), 'updated_at' => now(),
    ]);

    loadUpgradeLegacyProductsStandInMigration()->up();

    expect(Product::query()->find($configured)->boxes_count)->toBe(7);
    expect(Product::query()->find($other)->boxes_count)->toBe(3);
});

test('the migration does not overwrite a box count this app already stored', function () {
    revertProductsToLegacyStandIn();
    DB::table('wms_product_settings')->delete();

    $product = DB::table('products')->insertGetId([
        'name' => 'Widgets', 'boxes_count' => 7, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('wms_product_settings')->insert([
        'product_id' => $product, 'boxes_count' => 99, 'created_at' => now(), 'updated_at' => now(),
    ]);

    loadUpgradeLegacyProductsStandInMigration()->up();

    expect(Product::query()->find($product)->boxes_count)->toBe(99);
});

test('the migration skips dropping columns from the products table in production', function () {
    revertProductsToLegacyStandIn();
    loadUpgradeLegacyProductsStandInMigration()->up();

    app()->instance('env', 'production');
    loadUpgradeLegacyProductsStandInMigration()->down();
    app()->instance('env', 'testing');

    expect(Schema::hasColumn('products', 'thumbnail_img'))->toBeTrue();
    expect(Schema::hasColumn('products', 'image_url'))->toBeFalse();
});

test('down() is a no-op even outside production, since a fresh install is indistinguishable from an already-upgraded stand-in', function () {
    // No revertProductsToLegacyStandIn(): this is the fresh-install shape
    // create_products_table already builds — up() never touches it.
    expect(Schema::hasColumn('products', 'thumbnail_img'))->toBeTrue();
    expect(Schema::hasColumn('products', 'image_url'))->toBeFalse();

    loadUpgradeLegacyProductsStandInMigration()->down();

    // A guess here would re-add image_url/boxes_count and drop
    // thumbnail_img, breaking every product read on a partial rollback.
    expect(Schema::hasColumn('products', 'thumbnail_img'))->toBeTrue();
    expect(Schema::hasColumn('products', 'image_url'))->toBeFalse();
    expect(Schema::hasColumn('products', 'boxes_count'))->toBeFalse();
});
