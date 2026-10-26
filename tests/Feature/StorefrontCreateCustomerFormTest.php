<?php

use App\Models\StoreUser;
use App\Services\UserService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A representative's "Add Customer" form (shop.frontend.user.create_user at
 * /temp/create_customer, posting to CustomerController::store_customer). The
 * endpoint requires all twelve fields (StoreRepresentativeCustomerRequest
 * since §48, inline rules before), and the form says so: the mark and
 * `required` on each, the legend, labels tied to their controls and each
 * error under its field (.ai/rules/form-required-fields.md,
 * store-backoffice-migration.md §47).
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
    // An account whose email is taken, for the unique rule.
    createStoreUser('seller', 'taken@example.com');
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

/**
 * StoreCustomerByRepresentativeRequest holds each field to its column:
 * users.name / users.email varchar(191), users.phone varchar(20),
 * users.country varchar(30), company name/company address varchar(191), shipping address rule max:255, the tax and
 * bank numbers to the 100 the rules always allowed, and the trade license to
 * the kinds TradeLicenseStorage keeps (store-backoffice-migration.md §48).
 */
describe('the limits', function () {
    test('takes every field up to its column', function () {
        actingAsRepresentative();
        DB::table('countries')->insert(['code' => 'XX', 'name' => str_repeat('c', 30), 'status' => 1]);

        $this->post('/temp/store_customer', createCustomerPayload([
            'name' => str_repeat('é', 191), 'email' => str_repeat('a', 179).'@example.com', 'phone' => str_repeat('1', 20),
            'country' => str_repeat('c', 30), 'company_name' => str_repeat('ü', 191), 'tax_number' => str_repeat('t', 100),
            'bank_account_number' => str_repeat('b', 100), 'company_address' => str_repeat('a', 191), 'shipping_address' => str_repeat('s', 255),
        ]))->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();

        $customer = StoreUser::where('email', str_repeat('a', 179).'@example.com')->sole();
        expect(mb_strlen($customer->name))->toBe(191)
            ->and(mb_strlen($customer->company_name))->toBe(191)
            ->and($customer->phone)->toBe(str_repeat('1', 20))
            ->and($customer->country)->toBe(str_repeat('c', 30));
    });

    test('refuses a field one character past its column and creates nothing', function (array $input, string $field) {
        actingAsRepresentative();

        $this->from('/temp/create_customer')->post('/temp/store_customer', createCustomerPayload($input))
            ->assertRedirect('/temp/create_customer')
            ->assertSessionHasErrors([$field]);

        expect(StoreUser::count())->toBe(2); // The representative and the taken account.
        Storage::disk('local')->assertDirectoryEmpty('uploads/licenses');
    })->with([
        'name, users.name' => [['name' => str_repeat('a', 192)], 'name'],
        'a multibyte name' => [['name' => str_repeat('é', 192)], 'name'],
        'email, users.email' => [['email' => str_repeat('a', 180).'@example.com'], 'email'],
        'phone, users.phone' => [['phone' => str_repeat('1', 21)], 'phone'],
        'country, users.country' => [['country' => str_repeat('c', 31)], 'country'],
        'company name, users.company_name' => [['company_name' => str_repeat('a', 192)], 'company_name'],
        'tax number' => [['tax_number' => str_repeat('t', 101)], 'tax_number'],
        'bank account number' => [['bank_account_number' => str_repeat('b', 101)], 'bank_account_number'],
        'company address, users.company_address' => [['company_address' => str_repeat('a', 192)], 'company_address'],
        'shipping address, users.shipping_address' => [['shipping_address' => str_repeat('s', 256)], 'shipping_address'],
    ]);

    test('refuses wrong types and creates nothing', function (array $input, string $field) {
        actingAsRepresentative();

        $this->post('/temp/store_customer', createCustomerPayload($input))->assertSessionHasErrors([$field]);

        expect(StoreUser::count())->toBe(2); // The representative and the taken account.
    })->with([
        'a phone that is an array' => [['phone' => ['+31612345678']], 'phone'],
        'a country that is an array' => [['country' => ['Netherlands']], 'country'],
        'a tax number that is an array' => [['tax_number' => ['NL1']], 'tax_number'],
        'a bank account number that is an array' => [['bank_account_number' => ['NL1']], 'bank_account_number'],
        'an email that is an array' => [['email' => ['a@example.com']], 'email'],
        'a trade license that is text' => [['trade_license' => 'license.pdf'], 'trade_license'],
        'an email that already has an account' => [['email' => 'taken@example.com'], 'email'],
    ]);

    test('takes a trade license of each kind the storage keeps', function (string $name, string $contents) {
        actingAsRepresentative();

        $this->post('/temp/store_customer', createCustomerPayload(['trade_license' => UploadedFile::fake()->createWithContent($name, $contents)]))
            ->assertSessionHasNoErrors();

        expect(StoreUser::where('email', 'new-customer@example.com')->sole()->trade_license)->toEndWith('.'.pathinfo($name, PATHINFO_EXTENSION));
    })->with([
        'pdf' => ['license.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n"],
        'png' => ['license.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')],
        'jpg' => ['license.jpg', base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=')],
    ]);

    test('refuses a trade license of a kind the storage does not keep, before anything is written', function (string $name, string $contents) {
        actingAsRepresentative();

        $this->post('/temp/store_customer', createCustomerPayload(['trade_license' => UploadedFile::fake()->createWithContent($name, $contents)]))
            ->assertSessionHasErrors(['trade_license']);

        // The representative and the taken account, no address.
        expect(StoreUser::count())->toBe(2)
            ->and(DB::table('addresses')->count())->toBe(0);
        Storage::disk('local')->assertDirectoryEmpty('uploads/licenses');
    })->with([
        // The old list allowed "bmb" (a typo); TradeLicenseStorage never kept bitmaps.
        'a bitmap' => ['license.bmp', 'BM'.str_repeat("\0", 60)],
        'a GIF' => ['license.gif', 'GIF89a'.str_repeat("\0", 20)],
        'a text file' => ['license.txt', 'plain text'],
    ]);

    test('a signed-in account that is not a representative is refused before its input is read', function () {
        actingAsStoreUser(createStoreUser());

        $this->post('/temp/store_customer', ['name' => ['x']])->assertForbidden();

        expect(StoreUser::count())->toBe(2); // The representative and the taken account.
    });

    test('the form limits each field in HTML as the request does', function () {
        actingAsRepresentative();

        $xpath = htmlXPath($this->get('/temp/create_customer')->assertOk());
        $form = formPostingTo($xpath, '/temp/store_customer');
        $attribute = fn (string $name, string $attribute) => $xpath->query('.//input[@name="'.$name.'"]', $form)->item(0)->getAttribute($attribute);

        expect(array_map(fn (string $name) => $attribute($name, 'maxlength'), ['name', 'email', 'phone', 'company_name', 'tax_number', 'bank_account_number', 'company_address', 'shipping_address']))
            ->toBe(['191', '191', '20', '191', '100', '100', '191', '255'])
            ->and($attribute('trade_license', 'accept'))->toBe('.pdf,.doc,.docx,.png,.jpg,.jpeg');
    });
});
