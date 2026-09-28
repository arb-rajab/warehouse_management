<?php

use Illuminate\Support\Facades\Schema;

test('commission_histories has the expected columns', function () {
    expect(Schema::hasTable('commission_histories'))->toBeTrue();
    expect(Schema::hasColumns('commission_histories', [
        'id', 'order_id', 'order_detail_id', 'seller_id', 'admin_commission', 'seller_earning', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
