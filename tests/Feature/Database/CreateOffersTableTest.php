<?php

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadCreateOffersTableMigration(): object
{
    return require database_path('migrations/2026_09_30_000006_create_offers_table.php');
}

test('the migration leaves an existing offers table alone', function () {
    // The store's own database already has this table, with its own data.
    Schema::dropIfExists('offers');
    Schema::create('offers', function (Blueprint $table) {
        $table->string('store_only_column')->nullable();
    });

    loadCreateOffersTableMigration()->up();

    expect(Schema::hasColumn('offers', 'store_only_column'))->toBeTrue()
        ->and(Schema::hasColumn('offers', 'title'))->toBeFalse();
});

test('the migration creates the expected columns', function () {
    expect(Schema::hasColumns('offers', [
        'id', 'title', 'start_date', 'end_date', 'is_active', 'multiple_usage', 'is_repeatable', 'banner',
        'created_at', 'updated_at', 'offer_type', 'brand_id',
    ]))->toBeTrue();
});

test('a offers row gets the legacy defaults', function () {
    DB::table('offers')->insert(['title' => 'Summer']);

    expect((array) DB::table('offers')->first(['is_active', 'multiple_usage', 'is_repeatable', 'offer_type']))->toEqual([
        'is_active' => 1,
        'multiple_usage' => 0,
        'is_repeatable' => 0,
        'offer_type' => 'products_offer',
    ]);
});

test('an offer is either a brand offer or a products offer', function () {
    expect(fn () => DB::table('offers')->insert(['title' => 'Summer', 'offer_type' => 'category_offer']))
        ->toThrow(QueryException::class);
});

test('the migration skips dropping the offers table in production', function () {
    app()->instance('env', 'production');
    loadCreateOffersTableMigration()->down();
    app()->instance('env', 'testing');

    expect(Schema::hasTable('offers'))->toBeTrue();
});
