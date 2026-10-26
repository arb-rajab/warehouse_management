<?php

use App\Models\Address;
use App\Models\Cart;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The storefront's address forms (the "New Address" modal on the profile,
 * dashboard and checkout pages, posting to AddressController::store; the
 * edit modal AddressController::edit renders, posting to ::update) and the
 * checkout's address pick (CheckoutController::store_shipping_info).
 *
 * Their rules are StoreAddressRequest / UpdateAddressRequest (address, postal
 * code and phone required; the country required while the store has an
 * active country to choose; state, city, latitude and longitude optional, each
 * matched to its column) and StoreShippingInfoRequest (address_id required).
 * The forms mark and require exactly those fields
 * (.ai/rules/form-required-fields.md, .ai/archive/store-backoffice/changes-20261021.md §45).
 */
function storefrontAddressCountry(string $code = 'NL', string $name = 'Netherlands', int $status = 1): int
{
    return DB::table('countries')->insertGetId(['code' => $code, 'name' => $name, 'status' => $status]);
}

/**
 * @return array<string, mixed>
 */
function storefrontAddressPayload(array $overrides = []): array
{
    return ['address' => 'Damrak 1, Amsterdam', 'postal_code' => '1012 LG', 'phone' => '+31612345678', ...$overrides];
}

function storefrontAddressOf(int $userId, ?int $countryId = null): Address
{
    return Address::forceCreate(['user_id' => $userId, 'address' => 'Kalverstraat 2', 'postal_code' => '1012 PC', 'phone' => '0201234567', 'country_id' => $countryId, 'set_default' => 1]);
}

/**
 * @return array<string, array{0: array<string, mixed>, 1: string}>
 */
function hostileStorefrontAddressInput(): array
{
    return [
        'an address over 255 characters' => [['address' => str_repeat('a', 256)], 'address'],
        'a postal code over 255 characters' => [['postal_code' => str_repeat('1', 256)], 'postal_code'],
        'a phone over 255 characters' => [['phone' => str_repeat('6', 256)], 'phone'],
        'an address that is not a string' => [['address' => ['Damrak 1']], 'address'],
        'a phone that is not a string' => [['phone' => ['+31612345678']], 'phone'],
        'a country that does not exist' => [['country_id' => 999999], 'country_id'],
        'a country that is not a number' => [['country_id' => 'NL'], 'country_id'],
        'an inactive country' => [['country_id' => 'inactive'], 'country_id'],
        'a state of another country' => [['state_id' => 'foreign'], 'state_id'],
        'a state that does not exist' => [['state_id' => 999999], 'state_id'],
        'a city of another state' => [['city_id' => 'foreign'], 'city_id'],
        'a latitude that is not a number' => [['latitude' => 'north'], 'latitude'],
        'a latitude past the pole' => [['latitude' => 90.5], 'latitude'],
        'a longitude past the antimeridian, eastwards' => [['longitude' => 180.0001], 'longitude'],
        'a longitude past the antimeridian, westwards' => [['longitude' => -180.0001], 'longitude'],
        'a longitude that is not a number' => [['longitude' => 'east'], 'longitude'],
    ];
}

/**
 * Resolves the placeholders of hostileStorefrontAddressInput() against real
 * reference rows: an active country (sent as the address's country), an
 * inactive one, and a state and city of a second country.
 *
 * @param  array<string, mixed>  $input
 * @return array<string, mixed>
 */
function resolveHostileAddressInput(array $input, int $countryId): array
{
    $other = storefrontAddressCountry('BE', 'Belgium');
    $foreignState = DB::table('states')->insertGetId(['name' => 'Flanders', 'country_id' => $other, 'status' => 1]);
    $foreignCity = DB::table('cities')->insertGetId(['name' => 'Antwerp', 'state_id' => $foreignState, 'status' => 1]);
    $inactive = storefrontAddressCountry('DE', 'Germany', status: 0);

    return array_map(fn (mixed $value) => match ($value) {
        'inactive' => $inactive,
        'foreign' => array_key_exists('city_id', $input) ? $foreignCity : $foreignState,
        default => $value,
    }, ['country_id' => $countryId, ...$input]);
}

describe('the new-address form', function () {
    test('marks and requires exactly address, country, postal code and phone, under the legend', function () {
        storefrontAddressCountry();
        actingAsStoreUser(createStoreUser());

        $xpath = htmlXPath($this->get('/temp/profile')->assertOk());
        $form = formPostingTo($xpath, '/temp/addresses');

        expect(formFieldMarks($xpath, $form))->toBe([
            'address' => ['tag' => 'textarea', 'required' => true, 'mark' => true, 'optional' => false],
            'country_id' => ['tag' => 'select', 'required' => true, 'mark' => true, 'optional' => false],
            'postal_code' => ['tag' => 'input', 'required' => true, 'mark' => true, 'optional' => false],
            'phone' => ['tag' => 'input', 'required' => true, 'mark' => true, 'optional' => false],
        ])
            ->and($xpath->query('.//*[@data-required-legend]', $form)->length)->toBe(1)
            ->and(trim(preg_replace('/\s+/', ' ', $xpath->query('.//*[@data-required-legend]', $form)->item(0)->textContent)))->toBe('* Required fields')
            ->and($xpath->query('.//label[preceding::*[@data-required-legend]]', $form)->length)->toBe($xpath->query('.//label', $form)->length)
            ->and($xpath->query('.//textarea[@name="address"]', $form)->item(0)->getAttribute('maxlength'))->toBe('255')
            ->and($xpath->query('.//input[@name="postal_code"]', $form)->item(0)->getAttribute('maxlength'))->toBe('255')
            ->and($xpath->query('.//input[@name="phone"]', $form)->item(0)->getAttribute('maxlength'))->toBe('255');
    });

    test('lists only the active countries, the highest priority first', function () {
        DB::table('countries')->insert([
            ['code' => 'BE', 'name' => 'Belgium', 'status' => 1, 'priority' => 0],
            ['code' => 'NL', 'name' => 'Netherlands', 'status' => 1, 'priority' => 1],
            ['code' => 'DE', 'name' => 'Germany', 'status' => 0, 'priority' => 0],
        ]);
        actingAsStoreUser(createStoreUser());

        $xpath = htmlXPath($this->get('/temp/profile')->assertOk());
        $options = $xpath->query('.//select[@name="country_id"]/option', formPostingTo($xpath, '/temp/addresses'));

        expect(array_map(fn (DOMElement $option) => trim($option->textContent), iterator_to_array($options)))
            ->toBe(['Select your country', 'Netherlands', 'Belgium']);
    });

    test('without an active country it says so and asks for none', function () {
        storefrontAddressCountry(status: 0);
        actingAsStoreUser(createStoreUser());

        $xpath = htmlXPath($this->get('/temp/profile')->assertOk());
        $form = formPostingTo($xpath, '/temp/addresses');

        expect(array_keys(formFieldMarks($xpath, $form)))->toBe(['address', 'postal_code', 'phone'])
            ->and(trim($xpath->query('.//*[@data-no-countries]', $form)->item(0)?->textContent ?? ''))
            ->toBe('No countries are set up yet. You can save the address without one.')
            ->and($xpath->query('.//label[@for="new_address_country"]//*[@data-required-mark]', $form)->length)->toBe(0);
    });
});

describe('saving a new address', function () {
    test('saves every field it is given and goes back with the same message', function () {
        $countryId = storefrontAddressCountry();
        $stateId = DB::table('states')->insertGetId(['name' => 'North Holland', 'country_id' => $countryId, 'status' => 1]);
        $cityId = DB::table('cities')->insertGetId(['name' => 'Amsterdam', 'state_id' => $stateId, 'status' => 1]);
        $customer = actingAsStoreUser(createStoreUser());

        $this->from('/temp/profile')
            ->post('/temp/addresses', storefrontAddressPayload([
                'country_id' => $countryId, 'state_id' => $stateId, 'city_id' => $cityId, 'latitude' => '52.37', 'longitude' => '4.89',
            ]))
            ->assertRedirect('/temp/profile')
            ->assertSessionHasNoErrors();

        expect(Address::sole()->only(['user_id', 'address', 'country_id', 'state_id', 'city_id', 'postal_code', 'phone', 'latitude', 'longitude']))->toEqual([
            'user_id' => $customer->id, 'address' => 'Damrak 1, Amsterdam', 'country_id' => $countryId, 'state_id' => $stateId, 'city_id' => $cityId,
            'postal_code' => '1012 LG', 'phone' => '+31612345678', 'latitude' => '52.370000000000000', 'longitude' => '4.890000000000000',
        ])
            ->and(flashedMessage())->toBe('Address info Stored successfully');
    });

    test('saves an address without a country while the store has none to choose', function () {
        storefrontAddressCountry(status: 0);
        $customer = actingAsStoreUser(createStoreUser());

        $this->from('/temp/profile')->post('/temp/addresses', storefrontAddressPayload())
            ->assertRedirect('/temp/profile')
            ->assertSessionHasNoErrors();

        expect(Address::sole()->only(['user_id', 'country_id', 'state_id', 'city_id']))
            ->toEqual(['user_id' => $customer->id, 'country_id' => null, 'state_id' => null, 'city_id' => null]);
    });

    test('refuses a missing required field with that field\'s error, keeps the input and saves nothing', function (string $field, string $message) {
        $countryId = storefrontAddressCountry();
        actingAsStoreUser(createStoreUser());
        $payload = storefrontAddressPayload(['country_id' => $countryId]);
        unset($payload[$field]);

        $this->from('/temp/profile')->post('/temp/addresses', $payload)
            ->assertRedirect('/temp/profile')
            ->assertSessionHasErrorsIn('storeAddress', [$field])
            ->assertSessionHasInput(array_key_first(array_diff_key(storefrontAddressPayload(), [$field => true])));

        expect(Address::count())->toBe(0)
            ->and(flashedMessage())->toBe($message);
    })->with([
        'address' => ['address', 'The address field is required.'],
        'country' => ['country_id', 'The country id field is required.'],
        'postal code' => ['postal_code', 'The postal code field is required.'],
        // The one failure the endpoint reported before it had a rule set: the same message.
        'phone' => ['phone', 'Phone number is required'],
    ]);

    test('refuses hostile input and saves nothing', function (array $input, string $field) {
        $countryId = storefrontAddressCountry();
        actingAsStoreUser(createStoreUser());

        $this->from('/temp/profile')
            ->post('/temp/addresses', storefrontAddressPayload(resolveHostileAddressInput($input, $countryId)))
            ->assertRedirect('/temp/profile')
            ->assertSessionHasErrorsIn('storeAddress', [$field]);

        expect(Address::count())->toBe(0);
    })->with(hostileStorefrontAddressInput());

    test('accepts every longitude up to the antimeridian', function (float $longitude) {
        actingAsStoreUser(createStoreUser());

        $this->post('/temp/addresses', storefrontAddressPayload(['latitude' => -33.8688, 'longitude' => $longitude]))->assertSessionHasNoErrors();

        expect((float) Address::sole()->longitude)->toBe((float) $longitude);
    })->with(validMapLongitudes());

    test('a script is refused before it reaches the endpoint (the WAF)', function () {
        actingAsStoreUser(createStoreUser());

        $this->post('/temp/addresses', storefrontAddressPayload(['address' => '<script>alert(1)</script>']))->assertForbidden();

        expect(Address::count())->toBe(0);
    });

    test('keeps other markup as text and prints it escaped', function () {
        actingAsStoreUser(createStoreUser());

        $this->post('/temp/addresses', storefrontAddressPayload(['address' => '<b>Damrak</b> 1 & "Co"']))->assertSessionHasNoErrors();

        expect(Address::sole()->address)->toBe('<b>Damrak</b> 1 & "Co"');
        $this->get('/temp/profile')->assertOk()
            ->assertDontSee('<b>Damrak</b>', false)
            ->assertSee('&lt;b&gt;Damrak&lt;/b&gt; 1 &amp; &quot;Co&quot;', false);
    });

    test('cannot save an address for another customer, whatever the payload', function (array $payload) {
        $stranger = createStoreUser();
        actingAsStoreUser(createStoreUser());

        $this->post('/temp/addresses', [...$payload, 'customer_id' => $stranger->id])->assertForbidden();

        expect(Address::count())->toBe(0);
    })->with([
        'a complete payload' => [storefrontAddressPayload()],
        'an empty payload' => [[]],
    ]);

    test('a guest is sent to sign in and nothing is saved', function () {
        $this->post('/temp/addresses', storefrontAddressPayload())->assertRedirect();

        expect(Address::count())->toBe(0);
    });

    test('a failed save reopens the form with the input and the errors', function () {
        storefrontAddressCountry();
        actingAsStoreUser(createStoreUser());

        $this->from('/temp/profile')->post('/temp/addresses', storefrontAddressPayload(['postal_code' => '', 'address' => 'Damrak 9']));
        $page = $this->get('/temp/profile')->assertOk();
        $xpath = htmlXPath($page);
        $form = formPostingTo($xpath, '/temp/addresses');

        expect(trim($xpath->query('.//textarea[@name="address"]', $form)->item(0)->textContent))->toBe('Damrak 9')
            ->and($xpath->query('.//input[@name="postal_code"]', $form)->item(0)->getAttribute('class'))->toContain('is-invalid')
            ->and(trim($xpath->query('.//input[@name="postal_code"]/following-sibling::*[@role="alert"]', $form)->item(0)?->textContent ?? ''))
            ->toBe('The postal code field is required.')
            ->and($page->getContent())->toContain("$('#new-address-modal').modal('show')");
    });
});

describe('editing an address', function () {
    test('the edit form marks and requires the same fields, filled with the address', function () {
        $countryId = storefrontAddressCountry();
        $customer = actingAsStoreUser(createStoreUser());
        $address = storefrontAddressOf($customer->id, $countryId);

        $xpath = htmlXPath($this->get("/temp/addresses/{$address->id}/edit")->assertOk()->json('html'));
        $form = formPostingTo($xpath, "/temp/addresses/update/{$address->id}");

        expect(formFieldMarks($xpath, $form))->toBe([
            'address' => ['tag' => 'textarea', 'required' => true, 'mark' => true, 'optional' => false],
            'country_id' => ['tag' => 'select', 'required' => true, 'mark' => true, 'optional' => false],
            'postal_code' => ['tag' => 'input', 'required' => true, 'mark' => true, 'optional' => false],
            'phone' => ['tag' => 'input', 'required' => true, 'mark' => true, 'optional' => false],
        ])
            ->and($xpath->query('.//*[@data-required-legend]', $form)->length)->toBe(1)
            ->and(trim($xpath->query('.//textarea[@name="address"]', $form)->item(0)->textContent))->toBe('Kalverstraat 2')
            ->and($xpath->query('.//select[@name="country_id"]/option[@selected]', $form)->item(0)?->getAttribute('value'))->toBe((string) $countryId)
            ->and($xpath->query('.//input[@name="address_id"][@type="hidden"]', $form)->item(0)?->getAttribute('value'))->toBe((string) $address->id);
    });

    test('updates every field and goes back with the same message', function () {
        $countryId = storefrontAddressCountry();
        $customer = actingAsStoreUser(createStoreUser());
        $address = storefrontAddressOf($customer->id);

        $this->from('/temp/profile')
            ->post("/temp/addresses/update/{$address->id}", storefrontAddressPayload(['country_id' => $countryId, 'address_id' => $address->id]))
            ->assertRedirect('/temp/profile')
            ->assertSessionHasNoErrors();

        expect($address->fresh()->only(['user_id', 'address', 'country_id', 'postal_code', 'phone']))->toEqual([
            'user_id' => $customer->id, 'address' => 'Damrak 1, Amsterdam', 'country_id' => $countryId, 'postal_code' => '1012 LG', 'phone' => '+31612345678',
        ])
            ->and(flashedMessage())->toBe('Address info updated successfully');
    });

    test('refuses a missing required field and changes nothing', function (string $field) {
        $countryId = storefrontAddressCountry();
        $customer = actingAsStoreUser(createStoreUser());
        $address = storefrontAddressOf($customer->id, $countryId);
        $payload = storefrontAddressPayload(['country_id' => $countryId]);
        unset($payload[$field]);

        $this->from('/temp/profile')->post("/temp/addresses/update/{$address->id}", $payload)
            ->assertRedirect('/temp/profile')
            ->assertSessionHasErrorsIn('updateAddress', [$field]);

        expect($address->fresh()->only(['address', 'country_id', 'postal_code', 'phone']))
            ->toEqual(['address' => 'Kalverstraat 2', 'country_id' => $countryId, 'postal_code' => '1012 PC', 'phone' => '0201234567']);
    })->with(['address', 'country_id', 'postal_code', 'phone']);

    test('refuses hostile input and changes nothing', function (array $input, string $field) {
        $countryId = storefrontAddressCountry();
        $customer = actingAsStoreUser(createStoreUser());
        $address = storefrontAddressOf($customer->id, $countryId);

        $this->post("/temp/addresses/update/{$address->id}", storefrontAddressPayload(resolveHostileAddressInput($input, $countryId)))
            ->assertSessionHasErrorsIn('updateAddress', [$field]);

        expect($address->fresh()->address)->toBe('Kalverstraat 2');
    })->with([
        ...hostileStorefrontAddressInput(),
        'an address id that is not a number' => [['address_id' => 'x'], 'address_id'],
    ]);

    test('accepts every longitude up to the antimeridian', function (float $longitude) {
        $customer = actingAsStoreUser(createStoreUser());
        $address = storefrontAddressOf($customer->id);

        $this->post("/temp/addresses/update/{$address->id}", storefrontAddressPayload(['latitude' => 52.37, 'longitude' => $longitude]))
            ->assertSessionHasNoErrors();

        expect((float) $address->fresh()->longitude)->toBe((float) $longitude);
    })->with(validMapLongitudes());

    test("cannot change another customer's address, whatever the payload", function (array $payload) {
        $theirs = storefrontAddressOf(createStoreUser()->id);
        actingAsStoreUser(createStoreUser());

        $this->post("/temp/addresses/update/{$theirs->id}", $payload)->assertForbidden();

        expect($theirs->fresh()->address)->toBe('Kalverstraat 2');
    })->with([
        'a complete payload' => [storefrontAddressPayload()],
        'an empty payload' => [[]],
    ]);

    test('an address that does not exist is a 404', function () {
        actingAsStoreUser(createStoreUser());

        $this->post('/temp/addresses/update/999999', storefrontAddressPayload())->assertNotFound();
    });

    test('a failed update reopens that address\'s form with the input and the errors', function () {
        $countryId = storefrontAddressCountry();
        $customer = actingAsStoreUser(createStoreUser());
        $address = storefrontAddressOf($customer->id, $countryId);

        $this->from('/temp/profile')->post("/temp/addresses/update/{$address->id}", storefrontAddressPayload([
            'address_id' => $address->id, 'country_id' => $countryId, 'address' => 'Damrak 9', 'phone' => '',
        ]));
        $page = $this->get('/temp/profile')->assertOk();
        $xpath = htmlXPath($page);
        $form = formPostingTo($xpath, "/temp/addresses/update/{$address->id}");

        expect(trim($xpath->query('.//textarea[@name="address"]', $form)->item(0)->textContent))->toBe('Damrak 9')
            ->and($xpath->query('.//input[@name="phone"]', $form)->item(0)->getAttribute('class'))->toContain('is-invalid')
            ->and($page->getContent())->toContain("$('#edit-address-modal').modal('show')");
    });

    test("a failed update never prints another customer's address", function () {
        $theirs = storefrontAddressOf(createStoreUser()->id);
        $customer = actingAsStoreUser(createStoreUser());
        $mine = storefrontAddressOf($customer->id);

        $this->from('/temp/profile')->post("/temp/addresses/update/{$mine->id}", ['address_id' => $theirs->id]);
        $xpath = htmlXPath($this->get('/temp/profile')->assertOk());

        expect($xpath->query("//form[contains(@action, '/temp/addresses/update/{$theirs->id}')]")->length)->toBe(0);
    });
});

describe('choosing the shipping address at checkout', function () {
    test('the address choice is required and marked', function () {
        $customer = actingAsStoreUser(createStoreUser());
        orderableCartLine($customer, createStoreUser('seller'), 1);

        $xpath = htmlXPath($this->get('/temp/checkout')->assertOk());
        $form = formPostingTo($xpath, '/temp/checkout/delivery_info');
        $legend = $xpath->query('.//fieldset/legend', $form)->item(0);

        expect($xpath->query('.//input[@type="radio"][@name="address_id"][not(@required)]', $form)->length)->toBe(0)
            ->and($xpath->query('.//input[@type="radio"][@name="address_id"]', $form)->length)->toBe(1)
            ->and(trim(preg_replace('/\s+/', ' ', $legend?->textContent ?? '')))->toBe('Shipping address*required')
            ->and($xpath->query('.//*[@data-required-legend]', $form)->length)->toBe(1);
    });

    test('continuing without an address keeps the message and changes no cart line', function () {
        $customer = actingAsStoreUser(createStoreUser());
        $cart = orderableCartLine($customer, createStoreUser('seller'), 1, cartAttributes: ['address_id' => 0]);

        $this->from('/temp/checkout')->post('/temp/checkout/delivery_info')
            ->assertRedirect('/temp/checkout')
            ->assertSessionHasErrors('address_id');

        expect(flashedMessage())->toBe('Please add shipping address')
            ->and($cart->fresh()->address_id)->toBe(0);
    });

    test('an address id that is not a number is refused', function (mixed $addressId) {
        $customer = actingAsStoreUser(createStoreUser());
        $cart = orderableCartLine($customer, createStoreUser('seller'), 1, cartAttributes: ['address_id' => 0]);

        $this->from('/temp/checkout')->post('/temp/checkout/delivery_info', ['address_id' => $addressId])
            ->assertRedirect('/temp/checkout')
            ->assertSessionHasErrors('address_id');

        expect($cart->fresh()->address_id)->toBe(0);
    })->with(['a word' => 'home', 'a list' => [[1]]]);

    test('an address that does not exist is a 404', function () {
        $customer = actingAsStoreUser(createStoreUser());
        $cart = orderableCartLine($customer, createStoreUser('seller'), 1, cartAttributes: ['address_id' => 0]);

        $this->post('/temp/checkout/delivery_info', ['address_id' => 999999])->assertNotFound();

        expect($cart->fresh()->address_id)->toBe(0);
    });

    test('a chosen own address is set on every cart line', function () {
        // The delivery step names the store's own admin account, which every real store has.
        createStoreUser('admin');
        $customer = actingAsStoreUser(createStoreUser());
        $seller = createStoreUser('seller');
        $first = orderableCartLine($customer, $seller, 1, cartAttributes: ['address_id' => 0]);
        $second = orderableCartLine($customer, $seller, 2, cartAttributes: ['address_id' => 0]);
        $address = storefrontAddressOf($customer->id);

        $this->post('/temp/checkout/delivery_info', ['address_id' => $address->id])->assertOk()->assertViewIs('shop.frontend.delivery_info');

        expect(Cart::whereKey([$first->id, $second->id])->pluck('address_id')->unique()->all())->toBe([$address->id]);
    });
});

test('the over-long values the column cannot hold are refused before the database sees them', function () {
    actingAsStoreUser(createStoreUser());

    $this->post('/temp/addresses', storefrontAddressPayload(['address' => Str::repeat('é', 256)]))
        ->assertSessionHasErrorsIn('storeAddress', ['address']);

    expect(Address::count())->toBe(0);
});
