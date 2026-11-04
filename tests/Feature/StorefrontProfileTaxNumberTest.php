<?php

use App\Models\StoreUser;
use Illuminate\Testing\TestResponse;

/**
 * The storefront profile form (shop.frontend.user.profile, posting to
 * HomeController::userProfileUpdate) wrote whatever tax number it was sent.
 * The tax number is the customer's unique business identifier now, so the
 * form refuses one another customer has and shows the error under the field.
 */
function postProfileTaxNumber(StoreUser $customer, ?string $taxNumber): TestResponse
{
    actingAsStoreUser($customer);

    return test()->from(route('profile'))->post(route('user.profile.update'), [
        'name' => 'Renamed', 'company_name' => 'Acme BV', 'phone' => $customer->phone, 'tax_number' => $taxNumber,
    ]);
}

test('a tax number another customer already has is refused, shown under the field, and nothing changes', function () {
    $other = createStoreUser('customer');
    $other->forceFill(['tax_number' => 'NL-TAKEN'])->save();
    $customer = createStoreUser('customer');
    $customer->forceFill(['name' => 'Before', 'tax_number' => 'NL-OWN'])->save();

    postProfileTaxNumber($customer, 'NL-TAKEN')->assertRedirect(route('profile'));

    $xpath = htmlXPath($this->get(route('profile'))->assertOk());
    $input = $xpath->query('//input[@name="tax_number"]')->item(0);

    expect(trim($xpath->query('//input[@name="tax_number"]/following-sibling::*[@role="alert"]')->item(0)?->textContent ?? ''))
        ->toBe('This tax number is already registered to another customer.')
        ->and($input->getAttribute('value'))->toBe('NL-TAKEN')
        ->and($input->getAttribute('class'))->toContain('is-invalid')
        ->and($customer->fresh())->name->toBe('Before')->tax_number->toBe('NL-OWN')
        ->and($other->fresh()->tax_number)->toBe('NL-TAKEN');
});

test('a customer can resubmit their own tax number', function () {
    $customer = createStoreUser('customer');
    $customer->forceFill(['tax_number' => 'NL-OWN'])->save();

    postProfileTaxNumber($customer, 'NL-OWN')->assertSessionHasNoErrors();

    expect($customer->fresh())->name->toBe('Renamed')->tax_number->toBe('NL-OWN');
});

test('a blank tax number clears it to null', function () {
    $customer = createStoreUser('customer');
    $customer->forceFill(['tax_number' => 'NL-OWN'])->save();

    postProfileTaxNumber($customer, '   ')->assertSessionHasNoErrors();

    expect($customer->fresh()->tax_number)->toBeNull();
});

test('the profile form shows the saved tax number without an error', function () {
    $customer = createStoreUser('customer');
    $customer->forceFill(['tax_number' => 'NL-OWN'])->save();
    actingAsStoreUser($customer->fresh());

    $xpath = htmlXPath($this->get(route('profile'))->assertOk());

    expect($xpath->query('//input[@name="tax_number"]')->item(0)->getAttribute('value'))->toBe('NL-OWN')
        ->and($xpath->query('//input[@name="tax_number"]/following-sibling::*[@role="alert"]')->length)->toBe(0)
        ->and($xpath->query('//label[@for="profile_tax_number"]')->length)->toBe(1);
});
