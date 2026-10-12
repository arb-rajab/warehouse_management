<?php

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadAddUniqueUserCouponIndexMigration(): object
{
    return require database_path('migrations/2026_10_03_000004_add_unique_user_coupon_index_to_coupon_usages_table.php');
}

test('a customer cannot use the same coupon twice, while other pairs are unaffected', function () {
    DB::table('coupon_usages')->insert(['user_id' => 1, 'coupon_id' => 1]);
    DB::table('coupon_usages')->insert(['user_id' => 1, 'coupon_id' => 2]);
    DB::table('coupon_usages')->insert(['user_id' => 2, 'coupon_id' => 1]);

    expect(fn () => DB::table('coupon_usages')->insert(['user_id' => 1, 'coupon_id' => 1]))->toThrow(QueryException::class)
        ->and(DB::table('coupon_usages')->count())->toBe(3);
});

test('rows already used twice are collapsed to the oldest before the index is built', function () {
    Schema::table('coupon_usages', function (Blueprint $table) {
        $table->dropUnique(['user_id', 'coupon_id']);
    });
    $first = DB::table('coupon_usages')->insertGetId(['user_id' => 1, 'coupon_id' => 1]);
    DB::table('coupon_usages')->insert(['user_id' => 1, 'coupon_id' => 1]);
    $noise = DB::table('coupon_usages')->insertGetId(['user_id' => 2, 'coupon_id' => 1]);

    loadAddUniqueUserCouponIndexMigration()->up();

    expect(DB::table('coupon_usages')->orderBy('id')->pluck('id')->all())->toBe([$first, $noise])
        ->and(Schema::hasIndex('coupon_usages', ['user_id', 'coupon_id'], 'unique'))->toBeTrue();
});

test('running the migration again leaves an existing index alone', function () {
    loadAddUniqueUserCouponIndexMigration()->up();

    expect(Schema::hasIndex('coupon_usages', ['user_id', 'coupon_id'], 'unique'))->toBeTrue();
});

test('rolling the migration back removes the index', function () {
    loadAddUniqueUserCouponIndexMigration()->down();

    expect(Schema::hasIndex('coupon_usages', ['user_id', 'coupon_id'], 'unique'))->toBeFalse();

    DB::table('coupon_usages')->insert(['user_id' => 1, 'coupon_id' => 1]);
    DB::table('coupon_usages')->insert(['user_id' => 1, 'coupon_id' => 1]);

    expect(DB::table('coupon_usages')->count())->toBe(2);
});
