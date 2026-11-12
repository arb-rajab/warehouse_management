<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('wms_gateway_payments carries the converted amount, its currency and an expiry', function () {
    expect(Schema::hasColumns('wms_gateway_payments', ['gateway_amount', 'gateway_currency', 'expires_at']))->toBeTrue();
});

test('the new columns are optional, so the API\'s pending rows and older rows need none', function () {
    DB::table('wms_gateway_payments')->insert([
        'gateway' => 'sslcommerz', 'reference' => 'AIZ-1', 'user_id' => 1, 'payment_type' => 'cart_payment',
        'amount' => 10, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $row = DB::table('wms_gateway_payments')->where('reference', 'AIZ-1')->first();

    expect($row->gateway_amount)->toBeNull()
        ->and($row->gateway_currency)->toBeNull()
        ->and($row->expires_at)->toBeNull();
});

test('the converted amount keeps its cents', function () {
    DB::table('wms_gateway_payments')->insert([
        'gateway' => 'aamarpay', 'reference' => 'WEB-1', 'user_id' => 1, 'payment_type' => 'wallet_payment',
        'amount' => 24.6, 'gateway_amount' => 3210.3, 'gateway_currency' => 'BDT', 'expires_at' => now()->addHours(3),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect((float) DB::table('wms_gateway_payments')->where('reference', 'WEB-1')->value('gateway_amount'))->toBe(3210.3);
});

test('pending rows are indexed for the expiry prune', function () {
    $indexed = collect(Schema::getIndexes('wms_gateway_payments'))->pluck('columns')->all();

    expect($indexed)->toContain(['completed_at', 'expires_at']);
});
