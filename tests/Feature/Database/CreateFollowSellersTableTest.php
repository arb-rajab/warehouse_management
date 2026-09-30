<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

function loadCreateFollowSellersTableMigration(): object
{
    return require database_path('migrations/2026_09_30_000018_create_follow_sellers_table.php');
}

test('the migration leaves an existing follow_sellers table alone', function () {
    // The store's own database already has this table, with its own data.
    Schema::dropIfExists('follow_sellers');
    Schema::create('follow_sellers', function (Blueprint $table) {
        $table->string('store_only_column')->nullable();
    });

    loadCreateFollowSellersTableMigration()->up();

    expect(Schema::hasColumn('follow_sellers', 'store_only_column'))->toBeTrue()
        ->and(Schema::hasColumn('follow_sellers', 'user_id'))->toBeFalse();
});

test('the migration creates the expected columns', function () {
    expect(Schema::hasColumns('follow_sellers', [
        'user_id', 'shop_id',
    ]))->toBeTrue();
});

test('the migration skips dropping the follow_sellers table in production', function () {
    app()->instance('env', 'production');
    loadCreateFollowSellersTableMigration()->down();
    app()->instance('env', 'testing');

    expect(Schema::hasTable('follow_sellers'))->toBeTrue();
});
