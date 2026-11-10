<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('wallet rows default to a non-offline, unapproved recharge', function () {
    expect(Schema::hasColumns('wallets', ['offline_payment', 'approval']))->toBeTrue();

    $id = DB::table('wallets')->insertGetId(['user_id' => 1, 'amount' => 10]);
    $noise = DB::table('wallets')->insertGetId(['user_id' => 2, 'amount' => 5, 'offline_payment' => 1, 'approval' => 1]);

    expect((int) DB::table('wallets')->where('id', $id)->value('offline_payment'))->toBe(0)
        ->and((int) DB::table('wallets')->where('id', $id)->value('approval'))->toBe(0)
        ->and((int) DB::table('wallets')->where('id', $noise)->value('approval'))->toBe(1);
});

test('re-running the migration keeps existing columns and their data', function () {
    $id = DB::table('wallets')->insertGetId(['user_id' => 1, 'amount' => 10, 'offline_payment' => 1, 'approval' => 1]);

    (require database_path('migrations/2026_11_10_000000_add_offline_payment_and_approval_to_wallets_table.php'))->up();

    expect((int) DB::table('wallets')->where('id', $id)->value('offline_payment'))->toBe(1)
        ->and((int) DB::table('wallets')->where('id', $id)->value('approval'))->toBe(1);
});

test('the migration adds only the missing column and drops both on rollback', function () {
    Schema::table('wallets', fn (Blueprint $table) => $table->dropColumn('approval'));
    expect(Schema::hasColumn('wallets', 'approval'))->toBeFalse();

    $migration = require database_path('migrations/2026_11_10_000000_add_offline_payment_and_approval_to_wallets_table.php');
    $migration->up();

    expect(Schema::hasColumns('wallets', ['offline_payment', 'approval']))->toBeTrue();

    $migration->down();

    expect(Schema::hasColumn('wallets', 'offline_payment'))->toBeFalse()
        ->and(Schema::hasColumn('wallets', 'approval'))->toBeFalse();
});
