<?php

use App\Models\Address;
use App\Models\StoreUser;
use App\Support\DefaultMapLocation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Where the Google Maps pin starts on the address forms (the storefront and
 * seller-panel address modals) and on the seller's shop settings (the
 * delivery pick-up point): the default location the admin sets on the Google
 * Map settings page, else Amsterdam. It started in Sydney, whose longitude the
 * address columns could not hold before 2026_10_21_110000.
 */
function mapDefaultLocationScript(string $html): ?array
{
    if (preg_match('/var defaultMapLocation = (\{[^}]*\});/', $html, $match) !== 1) {
        return null;
    }

    return json_decode($match[1], true);
}

function actingAsMapSeller(): StoreUser
{
    $seller = createStoreUser('seller');
    DB::table('shops')->where('id', createShopFor($seller, 0))->update(['slug' => 'shop-'.$seller->id]);

    return actingAsStoreUser($seller->fresh());
}

beforeEach(function (): void {
    storeSetting('google_map', 1);
});

describe('the default location', function () {
    test('is Amsterdam while the admin has set none', function () {
        expect(DefaultMapLocation::coordinates())->toBe(['latitude' => 52.3676, 'longitude' => 4.9041]);
    });

    test('is the location set on the Google Map settings page', function () {
        storeSetting('google_map_latitude', '48.8566');
        storeSetting('google_map_longtitude', '-151.2093');

        expect(DefaultMapLocation::coordinates())->toBe(['latitude' => 48.8566, 'longitude' => -151.2093]);
    });

    test('falls back to Amsterdam when either setting is empty or out of range', function (?string $latitude, ?string $longitude) {
        storeSetting('google_map_latitude', $latitude);
        storeSetting('google_map_longtitude', $longitude);

        expect(DefaultMapLocation::coordinates())->toBe(['latitude' => 52.3676, 'longitude' => 4.9041]);
    })->with([
        'no latitude' => ['', '4.9'],
        'no longitude' => ['52.3', ''],
        'a null latitude' => [null, '4.9'],
        'a latitude past the pole' => ['90.5', '4.9'],
        'a longitude past the antimeridian' => ['52.3', '180.1'],
        'a latitude that is not a number' => ['north', '4.9'],
        'a longitude that is markup' => ['52.3', '4);alert(1'],
        'an infinite latitude' => ['INF', '4.9'],
    ]);
});

describe('the storefront address forms', function () {
    test('start the pin at Amsterdam, never Sydney', function () {
        actingAsStoreUser(createStoreUser());

        $page = $this->get('/temp/profile')->assertOk()->getContent();

        expect(mapDefaultLocationScript($page))->toBe(['latitude' => 52.3676, 'longitude' => 4.9041])
            ->and($page)->not->toContain('-33.8688')
            ->and($page)->not->toContain('151.2195')
            ->and($page)->toContain('var lat     = defaultMapLocation.latitude;');
    });

    test('start the pin at the configured default location', function () {
        storeSetting('google_map_latitude', '50.8503');
        storeSetting('google_map_longtitude', '4.3517');
        actingAsStoreUser(createStoreUser());

        $page = $this->get('/temp/profile')->assertOk()->getContent();

        expect(mapDefaultLocationScript($page))->toBe(['latitude' => 50.8503, 'longitude' => 4.3517]);
    });

    test('print no map script while the map is off', function () {
        storeSetting('google_map', 0);
        actingAsStoreUser(createStoreUser());

        expect(mapDefaultLocationScript($this->get('/temp/profile')->assertOk()->getContent()))->toBeNull();
    });

    test('save the address of a customer who never moved the pin', function (string $latitude, string $longitude) {
        storeSetting('google_map_latitude', $latitude);
        storeSetting('google_map_longtitude', $longitude);
        $customer = actingAsStoreUser(createStoreUser());

        $xpath = htmlXPath($this->get('/temp/profile')->assertOk());
        $form = formPostingTo($xpath, '/temp/addresses');
        $coordinates = [
            'latitude' => $xpath->query('.//input[@name="latitude"]', $form)->item(0)->getAttribute('value'),
            'longitude' => $xpath->query('.//input[@name="longitude"]', $form)->item(0)->getAttribute('value'),
        ];

        $this->from('/temp/profile')
            ->post('/temp/addresses', ['address' => 'Damrak 1', 'postal_code' => '1012 LG', 'phone' => '+31612345678', ...$coordinates])
            ->assertRedirect('/temp/profile')
            ->assertSessionHasNoErrors();

        expect($coordinates)->toBe(['latitude' => '', 'longitude' => ''])
            ->and(Address::sole()->only(['user_id', 'latitude', 'longitude']))->toBe(['user_id' => $customer->id, 'latitude' => null, 'longitude' => null]);
    })->with([
        'no default location' => ['', ''],
        'a default location east of 100°' => ['-33.8688', '151.2093'],
    ]);
});

describe('the seller panel', function () {
    test('starts the address pin at the default location', function () {
        actingAsMapSeller();

        $page = $this->get('/temp/seller/profile')->assertOk()->getContent();

        expect(mapDefaultLocationScript($page))->toBe(['latitude' => 52.3676, 'longitude' => 4.9041])
            ->and($page)->not->toContain('-33.8688')
            ->and($page)->not->toContain('151.2195');
    });

    test('starts the delivery pick-up pin at the configured default location', function () {
        DB::table('addons')->insert(['name' => 'Delivery boy', 'unique_identifier' => 'delivery_boy', 'activated' => 1]);
        Cache::forget('addons');
        storeSetting('google_map_latitude', '51.2194');
        storeSetting('google_map_longtitude', '4.4025');
        actingAsMapSeller();

        $page = $this->get('/temp/seller/shop')->assertOk()->getContent();

        expect(mapDefaultLocationScript($page))->toBe(['latitude' => 51.2194, 'longitude' => 4.4025])
            ->and($page)->not->toContain('-33.8688')
            ->and($page)->not->toContain('151.2195')
            ->and($page)->not->toContain('default_longtitude');
    });
});
