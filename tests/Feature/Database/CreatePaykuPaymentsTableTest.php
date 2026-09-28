<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('payku_payments has the expected columns', function () {
    expect(Schema::hasTable('payku_payments'))->toBeTrue();
    expect(Schema::hasColumns('payku_payments', [
        'transaction_id', 'start', 'end', 'media', 'transaction_key', 'payment_key',
        'verification_key', 'authorization_code', 'last_4_digits', 'installments',
        'card_type', 'additional_parameters', 'currency', 'created_at', 'updated_at',
    ]))->toBeTrue();
    expect(Schema::hasColumn('payku_payments', 'id'))->toBeFalse();

    DB::table('payku_transactions')->insert(['id' => 'txn-1']);

    DB::table('payku_payments')->insert([
        'transaction_id' => 'txn-1',
        'start' => '2026-01-01',
        'end' => '2026-01-02',
        'media' => 'webpay',
        'verification_key' => 'vkey',
        'authorization_code' => 'auth-code',
        'currency' => 'CLP',
    ]);

    $this->assertDatabaseHas('payku_payments', [
        'transaction_id' => 'txn-1',
        'media' => 'webpay',
    ]);
});

test('deleting a referenced payku_transactions row is restricted while a payku_payments row references it', function () {
    DB::table('payku_transactions')->insert(['id' => 'txn-1']);

    DB::table('payku_payments')->insert([
        'transaction_id' => 'txn-1',
        'start' => '2026-01-01',
        'end' => '2026-01-02',
        'media' => 'webpay',
        'verification_key' => 'vkey',
        'authorization_code' => 'auth-code',
        'currency' => 'CLP',
    ]);

    expect(fn () => DB::table('payku_transactions')->where('id', 'txn-1')->delete())
        ->toThrow(QueryException::class);

    expect(DB::table('payku_transactions')->where('id', 'txn-1')->exists())->toBeTrue();
});
