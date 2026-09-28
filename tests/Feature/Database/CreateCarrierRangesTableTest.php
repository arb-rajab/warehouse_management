<?php

use Illuminate\Support\Facades\Schema;

test('carrier_ranges has the expected columns', function () {
    expect(Schema::hasTable('carrier_ranges'))->toBeTrue();
    expect(Schema::hasColumns('carrier_ranges', [
        'id', 'carrier_id', 'billing_type', 'delimiter1', 'delimiter2', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
