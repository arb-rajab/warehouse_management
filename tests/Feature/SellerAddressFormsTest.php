<?php

use App\Models\Address;
use App\Models\StoreUser;
use Illuminate\Support\Facades\DB;

/**
 * The seller panel's address forms (the "New Address" modal on
 * shop.seller.profile.index, posting to Seller\AddressController::store; the
 * edit modal its ::edit renders, posting to ::update). Neither endpoint
 * validated anything. Their rules are Seller\StoreAddressRequest /
 * Seller\UpdateAddressRequest (ValidatesAddress): address and phone required,
 * the country required while the store has an active country; state, city,
 * postal code and coordinates optional. The forms mark and require exactly
 * those fields (store-backoffice-migration.md §45).
 */
/**
 * A signed-in seller with a shop, as every real seller has: the profile page
 * reads the shop's payment settings.
 */
function actingAsShopSeller(): StoreUser
{
    $seller = createStoreUser('seller');
    createShopFor($seller, 0);

    return actingAsStoreUser($seller->fresh());
}

function sellerAddressCountry(string $code = 'NL', string $name = 'Netherlands', int $status = 1): int
{
    return DB::table('countries')->insertGetId(['code' => $code, 'name' => $name, 'status' => $status]);
}

/**
 * @return array<string, mixed>
 */
function sellerAddressPayload(array $overrides = []): array
{
    return ['address' => 'Damrak 1, Amsterdam', 'phone' => '+31612345678', ...$overrides];
}

function sellerAddressOf(int $userId, ?int $countryId = null): Address
{
    return Address::forceCreate(['user_id' => $userId, 'address' => 'Kalverstraat 2', 'postal_code' => '1012 PC', 'phone' => '0201234567', 'country_id' => $countryId, 'set_default' => 1]);
}

/**
 * @return array{country: int, state: int, city: int, foreignState: int, foreignCity: int, inactive: int}
 */
function sellerAddressReferenceRows(): array
{
    $country = sellerAddressCountry();
    $state = DB::table('states')->insertGetId(['name' => 'North Holland', 'country_id' => $country, 'status' => 1]);
    $city = DB::table('cities')->insertGetId(['name' => 'Amsterdam', 'state_id' => $state, 'status' => 1]);
    $other = sellerAddressCountry('BE', 'Belgium');
    $foreignState = DB::table('states')->insertGetId(['name' => 'Flanders', 'country_id' => $other, 'status' => 1]);
    $foreignCity = DB::table('cities')->insertGetId(['name' => 'Antwerp', 'state_id' => $foreignState, 'status' => 1]);
    $inactive = sellerAddressCountry('DE', 'Germany', status: 0);

    return compact('country', 'state', 'city', 'foreignState', 'foreignCity', 'inactive');
}

/**
 * @return array<string, array{0: Closure(array<string, int>): array<string, mixed>, 1: string}>
 */
function hostileSellerAddressInput(): array
{
    return [
        'an address over 255 characters' => [fn (array $rows) => ['address' => str_repeat('a', 256)], 'address'],
        'a phone over 255 characters' => [fn (array $rows) => ['phone' => str_repeat('6', 256)], 'phone'],
        'a postal code over 255 characters' => [fn (array $rows) => ['postal_code' => str_repeat('1', 256)], 'postal_code'],
        'an address that is not a string' => [fn (array $rows) => ['address' => ['Damrak 1']], 'address'],
        'a country that does not exist' => [fn (array $rows) => ['country_id' => 999999], 'country_id'],
        'an inactive country' => [fn (array $rows) => ['country_id' => $rows['inactive']], 'country_id'],
        'a state of another country' => [fn (array $rows) => ['state_id' => $rows['foreignState']], 'state_id'],
        'a city of another state' => [fn (array $rows) => ['state_id' => $rows['state'], 'city_id' => $rows['foreignCity']], 'city_id'],
        'a city that is not a number' => [fn (array $rows) => ['city_id' => 'Amsterdam'], 'city_id'],
        'a latitude past the pole' => [fn (array $rows) => ['latitude' => -91], 'latitude'],
        'a longitude the column cannot hold' => [fn (array $rows) => ['longitude' => 151.2195], 'longitude'],
    ];
}

describe('the new-address form', function () {
    test('marks and requires exactly address, country and phone; state and city are optional', function () {
        sellerAddressCountry();
        actingAsShopSeller();

        $xpath = htmlXPath($this->get('/temp/seller/profile')->assertOk());
        $form = formPostingTo($xpath, '/temp/seller/addresses');

        expect(formFieldMarks($xpath, $form))->toBe([
            'address' => ['tag' => 'textarea', 'required' => true, 'mark' => true, 'optional' => false],
            'country_id' => ['tag' => 'select', 'required' => true, 'mark' => true, 'optional' => false],
            'state_id' => ['tag' => 'select', 'required' => false, 'mark' => false, 'optional' => false],
            'city_id' => ['tag' => 'select', 'required' => false, 'mark' => false, 'optional' => false],
            'phone' => ['tag' => 'input', 'required' => true, 'mark' => true, 'optional' => false],
        ])
            ->and($xpath->query('.//*[@data-required-legend]', $form)->length)->toBe(1)
            ->and($xpath->query('.//label[preceding::*[@data-required-legend]]', $form)->length)->toBe($xpath->query('.//label', $form)->length);
    });

    test('without an active country it says so and asks for none', function () {
        actingAsShopSeller();

        $xpath = htmlXPath($this->get('/temp/seller/profile')->assertOk());
        $form = formPostingTo($xpath, '/temp/seller/addresses');

        expect(array_keys(formFieldMarks($xpath, $form)))->toBe(['address', 'state_id', 'city_id', 'phone'])
            ->and($xpath->query('.//*[@data-no-countries]', $form)->length)->toBe(1);
    });
});

describe('saving a new address', function () {
    test('saves every field it is given and goes back', function () {
        $rows = sellerAddressReferenceRows();
        $seller = actingAsShopSeller();

        $this->from('/temp/seller/profile')
            ->post('/temp/seller/addresses', sellerAddressPayload([
                'country_id' => $rows['country'], 'state_id' => $rows['state'], 'city_id' => $rows['city'], 'postal_code' => '1012 LG', 'latitude' => '52.37', 'longitude' => '4.89',
            ]))
            ->assertRedirect('/temp/seller/profile')
            ->assertSessionHasNoErrors();

        expect(Address::sole()->only(['user_id', 'address', 'country_id', 'state_id', 'city_id', 'postal_code', 'phone', 'latitude', 'longitude']))->toEqual([
            'user_id' => $seller->id, 'address' => 'Damrak 1, Amsterdam', 'country_id' => $rows['country'], 'state_id' => $rows['state'], 'city_id' => $rows['city'],
            'postal_code' => '1012 LG', 'phone' => '+31612345678', 'latitude' => '52.370000000000000', 'longitude' => '4.890000000000000',
        ])
            ->and(session()->has('flash_notification'))->toBeFalse();
    });

    test('saves without a state, a city or a postal code, and without a country while there is none', function () {
        $seller = actingAsShopSeller();

        $this->post('/temp/seller/addresses', sellerAddressPayload())->assertSessionHasNoErrors();

        expect(Address::sole()->only(['user_id', 'country_id', 'state_id', 'city_id', 'postal_code']))
            ->toEqual(['user_id' => $seller->id, 'country_id' => null, 'state_id' => null, 'city_id' => null, 'postal_code' => null]);
    });

    test('refuses a missing required field with that field\'s error, keeps the input and saves nothing', function (string $field) {
        $countryId = sellerAddressCountry();
        actingAsShopSeller();
        $payload = sellerAddressPayload(['country_id' => $countryId]);
        unset($payload[$field]);

        $this->from('/temp/seller/profile')->post('/temp/seller/addresses', $payload)
            ->assertRedirect('/temp/seller/profile')
            ->assertSessionHasErrorsIn('storeAddress', [$field])
            ->assertSessionHasInput(array_key_first($payload));

        expect(Address::count())->toBe(0);
    })->with(['address', 'country_id', 'phone']);

    test('refuses hostile input and saves nothing', function (Closure $input, string $field) {
        $rows = sellerAddressReferenceRows();
        actingAsShopSeller();

        $this->post('/temp/seller/addresses', sellerAddressPayload(['country_id' => $rows['country'], ...$input($rows)]))
            ->assertSessionHasErrorsIn('storeAddress', [$field]);

        expect(Address::count())->toBe(0);
    })->with(hostileSellerAddressInput());

    test('only ever saves the address for the seller signed in', function () {
        $other = createStoreUser('seller');
        $seller = actingAsShopSeller();

        $this->post('/temp/seller/addresses', sellerAddressPayload(['user_id' => $other->id, 'customer_id' => $other->id]))->assertSessionHasNoErrors();

        expect(Address::sole()->user_id)->toBe($seller->id);
    });

    test('a customer cannot use the seller endpoint', function () {
        actingAsStoreUser(createStoreUser('customer'));

        expect($this->post('/temp/seller/addresses', sellerAddressPayload())->getStatusCode())->toBeIn([302, 403, 404]);
        expect(Address::count())->toBe(0);
    });

    test('a failed save reopens the form with the input and the errors', function () {
        sellerAddressCountry();
        actingAsShopSeller();

        $this->from('/temp/seller/profile')->post('/temp/seller/addresses', sellerAddressPayload(['address' => 'Damrak 9', 'phone' => '']));
        $page = $this->get('/temp/seller/profile')->assertOk();
        $xpath = htmlXPath($page);
        $form = formPostingTo($xpath, '/temp/seller/addresses');

        expect(trim($xpath->query('.//textarea[@name="address"]', $form)->item(0)->textContent))->toBe('Damrak 9')
            ->and($xpath->query('.//input[@name="phone"]', $form)->item(0)->getAttribute('class'))->toContain('is-invalid')
            ->and(trim($xpath->query('.//input[@name="phone"]/following-sibling::*[@role="alert"]', $form)->item(0)?->textContent ?? ''))->toBe('The phone field is required.')
            ->and($page->getContent())->toContain("$('#new-address-modal').modal('show')");
    });
});

describe('editing an address', function () {
    test('the edit form marks and requires the same fields, filled with the address', function () {
        $rows = sellerAddressReferenceRows();
        $seller = actingAsShopSeller();
        $address = sellerAddressOf($seller->id, $rows['country']);

        $xpath = htmlXPath($this->get("/temp/seller/addresses/{$address->id}/edit")->assertOk()->json('html'));
        $form = formPostingTo($xpath, "/temp/seller/address/update/{$address->id}");

        expect(formFieldMarks($xpath, $form))->toBe([
            'address' => ['tag' => 'textarea', 'required' => true, 'mark' => true, 'optional' => false],
            'country_id' => ['tag' => 'select', 'required' => true, 'mark' => true, 'optional' => false],
            'state_id' => ['tag' => 'select', 'required' => false, 'mark' => false, 'optional' => false],
            'city_id' => ['tag' => 'select', 'required' => false, 'mark' => false, 'optional' => false],
            'phone' => ['tag' => 'input', 'required' => true, 'mark' => true, 'optional' => false],
        ])
            ->and($xpath->query('.//*[@data-required-legend]', $form)->length)->toBe(1)
            ->and($xpath->query('.//select[@name="state_id"]/option', $form)->item(0)?->getAttribute('value'))->toBe('')
            ->and(trim($xpath->query('.//textarea[@name="address"]', $form)->item(0)->textContent))->toBe('Kalverstraat 2');
    });

    test('updates every field and goes back with the same message', function () {
        $rows = sellerAddressReferenceRows();
        $seller = actingAsShopSeller();
        $address = sellerAddressOf($seller->id);

        $this->from('/temp/seller/profile')
            ->post("/temp/seller/address/update/{$address->id}", sellerAddressPayload(['country_id' => $rows['country'], 'state_id' => $rows['state'], 'city_id' => $rows['city'], 'address_id' => $address->id]))
            ->assertRedirect('/temp/seller/profile')
            ->assertSessionHasNoErrors();

        expect($address->fresh()->only(['user_id', 'address', 'country_id', 'state_id', 'city_id', 'phone']))->toEqual([
            'user_id' => $seller->id, 'address' => 'Damrak 1, Amsterdam', 'country_id' => $rows['country'], 'state_id' => $rows['state'], 'city_id' => $rows['city'], 'phone' => '+31612345678',
        ])
            ->and(flashedMessage())->toBe('Address info updated successfully');
    });

    test('refuses a missing required field and changes nothing', function (string $field) {
        $countryId = sellerAddressCountry();
        $seller = actingAsShopSeller();
        $address = sellerAddressOf($seller->id, $countryId);
        $payload = sellerAddressPayload(['country_id' => $countryId]);
        unset($payload[$field]);

        $this->from('/temp/seller/profile')->post("/temp/seller/address/update/{$address->id}", $payload)
            ->assertRedirect('/temp/seller/profile')
            ->assertSessionHasErrorsIn('updateAddress', [$field]);

        expect($address->fresh()->only(['address', 'country_id', 'phone']))->toEqual(['address' => 'Kalverstraat 2', 'country_id' => $countryId, 'phone' => '0201234567']);
    })->with(['address', 'country_id', 'phone']);

    test('refuses hostile input and changes nothing', function (Closure $input, string $field) {
        $rows = sellerAddressReferenceRows();
        $seller = actingAsShopSeller();
        $address = sellerAddressOf($seller->id, $rows['country']);

        $this->post("/temp/seller/address/update/{$address->id}", sellerAddressPayload(['country_id' => $rows['country'], ...$input($rows)]))
            ->assertSessionHasErrorsIn('updateAddress', [$field]);

        expect($address->fresh()->address)->toBe('Kalverstraat 2');
    })->with(hostileSellerAddressInput());

    test("cannot change another seller's address, whatever the payload", function (array $payload) {
        $theirs = sellerAddressOf(createStoreUser('seller')->id);
        actingAsShopSeller();

        $this->post("/temp/seller/address/update/{$theirs->id}", $payload)->assertForbidden();

        expect($theirs->fresh()->address)->toBe('Kalverstraat 2');
    })->with([
        'a complete payload' => [sellerAddressPayload()],
        'an empty payload' => [[]],
    ]);

    test('an address that does not exist is a 404', function () {
        actingAsShopSeller();

        $this->post('/temp/seller/address/update/999999', sellerAddressPayload())->assertNotFound();
    });

    test("a failed update reopens that address's form with the input and the errors, and never another seller's", function () {
        $countryId = sellerAddressCountry();
        $seller = actingAsShopSeller();
        $address = sellerAddressOf($seller->id, $countryId);
        $theirs = sellerAddressOf(createStoreUser('seller')->id);

        $this->from('/temp/seller/profile')->post("/temp/seller/address/update/{$address->id}", sellerAddressPayload([
            'address_id' => $address->id, 'country_id' => $countryId, 'address' => '',
        ]));
        $page = $this->get('/temp/seller/profile')->assertOk();
        $xpath = htmlXPath($page);
        $form = formPostingTo($xpath, "/temp/seller/address/update/{$address->id}");

        expect($xpath->query('.//textarea[@name="address"]', $form)->item(0)->getAttribute('class'))->toContain('is-invalid')
            ->and($page->getContent())->toContain("$('#edit-address-modal').modal('show')");

        $this->from('/temp/seller/profile')->post("/temp/seller/address/update/{$address->id}", ['address_id' => $theirs->id]);
        expect(htmlXPath($this->get('/temp/seller/profile'))->query("//form[contains(@action, '/temp/seller/address/update/{$theirs->id}')]")->length)->toBe(0);
    });
});
