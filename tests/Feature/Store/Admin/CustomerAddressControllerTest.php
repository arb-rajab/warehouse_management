<?php

use App\Models\Address;
use App\Models\StoreUser;
use Illuminate\Support\Facades\DB;

/** @return array<string,mixed> */
function adminCustomerAddressForm(array $overrides = []): array
{
    $country = DB::table('countries')->insertGetId(['name' => 'Netherlands', 'code' => 'NL', 'status' => 1]);

    return ['address' => 'Main Street', 'phone' => '31610000000', 'country_id' => $country, 'postal_code' => '1234 AB', ...$overrides];
}

function adminCustomerAddress(StoreUser $customer, bool $default = false): Address
{
    $address = new Address;
    $address->fill(adminCustomerAddressForm());
    $address->user_id = $customer->id;
    $address->set_default = $default;
    $address->save();

    return $address;
}

foreach (['admin.customers.addresses.store' => 'post', 'admin.customers.addresses.update' => 'put', 'admin.customers.addresses.destroy' => 'delete', 'admin.customers.addresses.default' => 'post'] as $route => $method) {
    test($route.' refuses unauthorized callers without changing any address', function (string $caller) use ($route, $method) {
        $customer = createStoreUser();
        $address = adminCustomerAddress($customer);
        $payload = adminCustomerAddressForm();
        $before = $address->fresh()->getAttributes();
        $count = Address::count();
        actingAsRefusedStoreCaller($caller);
        $params = $route === 'admin.customers.addresses.store' ? [$customer] : [$customer, $address];
        assertStoreCallerRefused($this->$method(route($route, $params), $payload), $caller);
        expect($address->fresh()->getAttributes())->toBe($before)->and(Address::count())->toBe($count);
    })->with('refused store admin callers');

    test($route.' refuses missing accounts and protects other customer ownership', function () use ($route, $method) {
        actingAsStoreUser(createStoreAdmin());
        $customer = createStoreUser();
        $other = createStoreUser();
        $address = adminCustomerAddress($other);
        $before = $address->fresh()->getAttributes();
        $payload = adminCustomerAddressForm();
        $params = $route === 'admin.customers.addresses.store' ? [999999] : [999999, $address];
        $this->$method(route($route, $params), $payload)->assertNotFound();
        if ($route !== 'admin.customers.addresses.store') {
            $this->$method(route($route, [$customer, $address]), $payload)->assertForbidden();
            $this->$method(route($route, [$customer, 999999]), $payload)->assertNotFound();
        }
        expect($address->fresh()->getAttributes())->toBe($before);
    });
}

test('admin address creation derives ownership and first default from the bound customer', function () {
    actingAsStoreUser(createStoreStaff('edit_customer'));
    $customer = createStoreUser();
    $other = createStoreUser();
    $form = adminCustomerAddressForm(['customer_id' => $other->id, 'user_id' => $other->id, 'set_default' => 0]);
    $this->post(route('admin.customers.addresses.store', $customer), $form)->assertRedirect(route('customers.edit', $customer));
    $address = Address::query()->firstOrFail();
    expect((int) $address->user_id)->toBe($customer->id)->and((bool) $address->set_default)->toBeTrue()->and($address->address)->toBe('Main Street');
    $this->post(route('admin.customers.addresses.store', $customer), $form)->assertRedirect();
    expect(Address::where('user_id', $customer->id)->where('set_default', 1)->count())->toBe(1);
});

test('admin address updates validate related country state city and coordinates without modifying ownership', function () {
    actingAsStoreUser(createStoreStaff('edit_customer'));
    $customer = createStoreUser();
    $address = adminCustomerAddress($customer);
    $payload = adminCustomerAddressForm();
    $state = DB::table('states')->insertGetId(['country_id' => $address->country_id, 'name' => 'Noise', 'status' => 1]);
    $city = DB::table('cities')->insertGetId(['state_id' => $state, 'name' => 'Noise City', 'status' => 1]);
    $this->put(route('admin.customers.addresses.update', [$customer, $address]), [...$payload, 'state_id' => $state, 'city_id' => $city, 'latitude' => 91, 'longitude' => 181])->assertSessionHasErrors(['state_id', 'latitude', 'longitude']);
    $this->put(route('admin.customers.addresses.update', [$customer, $address]), [...$payload, 'state_id' => null, 'city_id' => $city])->assertSessionHasErrors('city_id');
    $this->post(route('admin.customers.addresses.store', $customer), [...$payload, 'phone' => '', 'address' => ''])->assertSessionHasErrors(['phone', 'address']);
    $this->put(route('admin.customers.addresses.update', [$customer, $address]), [...$payload, 'address' => 'Changed', 'latitude' => 52.1, 'longitude' => 4.3, 'user_id' => 999999])->assertRedirect();
    expect((int) $address->fresh()->user_id)->toBe($customer->id)->and($address->fresh()->address)->toBe('Changed')->and((float) $address->fresh()->latitude)->toBe(52.1);
});

test('admin default changes affect only the bound customer and default addresses cannot be deleted', function () {
    actingAsStoreUser(createStoreStaff('edit_customer'));
    $customer = createStoreUser();
    $first = adminCustomerAddress($customer, true);
    $second = adminCustomerAddress($customer);
    $noise = adminCustomerAddress(createStoreUser(), true);
    $this->delete(route('admin.customers.addresses.destroy', [$customer, $first]))->assertForbidden();
    expect(Address::whereKey($first->id)->exists())->toBeTrue();
    $this->post(route('admin.customers.addresses.default', [$customer, $second]))->assertRedirect();
    expect((bool) $first->fresh()->set_default)->toBeFalse()->and((bool) $second->fresh()->set_default)->toBeTrue()->and((bool) $noise->fresh()->set_default)->toBeTrue();
    $this->delete(route('admin.customers.addresses.destroy', [$customer, $second]))->assertForbidden();
    expect(Address::whereKey($second->id)->exists())->toBeTrue()->and((bool) $second->fresh()->set_default)->toBeTrue();
    $this->delete(route('admin.customers.addresses.destroy', [$customer, $first]))->assertRedirect();
    expect(Address::whereKey($first->id)->exists())->toBeFalse();
});

foreach (['admin.addresses.states', 'admin.addresses.cities'] as $route) {
    test($route.' refuses unauthorized callers', function (string $caller) use ($route) {
        actingAsRefusedStoreCaller($caller);
        assertStoreCallerRefused($this->get(route($route)), $caller);
    })->with('refused store admin callers');
}

test('admin address options return narrow JSON filtered by parent and status', function () {
    actingAsStoreUser(createStoreStaff('edit_customer'));
    $country = adminCustomerAddressForm()['country_id'];
    $otherCountry = adminCustomerAddressForm()['country_id'];
    $state = DB::table('states')->insertGetId(['country_id' => $country, 'name' => '<State>', 'status' => 1]);
    $otherState = DB::table('states')->insertGetId(['country_id' => $otherCountry, 'name' => 'Noise', 'status' => 1]);
    DB::table('states')->insert(['country_id' => $country, 'name' => 'Disabled', 'status' => 0]);
    $city = DB::table('cities')->insertGetId(['state_id' => $state, 'name' => 'Amsterdam', 'status' => 1]);
    DB::table('cities')->insert(['state_id' => $otherState, 'name' => 'Noise', 'status' => 1]);
    $this->getJson(route('admin.addresses.states', ['country_id' => $country]))->assertExactJson([['id' => $state, 'name' => '<State>']]);
    $this->getJson(route('admin.addresses.cities', ['state_id' => $state]))->assertExactJson([['id' => $city, 'name' => 'Amsterdam']]);
    $this->getJson(route('admin.addresses.states', ['country_id' => 999999]))->assertUnprocessable()->assertJsonValidationErrors('country_id');
});

test('public address creation preserves own addresses and refuses forged customer ownership', function () {
    $owner = createStoreUser();
    $other = createStoreUser();
    actingAsStoreUser($owner);
    $this->post('/temp/addresses', adminCustomerAddressForm())->assertRedirect();
    expect(Address::where('user_id', $owner->id)->count())->toBe(1);
    $this->post('/temp/addresses', adminCustomerAddressForm(['customer_id' => $other->id]))->assertForbidden();
    expect(Address::where('user_id', $other->id)->count())->toBe(0);
});
