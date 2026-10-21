<?php

use App\Models\StoreUser;
use Illuminate\Support\Facades\DB;

/**
 * Both storefront registration forms (`/temp/register`, shop.auth.register,
 * and `/temp/users/registration`, shop.frontend.user_registration) post to
 * RegisterController::register, whose validator requires name, company name,
 * email, phone, country and a confirmed password. Those fields, and only
 * those, carry the native `required` attribute and the required mark; the
 * other fields say "(optional)"; a legend explains the asterisk
 * (.ai/rules/form-required-fields.md).
 */
const REGISTRATION_REQUIRED_FIELDS = ['name', 'company_name', 'email', 'phone', 'country', 'password', 'password_confirmation'];

/**
 * @return array<string, array{0: string, 1: list<string>}>
 */
function registrationPages(): array
{
    return [
        'shop.auth.register' => ['/temp/register', ['tax_number', 'company_address', 'shipping_address', 'trade_license']],
        // Its tax number, bank account, address and trade license fields are commented out.
        'shop.frontend.user_registration' => ['/temp/users/registration', []],
    ];
}

/**
 * @return array<string, string>
 */
function completeRegistration(array $overrides = []): array
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

/**
 * The visible controls of the registration form, by name.
 *
 * @return array<string, DOMElement>
 */
function registrationControls(DOMXPath $xpath): array
{
    $controls = [];
    foreach ($xpath->query('//form[@id="reg-form"]//*[self::input or self::select or self::textarea][@name]') as $control) {
        if (in_array($control->getAttribute('type'), ['hidden', 'submit'], true)) {
            continue;
        }
        $controls[$control->getAttribute('name')] ??= $control;
    }

    return $controls;
}

function registrationLabel(DOMXPath $xpath, DOMElement $control): DOMElement
{
    $label = $xpath->query('preceding-sibling::label[1]', $control)->item(0);
    expect($label)->toBeInstanceOf(DOMElement::class);

    return $label;
}

test('the registration form marks exactly the fields the validator requires', function (string $url, array $optional) {
    $xpath = htmlXPath($this->get($url)->assertOk()->getContent());
    $controls = registrationControls($xpath);

    expect(array_keys($controls))->toEqualCanonicalizing([...REGISTRATION_REQUIRED_FIELDS, ...$optional]);

    foreach (REGISTRATION_REQUIRED_FIELDS as $field) {
        $control = $controls[$field];
        $label = registrationLabel($xpath, $control);

        expect($control->hasAttribute('required'))->toBeTrue("{$field} has no required attribute")
            ->and($xpath->query('.//*[@data-required-mark]/span[@aria-hidden="true"]', $label)->item(0)?->textContent)->toBe('*', "{$field} has no asterisk")
            ->and($xpath->query('.//*[@data-required-mark]/span[@class="sr-only"]', $label)->item(0)?->textContent)->toBe('required')
            ->and($xpath->query('.//*[@data-optional-mark]', $label)->length)->toBe(0);
    }

    foreach ($optional as $field) {
        $control = $controls[$field];
        $label = registrationLabel($xpath, $control);

        expect($control->hasAttribute('required'))->toBeFalse("{$field} should be optional")
            ->and($xpath->query('.//*[@data-optional-mark]', $label)->item(0)?->textContent)->toBe('(optional)')
            ->and($xpath->query('.//*[@data-required-mark]', $label)->length)->toBe(0);
    }
})->with(registrationPages());

test('the registration form explains the asterisk before its first field', function (string $url) {
    $xpath = htmlXPath($this->get($url)->assertOk()->getContent());

    $legend = $xpath->query('//form[@id="reg-form"]//*[@data-required-legend]');
    expect($legend->length)->toBe(1)
        ->and(trim(preg_replace('/\s+/', ' ', $legend->item(0)->textContent)))->toBe('* Required fields')
        ->and($xpath->query('span[@aria-hidden="true"]', $legend->item(0))->item(0)?->textContent)->toBe('*')
        ->and($xpath->query('//form[@id="reg-form"]//label[preceding::*[@data-required-legend]]')->length)
        ->toBe($xpath->query('//form[@id="reg-form"]//label')->length);
})->with(registrationPages());

test('registering without a required field returns that field\'s error', function (string $field, string $errorKey) {
    $form = completeRegistration();
    unset($form[$field]);

    $this->from('/temp/users/registration')
        ->post('/temp/register', $form)
        ->assertRedirect('/temp/users/registration')
        ->assertSessionHasErrors($errorKey);

    expect(StoreUser::where('email', 'new-customer@example.com')->exists())->toBeFalse();
})->with([
    'name' => ['name', 'name'],
    'company name' => ['company_name', 'company_name'],
    'email' => ['email', 'email'],
    'phone' => ['phone', 'phone'],
    'country' => ['country', 'country'],
    'password' => ['password', 'password'],
    'password confirmation' => ['password_confirmation', 'password'],
]);

test('the optional registration fields may be left out', function () {
    DB::table('countries')->insert(['code' => 'NL', 'name' => 'Netherlands', 'status' => 1]);

    $this->post('/temp/register', completeRegistration())->assertSessionHasNoErrors();

    expect(StoreUser::where('email', 'new-customer@example.com')->exists())->toBeTrue();
});
