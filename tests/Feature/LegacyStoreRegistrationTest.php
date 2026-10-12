<?php

use App\Models\StoreUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Registration through the storefront's own form (/temp/users/registration,
 * which posts to the store's `register` route). It writes columns the store's
 * later migrations added to `users` (company_name, registration_completed)
 * and a default address with no state, which `shop.sql` required.
 */
function registrationForm(array $overrides = []): array
{
    return [
        'name' => 'New Customer',
        'company_name' => 'Acme BV',
        'email' => 'new-customer@example.com',
        'phone' => '612345678',
        'country_code' => '31',
        'country' => 'Netherlands',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        ...$overrides,
    ];
}

test('the registration form posts to the store register route', function () {
    $this->get('/temp/users/registration')
        ->assertOk()
        ->assertSee('action="'.route('register').'"', false);
});

test('a customer can register and is signed in on the store guard', function () {
    $countryId = DB::table('countries')->insertGetId(['code' => 'NL', 'name' => 'Netherlands', 'status' => 1]);

    $this->post('/temp/register', registrationForm())->assertRedirect(route('home'));

    $customer = StoreUser::where('email', 'new-customer@example.com')->sole();

    expect($customer->only(['name', 'company_name', 'user_type', 'phone', 'country']))->toBe([
        'name' => 'New Customer',
        'company_name' => 'Acme BV',
        'user_type' => 'customer',
        'phone' => '612345678',
        'country' => 'Netherlands',
    ])
        ->and($customer->registration_completed)->toBeFalse()
        ->and($customer->email_verified_at)->not->toBeNull()
        ->and(Auth::guard('store')->id())->toBe($customer->id)
        ->and(DB::table('addresses')->where('user_id', $customer->id)->get(['country_id', 'state_id', 'phone', 'set_default'])->map(fn ($address) => (array) $address)->all())->toBe([
            ['country_id' => $countryId, 'state_id' => null, 'phone' => '612345678', 'set_default' => 1],
        ]);
});

test('registering with an email that is taken creates no second account', function () {
    createStoreUser('customer', 'new-customer@example.com');

    $this->from('/temp/users/registration')
        ->post('/temp/register', registrationForm(['name' => 'Someone Else']))
        ->assertRedirect('/temp/users/registration');

    expect(StoreUser::where('email', 'new-customer@example.com')->pluck('name')->all())->toBe(['Customer Tester'])
        ->and(Auth::guard('store')->check())->toBeFalse();
});

test('registration requires a company name', function () {
    $this->from('/temp/users/registration')
        ->post('/temp/register', registrationForm(['company_name' => '']))
        ->assertRedirect('/temp/users/registration')
        ->assertSessionHasErrors('company_name');

    expect(StoreUser::where('email', 'new-customer@example.com')->exists())->toBeFalse();
});
