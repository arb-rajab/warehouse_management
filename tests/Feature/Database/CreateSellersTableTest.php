<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the sellers table has the expected columns', function () {
    expect(Schema::hasTable('sellers'))->toBeTrue();
    expect(Schema::hasColumns('sellers', [
        'id', 'user_id', 'rating', 'num_of_reviews', 'num_of_sale', 'verification_status',
        'verification_info', 'cash_on_delivery_status', 'admin_to_pay', 'bank_name',
        'bank_acc_name', 'bank_acc_no', 'bank_routing_no', 'bank_payment_status',
        'created_at', 'updated_at',
    ]))->toBeTrue();
});

test('user_id must be unique across sellers', function () {
    DB::table('sellers')->insert([
        'user_id' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('sellers')->insert([
        'user_id' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
