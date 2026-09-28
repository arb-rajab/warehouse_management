<?php

use Illuminate\Support\Facades\Schema;

test('coupon_usages table has the expected columns', function () {
    expect(Schema::hasTable('coupon_usages'))->toBeTrue();
    expect(Schema::hasColumns('coupon_usages', [
        'id', 'user_id', 'coupon_id', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
