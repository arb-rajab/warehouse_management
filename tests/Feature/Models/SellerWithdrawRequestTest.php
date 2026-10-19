<?php

use App\Models\SellerWithdrawRequest;

test('withdrawal user relation resolves only its requesting seller', function () {
    $seller = createStoreUser('seller');
    $other = createStoreUser('seller');
    $id = createWithdrawRequestFor($seller, 10);
    $noise = createWithdrawRequestFor($other, 20);
    expect(SellerWithdrawRequest::findOrFail($id)->user->is($seller))->toBeTrue()
        ->and(SellerWithdrawRequest::findOrFail($noise)->user->is($other))->toBeTrue();
});
