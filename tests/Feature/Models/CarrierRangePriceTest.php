<?php

use App\Models\CarrierRangePrice;
use Illuminate\Support\Facades\DB;

test('carrier price resolves the exact carrier range and zone foreign keys', function () {
    $fixture = createStoreCarrierTariff();
    $price = CarrierRangePrice::query()->orderBy('id')->firstOrFail();
    DB::table('carrier_ranges')->insert(['carrier_id' => 999, 'billing_type' => 'price_based', 'delimiter1' => 0, 'delimiter2' => 100]);
    expect($price->carrier->id)->toBe($fixture['carrier']->id)
        ->and($price->carrier_ranges->id)->toBe($price->carrier_range_id)
        ->and($price->zone->id)->toBe($fixture['zone']);
});
