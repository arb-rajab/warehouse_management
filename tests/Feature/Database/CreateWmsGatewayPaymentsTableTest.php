<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function insertGatewayPayment(string $gateway, string $reference): void
{
    DB::table('wms_gateway_payments')->insert([
        'gateway' => $gateway, 'reference' => $reference, 'user_id' => 1,
        'payment_type' => 'cart_payment', 'amount' => 10, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

test('wms_gateway_payments has the expected columns', function () {
    expect(Schema::hasColumns('wms_gateway_payments', [
        'id', 'gateway', 'reference', 'user_id', 'payment_type', 'combined_order_id', 'package_id',
        'amount', 'completed_at', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

test('a gateway reference can be recorded only once', function () {
    insertGatewayPayment('payhere', 'PH-1');

    expect(fn () => insertGatewayPayment('payhere', 'PH-1'))->toThrow(QueryException::class);
});

test('the same reference at another gateway is a different payment', function () {
    insertGatewayPayment('payhere', 'REF-1');
    insertGatewayPayment('bkash', 'REF-1');

    expect(DB::table('wms_gateway_payments')->where('reference', 'REF-1')->count())->toBe(2);
});
