<?php

use App\Models\Carrier;
use App\Models\Cart;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

// Exercise the unchanged shared shipping calculation against migrated writes.
test('migrated carrier tariffs preserve inclusive lower and exclusive upper boundaries by zone and billing mode', function (string $mode, float $amount, int $zoneIndex, float $expected) {
    $fixture = createStoreCarrierTariff();
    actingAsStoreUser(createStoreStaff('manage_carriers'));
    $this->put('/temp/admin/carriers/'.$fixture['carrier']->id, [...$fixture['payload'], 'billing_type' => $mode])->assertRedirect();
    DB::table('business_settings')->insert(['type' => 'shipping_type', 'value' => 'carrier_wise_shipping']);
    $country = DB::table('countries')->insertGetId(['name' => 'Test country', 'code' => 'TC', 'zone_id' => $zoneIndex === 0 ? $fixture['zone'] : $fixture['otherZone']]);
    $address = DB::table('addresses')->insertGetId(['user_id' => 1, 'address' => 'Street', 'country_id' => $country, 'state_id' => 0]);
    $product = createStoreProduct(['added_by' => 'admin', 'digital' => 0, 'auction_product' => 0, 'weight' => $amount, 'unit_price' => $amount, 'discount' => 0]);
    $cart = Cart::forceCreate(['user_id' => 1, 'owner_id' => 1, 'product_id' => $product->id, 'address_id' => $address, 'quantity' => 1, 'variation' => '']);
    // The retained helper traverses relations lazily, as production permits.
    $strict = Model::preventsLazyLoading();
    Model::preventLazyLoading(false);
    try {
        expect((float) getShippingCost(collect([$cart]), 0, $fixture['carrier']->id))->toBe($expected);
    } finally {
        Model::preventLazyLoading($strict);
    }
})->with([
    ['weight_based', 0.0, 0, 5.0], ['weight_based', 9.99, 0, 5.0], ['weight_based', 10.0, 0, 8.0], ['weight_based', 20.0, 0, 0.0],
    ['price_based', 9.99, 1, 7.0], ['price_based', 10.0, 1, 9.0], ['price_based', 20.0, 1, 0.0],
]);

test('migrated free shipping remains active without zone prices', function () {
    $fixture = createStoreCarrierTariff();
    actingAsStoreUser(createStoreStaff('manage_carriers'));
    $this->put('/temp/admin/carriers/'.$fixture['carrier']->id, [...$fixture['payload'], 'shipping_type' => 1])->assertRedirect();
    expect(Carrier::query()->active()->where('free_shipping', 1)->pluck('id')->all())->toBe([$fixture['carrier']->id]);
    $this->assertDatabaseMissing('carrier_range_prices', ['carrier_id' => $fixture['carrier']->id]);
});
