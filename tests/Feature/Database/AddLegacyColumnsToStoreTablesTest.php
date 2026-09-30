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
        ->and(Schema::hasColumn('categories', 'serial'))->toBeTrue()
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
