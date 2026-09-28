<?php

use Illuminate\Support\Facades\Schema;

test('coupons table has the expected columns', function () {
    expect(Schema::hasTable('coupons'))->toBeTrue();
    expect(Schema::hasColumns('coupons', [
        'id', 'user_id', 'type', 'code', 'details', 'discount', 'discount_type',
        'start_date', 'end_date', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
