<?php

use App\Models\Cart;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadAddLegacyColumnsToStoreTablesMigration(): object
{
    return require database_path('migrations/2026_09_30_000000_add_legacy_columns_to_store_tables.php');
}

test('every column the legacy store added after shop.sql exists', function () {
    expect(Schema::hasColumns('users', [
        'company_name', 'admin_verified', 'admin_verified_at', 'last_order_at', 'is_important', 'tax_number',
        'company_address', 'shipping_address', 'trade_license', 'bank_account_number', 'is_rep', 'from_api',
        'member_serial', 'synched_with_otajer', 'AccSysID', 'registration_completed', 'rep_serial', 'rep_id',
        'rep_package_id', 'rep_discount_percentage', 'admin_notes', 'activity_status', 'profits', 'device_key',
        'raizer_admin',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('categories', ['serial', 'cover_image']))->toBeTrue()
        ->and(Schema::hasColumn('reviews', 'photos'))->toBeTrue()
        ->and(Schema::hasColumns('shops', ['top_banner', 'banner_full_width_1', 'banners_half_width', 'banner_full_width_2']))->toBeTrue()
        ->and(Schema::hasColumn('countries', 'priority'))->toBeTrue()
        ->and(Schema::hasColumn('brands', 'serial'))->toBeTrue()
        ->and(Schema::hasColumns('carts', [
            'deleted_at', 'variation_qty', 'by_rep', 'for_customer', 'item_notes', 'product_serial', 'rep_price',
            'is_price_changed', 'is_admin_notified',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('orders', [
            'company_address', 'company_shipping_address', 'company_tax_number', 'order_from', 'by_rep',
            'for_customer', 'manager_notes', 'member_serial', 'delivery_date', 'wms_status', 'otajerOrderID',
            'profits', 'discount_percent', 'indicator', 'invoice_number',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('products', [
            'added_by', 'user_id', 'brand_id', 'photos', 'old_photo', 'video_provider', 'video_link', 'tags',
            'description', 'unit_price', 'purchase_price', 'variant_product', 'attributes', 'choice_options', 'colors',
            'variations', 'todays_deal', 'approved', 'stock_visibility_state', 'cash_on_delivery', 'featured',
            'seller_featured', 'current_stock', 'unit', 'weight', 'min_qty', 'max_qty', 'low_stock_quantity',
            'discount', 'discount_type', 'discount_start_date', 'discount_end_date', 'tax', 'tax_type',
            'shipping_type', 'shipping_cost', 'is_quantity_multiplied', 'est_shipping_days', 'num_of_sale',
            'meta_title', 'meta_description', 'meta_img', 'pdf', 'slug', 'rating', 'barcode', 'digital',
            'auction_product', 'file_name', 'file_path', 'external_link', 'external_link_btn', 'wholesale_product',
            'serial', 'from_api', 'mat_id', 'unit_equal', 'isNewProduct', 'isOfferProduct', 'for_sale', 'api_unitId',
            'deleted_from_api', 'api_unit_name', 'collection_id',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('order_details', [
            'product_serial', 'variation_qty', 'item_notes', 'product_matId', 'product_name', 'original_price',
            'is_price_changed', 'profits',
        ]))->toBeTrue();
});

test('a store user row gets the legacy defaults', function () {
    DB::table('users')->insert(['name' => 'Jane Doe']);

    expect((array) DB::table('users')->where('name', 'Jane Doe')->first(['company_name', 'is_important', 'is_rep', 'synched_with_otajer', 'activity_status', 'profits', 'raizer_admin']))->toBe([
        'company_name' => '',
        'is_important' => 1,
        'is_rep' => 0,
        'synched_with_otajer' => 0,
        'activity_status' => 'new_user',
        'profits' => 0.0,
        'raizer_admin' => null,
    ]);
});

test('activity_status only takes the two legacy values', function () {
    expect(fn () => DB::table('users')->insert(['name' => 'Jane Doe', 'activity_status' => 'archived']))
        ->toThrow(QueryException::class);

    DB::table('users')->insert(['name' => 'John Doe', 'activity_status' => 'has_been_contacted']);

    expect(DB::table('users')->where('name', 'John Doe')->value('activity_status'))->toBe('has_been_contacted');
});

test('a cart row gets the legacy defaults, home delivery among them', function () {
    DB::table('carts')->insert(['user_id' => 1, 'product_id' => 1, 'quantity' => 1]);

    expect((array) DB::table('carts')->first(['shipping_type', 'by_rep', 'rep_price', 'is_price_changed', 'is_admin_notified']))->toEqual([
        'shipping_type' => 'home_delivery',
        'by_rep' => 0,
        'rep_price' => 0,
        'is_price_changed' => 0,
        'is_admin_notified' => 0,
    ]);
});

/**
 * Cart uses SoftDeletes, and signing out deletes the user's cart: without
 * `deleted_at` every logout 500'd.
 */
test('deleting a cart soft-deletes it and leaves the other carts alone', function () {
    DB::table('carts')->insert(['user_id' => 1, 'product_id' => 1, 'quantity' => 1]);
    DB::table('carts')->insert(['user_id' => 2, 'product_id' => 1, 'quantity' => 1]);

    Cart::where('user_id', 1)->delete();

    expect(Cart::pluck('user_id')->all())->toBe([2])
        ->and(DB::table('carts')->where('user_id', 1)->value('deleted_at'))->not->toBeNull();
});

/**
 * The only writer of `products` is `products:sync`, which sends `name`,
 * `ar_name` and `published`: every store column has to default from that.
 */
test('a product written the way products:sync writes one gets the store defaults', function () {
    DB::table('products')->insert(['id' => 100027, 'name' => 'Widgets', 'ar_name' => 'ودجات', 'published' => 1]);

    expect((array) DB::table('products')->where('id', 100027)->first([
        'added_by', 'user_id', 'unit_price', 'todays_deal', 'approved', 'auction_product', 'isNewProduct', 'min_qty',
        'max_qty', 'current_stock', 'shipping_type', 'attributes', 'slug', 'digital', 'for_sale', 'deleted_from_api',
    ]))->toEqual([
        'added_by' => 'admin',
        'user_id' => null,
        'unit_price' => 0,
        'todays_deal' => 0,
        'approved' => 1,
        'auction_product' => 0,
        'isNewProduct' => null,
        'min_qty' => 1,
        'max_qty' => 1000,
        'current_stock' => 0,
        'shipping_type' => 'flat_rate',
        'attributes' => '[]',
        'slug' => null,
        'digital' => 0,
        'for_sale' => 1,
        'deleted_from_api' => 0,
    ]);
});

test('an order detail row gets the legacy defaults', function () {
    DB::table('order_details')->insert(['order_id' => 1, 'product_id' => 1, 'quantity' => 1]);

    expect((array) DB::table('order_details')->first(['original_price', 'is_price_changed', 'profits']))->toEqual([
        'original_price' => 0,
        'is_price_changed' => 0,
        'profits' => 0,
    ]);
});

test('an address may leave its state empty', function () {
    DB::table('addresses')->insert(['user_id' => 1, 'address' => 'Street 1', 'state_id' => null]);

    expect(DB::table('addresses')->where('user_id', 1)->value('state_id'))->toBeNull();
});

test('a product stock may leave its variant empty', function () {
    DB::table('product_stocks')->insert(['product_id' => 1, 'variant' => null, 'price' => 1, 'qty' => 1]);

    expect(DB::table('product_stocks')->where('product_id', 1)->value('variant'))->toBeNull();
});

/**
 * The production case: the store's own database already has every column.
 */
test('running the migration again leaves the columns, and their data, alone', function () {
    DB::table('users')->insert(['name' => 'Jane Doe', 'company_name' => 'Acme', 'raizer_admin' => true]);

    loadAddLegacyColumnsToStoreTablesMigration()->up();

    expect((array) DB::table('users')->where('name', 'Jane Doe')->first(['company_name', 'raizer_admin']))->toBe([
        'company_name' => 'Acme',
        'raizer_admin' => 1,
    ]);
});

test('rolling the migration back removes the added columns and restores the changed ones', function () {
    $migration = loadAddLegacyColumnsToStoreTablesMigration();

    $migration->down();

    expect(Schema::hasColumn('users', 'raizer_admin'))->toBeFalse()
        ->and(Schema::hasColumn('carts', 'deleted_at'))->toBeFalse()
        ->and(Schema::hasColumn('order_details', 'profits'))->toBeFalse()
        ->and(fn () => DB::table('addresses')->insert(['user_id' => 1, 'address' => 'Street 1', 'state_id' => null]))
        ->toThrow(QueryException::class);

    $migration->up();

    expect(Schema::hasColumn('users', 'raizer_admin'))->toBeTrue();
});
