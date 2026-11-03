<?php

use App\Models\Carrier;
use App\Models\Country;
use App\Models\StoreUser;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;

/**
 * GET delivery-info listed no carrier on dev because no carrier had a price for
 * the zone of the customer's country, and no minimum order was configured.
 */
function dutchCustomerWithCart(): StoreUser
{
    $customer = createStoreUser('customer');
    $country = Country::query()->where('name', 'Netherlands')->first();
    $address = addStoreAddress($customer->id);
    DB::table('addresses')->where('id', $address)->update(['country_id' => $country->id]);
    cartLine($customer, createStoreUser('seller'), 2)->update(['address_id' => $address]);

    return $customer;
}

beforeEach(function () {
    seedStoreCurrency();
    DB::table('countries')->insert(['code' => 'NL', 'name' => 'Netherlands', 'zone_id' => 0]);
    DB::table('countries')->insert(['code' => 'BE', 'name' => 'Belgium', 'zone_id' => 0]);
});

test('the command gives the country a carrier that delivery-info then lists', function () {
    $customer = dutchCustomerWithCart();
    $before = $this->withToken(storeApiToken($customer))->getJson('/api/v1/store/delivery-info')->assertOk()->json('0');
    expect($before['carriers']['data'])->toBe([]);

    $this->artisan('store:setup-delivery', ['--carrier' => 'Test carrier', '--price' => 6, '--transit-days' => 3])->assertSuccessful();

    app('auth')->forgetGuards();
    $after = $this->withToken(storeApiToken($customer))->getJson('/api/v1/store/delivery-info')->assertOk()->json('0');
    expect($after['carriers']['data'])->toHaveCount(1)
        ->and($after['carriers']['data'][0])->toMatchArray(['name' => 'Test carrier', 'transit_time' => 3, 'free_shipping' => false])
        ->and(Zone::where('name', 'Netherlands')->count())->toBe(1)
        ->and((int) Country::where('name', 'Belgium')->value('zone_id'))->toBe(0);
});

test('running the command again changes the price and adds no second carrier', function () {
    $this->artisan('store:setup-delivery', ['--price' => 6])->assertSuccessful();
    $this->artisan('store:setup-delivery', ['--price' => 9])->assertSuccessful();

    expect(Carrier::count())->toBe(1)
        ->and(DB::table('carrier_ranges')->count())->toBe(1)
        ->and(DB::table('carrier_range_prices')->pluck('price')->map(fn ($price) => (float) $price)->all())->toBe([9.0]);
});

test('the command sets the minimum order and the fee product that delivery-info reports', function () {
    $customer = dutchCustomerWithCart();
    $fee = createStoreProduct(['unit_price' => 7]);

    $this->artisan('store:setup-delivery', ['--minimum' => 50, '--fee-mat-id' => (string) $fee->id])->assertSuccessful();

    $shop = $this->withToken(storeApiToken($customer))->getJson('/api/v1/store/delivery-info')->assertOk()->json('0');
    expect($shop['minimum_order_amount'])->toBe(50)->and((float) $shop['default_delivery_fees'])->toBe(7.0)->and(DB::table('business_settings')->where('type', 'default_specific_product')->value('value'))->toBe((string) $fee->id);
});

test('the command refuses an unknown country, an unknown fee product and a bad minimum, and writes nothing', function (array $options, string $message) {
    $this->artisan('store:setup-delivery', $options)->expectsOutputToContain($message)->assertFailed();

    expect(Carrier::count())->toBe(0)->and(DB::table('business_settings')->whereIn('type', ['minimum_order_amount_check', 'default_specific_product'])->count())->toBe(0);
})->with([
    'unknown country' => [['--country' => 'Atlantis'], 'no country named'],
    'unknown fee product' => [['--fee-mat-id' => 'NOPE'], 'no product with id'],
    'bad minimum' => [['--minimum' => 'lots'], '--minimum must be a number'],
]);
