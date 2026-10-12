<?php

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadCreateRepresentativePaymentsTableMigration(): object
{
    return require database_path('migrations/2026_09_30_000004_create_representative_payments_table.php');
}

test('the migration leaves an existing representative_payments table alone', function () {
    // The store's own database already has this table, with its own data.
    Schema::dropIfExists('representative_payments');
    Schema::create('representative_payments', function (Blueprint $table) {
        $table->string('store_only_column')->nullable();
    });

    loadCreateRepresentativePaymentsTableMigration()->up();

    expect(Schema::hasColumn('representative_payments', 'store_only_column'))->toBeTrue()
        ->and(Schema::hasColumn('representative_payments', 'amount'))->toBeFalse();
});

test('the migration creates the expected columns', function () {
    expect(Schema::hasColumns('representative_payments', [
        'id', 'amount', 'receipt_code', 'invoice_code', 'customer_id', 'rep_id', 'admin_id', 'date', 'otajer_id',
        'customer_name', 'notes', 'status', 'url', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

test('a representative_payments row gets the legacy defaults', function () {
    DB::table('representative_payments')->insert(['id' => 'RP-1', 'receipt_code' => 'R-1', 'rep_id' => 1]);

    expect((array) DB::table('representative_payments')->first(['amount', 'status']))->toEqual([
        'amount' => 0,
        'status' => 'pending',
    ]);
});

test('a representative payment id is unique', function () {
    DB::table('representative_payments')->insert(['id' => 'RP-1', 'receipt_code' => 'R-1', 'rep_id' => 1]);

    expect(fn () => DB::table('representative_payments')->insert(['id' => 'RP-1', 'receipt_code' => 'R-2', 'rep_id' => 1]))
        ->toThrow(QueryException::class);
});

test('a representative payment status is accepted, rejected or pending', function () {
    expect(fn () => DB::table('representative_payments')->insert(['id' => 'RP-1', 'receipt_code' => 'R-1', 'rep_id' => 1, 'status' => 'refunded']))
        ->toThrow(QueryException::class);
});

test('the migration skips dropping the representative_payments table in production', function () {
    app()->instance('env', 'production');
    loadCreateRepresentativePaymentsTableMigration()->down();
    app()->instance('env', 'testing');

    expect(Schema::hasTable('representative_payments'))->toBeTrue();
});
