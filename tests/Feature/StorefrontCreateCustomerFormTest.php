<?php

use App\Models\StoreUser;
use App\Services\UserService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A representative's "Add Customer" form (shop.frontend.user.create_user at
 * /temp/create_customer, posting to CustomerController::store_customer). The
 * endpoint's inline rules already require all twelve fields, so the form only
 * gains what tells the representative so: the mark and `required` on each,
 * the legend, labels tied to their controls and each error under its field
 * (.ai/rules/form-required-fields.md, store-backoffice-migration.md §47).
 */
const CREATE_CUSTOMER_REQUIRED_FIELDS = [
    'name', 'company_name', 'email', 'phone', 'country', 'tax_number', 'bank_account_number',
    'company_address', 'shipping_address', 'trade_license', 'password', 'password_confirmation',
];

function actingAsRepresentative(): StoreUser
{
    $representative = createStoreUser();
    $representative->forceFill(['is_rep' => true])->save();

    return actingAsStoreUser($representative->fresh());
}

/**
 * @return array<string, mixed>
 */
function createCustomerPayload(array $overrides = []): array
{
    return [
        'name' => 'New Customer', 'company_name' => 'Acme BV', 'email' => 'new-customer@example.com', 'phone' => '+31612345678',
        'country' => 'Netherlands', 'tax_number' => 'NL123456789B01', 'bank_account_number' => 'NL91ABNA0417164300',
        'company_address' => 'Damrak 1, Amsterdam', 'shipping_address' => 'Damrak 2, Amsterdam',
        'trade_license' => UploadedFile::fake()->createWithContent('license.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n"),
        'password' => 'secret123', 'password_confirmation' => 'secret123', ...$overrides,
    ];
}

beforeEach(function (): void {
    Storage::fake('local');
    DB::table('countries')->insert(['code' => 'NL', 'name' => 'Netherlands', 'status' => 1]);
    // The account is mirrored to an external system; not under test here.
    $this->mock(UserService::class)->shouldReceive('createCustomerOnOtajer');
});

test('marks and requires every field the endpoint requires, under the legend', function () {
    actingAsRepresentative();

    $xpath = htmlXPath($this->get('/temp/create_customer')->assertOk());
    $form = formPostingTo($xpath, '/temp/store_customer');
    $marks = formFieldMarks($xpath, $form);

    expect(array_keys($marks))->toEqualCanonicalizing(CREATE_CUSTOMER_REQUIRED_FIELDS);
    foreach (CREATE_CUSTOMER_REQUIRED_FIELDS as $field) {
        expect($marks[$field])->toMatchArray(['required' => true, 'mark' => true, 'optional' => false], "{$field} is marked and required");
    }
    expect($xpath->query('.//*[@data-required-legend]', $form)->length)->toBe(1)
        ->and($xpath->query('.//label[preceding::*[@data-required-legend]]', $form)->length)->toBe($xpath->query('.//label', $form)->length);
});

test('ties every label to its control', function () {
    actingAsRepresentative();

    $xpath = htmlXPath($this->get('/temp/create_customer')->assertOk());
    $form = formPostingTo($xpath, '/temp/store_customer');

    foreach (CREATE_CUSTOMER_REQUIRED_FIELDS as $field) {
        $control = $xpath->query('.//*[self::input or self::select][@name="'.$field.'"]', $form)->item(0);
        expect($xpath->query('.//label[@for="'.$control->getAttribute('id').'"]', $form)->length)->toBe(1, "{$field} has its label");
    }
});

test('creates the customer as before', function () {
    actingAsRepresentative();

    $this->post('/temp/store_customer', createCustomerPayload())->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();

    expect(StoreUser::where('email', 'new-customer@example.com')->sole()->only(['name', 'company_name', 'user_type']))
        ->toBe(['name' => 'New Customer', 'company_name' => 'Acme BV', 'user_type' => 'customer'])
        ->and(flashedMessage())->toBe('Customer added successfully.');
});

test('refuses each required field left out with that field\'s error and creates nothing', function (string $field) {
    actingAsRepresentative();
    $payload = createCustomerPayload();
    unset($payload[$field]);

    $this->from('/temp/create_customer')->post('/temp/store_customer', $payload)
        ->assertRedirect('/temp/create_customer')
        ->assertSessionHasErrors([$field === 'password_confirmation' ? 'password' : $field]);

    expect(StoreUser::where('email', 'new-customer@example.com')->exists())->toBeFalse();
})->with(CREATE_CUSTOMER_REQUIRED_FIELDS);

test('a refused form shows each error under its field and keeps the input', function () {
    actingAsRepresentative();

    $this->from('/temp/create_customer')->post('/temp/store_customer', createCustomerPayload(['country' => '', 'tax_number' => '']));

    $xpath = htmlXPath($this->get('/temp/create_customer')->assertOk());
    $form = formPostingTo($xpath, '/temp/store_customer');
    $error = fn (string $field) => trim($xpath->query('.//*[@name="'.$field.'"]/following-sibling::*[@role="alert"]', $form)->item(0)?->textContent ?? '');

    expect($error('tax_number'))->toBe('The tax number field is required.')
        ->and($error('country'))->toBe('The country field is required.')
        // The country select is a bootstrap-select, so its error is shown without the .is-invalid sibling rule.
        ->and($xpath->query('.//select[@name="country"]/following-sibling::*[@role="alert"]', $form)->item(0)->getAttribute('class'))->toContain('d-block')
        ->and($xpath->query('.//input[@name="company_name"]', $form)->item(0)->getAttribute('value'))->toBe('Acme BV');
});
