<?php

use App\Models\RepresentativePayment;
use Illuminate\Support\Facades\DB;

test('representative payments preserve string receipt keys and resolve only their own related accounts', function () {
    $rep = createStoreUser('customer');
    $customer = createStoreUser('customer');
    $admin = createStoreUser('staff');
    $noise = createStoreUser('customer');
    DB::table('representative_payments')->insert([
        ['id' => 'receipt-001', 'rep_id' => $rep->id, 'customer_id' => $customer->id, 'admin_id' => $admin->id, 'receipt_code' => 'R-1', 'amount' => '25.50'],
        ['id' => 'receipt-002', 'rep_id' => $noise->id, 'customer_id' => $noise->id, 'admin_id' => $noise->id, 'receipt_code' => 'R-2', 'amount' => '75.50'],
    ]);
    $payment = RepresentativePayment::findOrFail('receipt-001');
    expect($payment->getIncrementing())->toBeFalse()
        ->and($payment->id)->toBe('receipt-001')
        ->and($payment->rep->id)->toBe($rep->id)->and($payment->customer->id)->toBe($customer->id)->and($payment->admin->id)->toBe($admin->id);
    expect(RepresentativePayment::findOrFail('receipt-002')->rep->id)->toBe($noise->id);
});

test('a payment created without an id gets a unique string id', function () {
    $first = RepresentativePayment::create(['rep_id' => 1, 'receipt_code' => 'R-A', 'amount' => 5]);
    $second = RepresentativePayment::create(['rep_id' => 1, 'receipt_code' => 'R-B', 'amount' => 5]);

    expect($first->id)->toBeString()->not->toBe('')
        ->and($second->id)->not->toBe($first->id)
        ->and(RepresentativePayment::find($first->id)?->receipt_code)->toBe('R-A');
});
