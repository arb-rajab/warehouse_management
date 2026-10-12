<?php

use App\Models\Carrier;
use Illuminate\Support\Facades\DB;

test('carrier relations and active scope exclude unrelated tariffs and inactive carriers', function () {
    $fixture = createStoreCarrierTariff();
    $other = DB::table('carriers')->insertGetId(['name' => 'Other', 'transit_time' => '1', 'status' => 0]);
    $range = DB::table('carrier_ranges')->insertGetId(['carrier_id' => $other, 'billing_type' => 'price_based', 'delimiter1' => 0, 'delimiter2' => 10]);
    DB::table('carrier_range_prices')->insert(['carrier_id' => $other, 'carrier_range_id' => $range, 'zone_id' => $fixture['zone'], 'price' => 100]);
    expect($fixture['carrier']->carrier_ranges->pluck('carrier_id')->unique()->all())->toBe([$fixture['carrier']->id])
        ->and($fixture['carrier']->carrier_ranges)->toHaveCount(2)
        ->and($fixture['carrier']->carrier_range_prices->pluck('price')->map(fn ($value) => (float) $value)->all())->toBe([5.0, 7.0, 8.0, 9.0])
        ->and(Carrier::query()->active()->pluck('id')->all())->toBe([$fixture['carrier']->id]);
});
