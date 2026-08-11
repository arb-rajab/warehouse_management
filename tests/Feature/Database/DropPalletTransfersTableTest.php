<?php

use Illuminate\Support\Facades\Schema;

test('the pallet_transfers table was dropped', function () {
    expect(Schema::hasTable('pallet_transfers'))->toBeFalse();
});
