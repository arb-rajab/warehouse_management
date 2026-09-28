<?php

use Illuminate\Support\Facades\Schema;

test('the seller_withdraw_requests table has the expected columns', function () {
    expect(Schema::hasTable('seller_withdraw_requests'))->toBeTrue();
    expect(Schema::hasColumns('seller_withdraw_requests', [
        'id', 'user_id', 'amount', 'message', 'status', 'viewed', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
