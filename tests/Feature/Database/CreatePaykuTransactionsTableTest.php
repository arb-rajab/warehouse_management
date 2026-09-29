<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('payku_transactions has the expected columns', function () {
    expect(Schema::hasTable('payku_transactions'))->toBeTrue();
    expect(Schema::hasColumns('payku_transactions', [
        'id', 'status', 'order', 'email', 'subject', 'url', 'amount', 'notified_at', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('payku_transactions')->insert([
        'id' => 'txn-1',
        'order' => 'order-1',
    ]);

    $this->assertDatabaseHas('payku_transactions', [
        'id' => 'txn-1',
        'order' => 'order-1',
    ]);
});

test('order must be unique', function () {
    DB::table('payku_transactions')->insert([
        'id' => 'txn-1',
        'order' => 'order-1',
    ]);

    expect(fn () => DB::table('payku_transactions')->insert([
        'id' => 'txn-2',
        'order' => 'order-1',
    ]))->toThrow(QueryException::class);
});
