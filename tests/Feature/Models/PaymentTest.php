<?php

use App\Models\Payment;
use Illuminate\Support\Facades\DB;

test('payment seller relation resolves only the recorded store account', function () {
    $seller = createStoreUser('seller');
    $noise = createStoreUser('seller');
    $id = DB::table('payments')->insertGetId(['seller_id' => $seller->id, 'amount' => 10]);
    $other = DB::table('payments')->insertGetId(['seller_id' => $noise->id, 'amount' => 20]);
    expect(Payment::findOrFail($id)->seller->is($seller))->toBeTrue()
        ->and(Payment::findOrFail($other)->seller->is($noise))->toBeTrue();
});
