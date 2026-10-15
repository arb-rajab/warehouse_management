<?php

use App\Models\CarrierRange;
use Illuminate\Support\Facades\DB;

test('carrier range resolves its carrier and only its own zone prices', function () {
    $fixture = createStoreCarrierTariff();
    $range = CarrierRange::query()->orderBy('id')->firstOrFail();
    $other = DB::table('carriers')->insertGetId(['name' => 'Noise', 'transit_time' => '9']);
    DB::table('carrier_ranges')->insert(['carrier_id' => $other, 'billing_type' => 'price_based', 'delimiter1' => 0, 'delimiter2' => 99]);
    expect($range->carrier->id)->toBe($fixture['carrier']->id)
        ->and($range->carrier_range_prices->pluck('price')->map(fn ($value) => (float) $value)->all())->toBe([5.0, 7.0]);
});
