<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function paymentTransactionCodeMigration(): object
{
    return require database_path('migrations/2026_10_24_161838_widen_payment_transaction_code.php');
}

test('the migrated payment column keeps 191 character references and null values', function () {
    DB::table('payments')->insert([
        ['seller_id' => 1, 'amount' => 40, 'txn_code' => str_repeat('ت', 191)],
        ['seller_id' => 2, 'amount' => 20, 'txn_code' => null],
    ]);
    $migration = paymentTransactionCodeMigration();
    $migration->up();
    $migration->up();
    $migration->down();
    expect(DB::table('payments')->where('seller_id', 1)->value('txn_code'))->toBe(str_repeat('ت', 191))
        ->and(DB::table('payments')->where('seller_id', 2)->value('txn_code'))->toBeNull();
    $column = collect(Schema::getColumns('payments'))->firstWhere('name', 'txn_code');
    expect($column['nullable'])->toBeTrue();
    if (DB::connection()->getDriverName() === 'mysql') {
        expect($column['type'])->toBe('varchar(191)');
    }
});

test('upgrading the old transaction column preserves rows and never narrows a wider legacy column', function () {
    $mysql = DB::connection()->getDriverName() === 'mysql';
    if ($mysql) {
        Schema::table('payments', fn (Blueprint $table) => $table->string('txn_code', 100)->nullable()->change());
    }
    try {
        DB::table('payments')->insert(['seller_id' => 1, 'amount' => 40, 'txn_code' => 'OLD-REFERENCE']);
        $migration = paymentTransactionCodeMigration();
        $migration->up();
        expect(DB::table('payments')->where('seller_id', 1)->value('txn_code'))->toBe('OLD-REFERENCE');
        DB::table('payments')->where('seller_id', 1)->update(['txn_code' => str_repeat('a', 191)]);
        expect(DB::table('payments')->where('seller_id', 1)->value('txn_code'))->toBe(str_repeat('a', 191));
        if ($mysql) {
            Schema::table('payments', fn (Blueprint $table) => $table->string('txn_code', 255)->nullable()->change());
            $migration->up();
            $column = collect(Schema::getColumns('payments'))->firstWhere('name', 'txn_code');
            expect($column['type'])->toBe('varchar(255)');
        }
    } finally {
        if ($mysql) {
            DB::table('payments')->delete();
            Schema::table('payments', fn (Blueprint $table) => $table->string('txn_code', 191)->nullable()->change());
        }
    }
});
