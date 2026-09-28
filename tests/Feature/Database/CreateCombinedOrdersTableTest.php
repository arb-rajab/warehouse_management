<?php

use Illuminate\Support\Facades\Schema;

test('combined_orders has the expected columns', function () {
    expect(Schema::hasTable('combined_orders'))->toBeTrue();
    expect(Schema::hasColumns('combined_orders', [
        'id', 'user_id', 'shipping_address', 'grand_total', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
