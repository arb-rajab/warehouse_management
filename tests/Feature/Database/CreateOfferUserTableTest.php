<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadCreateOfferUserTableMigration(): object
{
    return require database_path('migrations/2026_09_30_000008_create_offer_user_table.php');
}

test('the migration leaves an existing offer_user table alone', function () {
    // The store's own database already has this table, with its own data.
    Schema::dropIfExists('offer_user');
    Schema::create('offer_user', function (Blueprint $table) {
        $table->string('store_only_column')->nullable();
    });

    loadCreateOfferUserTableMigration()->up();

    expect(Schema::hasColumn('offer_user', 'store_only_column'))->toBeTrue()
        ->and(Schema::hasColumn('offer_user', 'offer_id'))->toBeFalse();
});

test('the migration creates the expected columns', function () {
    expect(Schema::hasColumns('offer_user', [
        'id', 'offer_id', 'user_id', 'is_ignored', 'is_postponed', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

test('a offer_user row gets the legacy defaults', function () {
    DB::table('offer_user')->insert(['offer_id' => 1, 'user_id' => 1]);

    expect((array) DB::table('offer_user')->first(['is_ignored', 'is_postponed']))->toEqual([
        'is_ignored' => 0,
        'is_postponed' => 0,
    ]);
});

test('the migration skips dropping the offer_user table in production', function () {
    app()->instance('env', 'production');
    loadCreateOfferUserTableMigration()->down();
    app()->instance('env', 'testing');

    expect(Schema::hasTable('offer_user'))->toBeTrue();
});
