<?php

use App\Models\StoreUser;
use App\Notifications\EmailVerificationNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** @return array<string, list<string>> */
function registrationRows(): array
{
    $rows = [];
    foreach (Schema::getTableListing(schemaQualified: false) as $table) {
        $rows[$table] = DB::table($table)->get()->map(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR))->sort()->values()->all();
    }

    return $rows;
}

/** @return array<string, mixed> */
function registrationWithBusinessFields(array $overrides = []): array
{
    return [
        ...completeRegistration(),
        'tax_number' => 'NL123456789B01',
        'company_address' => 'Damrak 1, Amsterdam',
        'trade_license' => UploadedFile::fake()->createWithContent('license.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n"),
        ...$overrides,
    ];
}

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('local');
    Storage::disk('local')->put('uploads/licenses/existing.pdf', 'another customer document');
    Storage::disk('local')->put('outside.txt', 'unrelated private file');
    DB::table('countries')->insert(['code' => 'NL', 'name' => 'Netherlands', 'status' => 1]);
    createStoreUser('customer', 'existing@example.com')->forceFill([
        'tax_number' => 'EXISTING', 'company_address' => 'Existing address', 'trade_license' => 'existing.pdf',
    ])->save();

    // The legacy translate() helper creates missing dictionary rows. Warm the
    // response strings before taking exact snapshots of every database table.
    foreach (['Please confirm that you are not a robot.', 'Registration successful.', 'Email Already exists.', 'The trade license must be a PDF, Word document or PNG/JPEG image of at most 20 MB.'] as $message) {
        translate($message);
    }
});

test('registration accepts and retains each business field', function (string $field) {
    $payload = registrationWithBusinessFields();
    $this->post('/temp/register', [...completeRegistration(), $field => $payload[$field]])
        ->assertRedirect(route('home'))->assertSessionHasNoErrors();

    $customer = StoreUser::where('email', $payload['email'])->sole();
    if ($field === 'trade_license') {
        expect($customer->trade_license)->toMatch('/^[A-Za-z0-9]{40}\.pdf$/');
        Storage::disk('local')->assertExists('uploads/licenses/'.$customer->trade_license);
        expect(Storage::disk('local')->get('uploads/licenses/'.$customer->trade_license))->toBe(file_get_contents($payload[$field]->getRealPath()));
    } else {
        expect($customer->{$field})->toBe($payload[$field]);
    }
})->with(['tax_number', 'company_address', 'trade_license']);

test('registration saves only its own customer and default address with optional fields present or absent', function (bool $withOptional) {
    $before = registrationRows();
    $payload = $withOptional ? registrationWithBusinessFields() : completeRegistration();
    unset($payload['country_code']);
    $this->post('/temp/register', [
        ...$payload,
        'shipping_address' => 'ignored old input', 'bank_account_number' => 'ignored',
        'user_type' => 'admin', 'is_rep' => 1, 'admin_verified' => 1, 'registration_completed' => 1,
    ])->assertRedirect(route('home'))->assertSessionHasNoErrors();

    $customer = StoreUser::where('email', $payload['email'])->sole();
    expect($customer->tax_number)->toBe($withOptional ? $payload['tax_number'] : null)
        ->and($customer->company_address)->toBe($withOptional ? $payload['company_address'] : null)
        ->and($customer->user_type)->toBe('customer')
        ->and($customer->is_rep)->toBeFalse()
        ->and($customer->admin_verified)->toBeNull()
        ->and($customer->registration_completed)->toBeFalse()
        ->and($customer->shipping_address)->toBeNull()
        ->and($customer->bank_account_number)->toBeNull()
        ->and($customer->email_verified_at)->not->toBeNull()
        ->and(Auth::guard('store')->id())->toBe($customer->id)
        ->and(Auth::guard('web')->check())->toBeFalse();
    $address = DB::table('addresses')->where('user_id', $customer->id)->sole();
    expect($address->address)->toBeNull()
        ->and($address->country_id)->toBe(DB::table('countries')->where('code', 'NL')->value('id'))
        ->and($address->phone)->toBe($payload['phone'])
        ->and($address->set_default)->toBe(1);
    $after = registrationRows();
    expect(count($after['users']))->toBe(count($before['users']) + 1)
        ->and(count($after['addresses']))->toBe(count($before['addresses']) + 1)
        ->and(array_values(array_intersect($before['users'], $after['users'])))->toBe($before['users']);
    unset($before['users'], $before['addresses'], $after['users'], $after['addresses']);
    expect($after)->toBe($before);
    if ($withOptional) {
        expect($customer->trade_license)->toMatch('/^[A-Za-z0-9]{40}\.pdf$/');
        expect(Storage::disk('local')->allFiles())->toEqualCanonicalizing(['outside.txt', 'uploads/licenses/existing.pdf', 'uploads/licenses/'.$customer->trade_license]);
        expect(file_exists(public_path('uploads/licenses/'.$customer->trade_license)))->toBeFalse();
    } else {
        expect($customer->trade_license)->toBeNull()
            ->and(Storage::disk('local')->allFiles())->toEqualCanonicalizing(['outside.txt', 'uploads/licenses/existing.pdf']);
    }
    expect(Storage::disk('local')->get('uploads/licenses/existing.pdf'))->toBe('another customer document')
        ->and(Storage::disk('local')->get('outside.txt'))->toBe('unrelated private file');
})->with([true, false]);

test('registration accepts each text field at the representative form limit', function (string $field, int $limit) {
    $value = $field === 'email' ? str_repeat('a', 60).'@'.str_repeat('b', 60).'.'.str_repeat('c', $limit - 134).'.example.com' : str_repeat('é', $limit);
    $this->post('/temp/register', registrationWithBusinessFields([$field => $value]))->assertRedirect(route('home'))->assertSessionHasNoErrors();

    expect(StoreUser::where('email', $field === 'email' ? $value : 'new-customer@example.com')->sole()->{$field})->toBe($value);
})->with([
    ['name', 191], ['company_name', 191], ['email', 191], ['phone', 20], ['country', 30],
    ['tax_number', 100], ['company_address', 191],
]);

test('registration refuses one over each text limit without any database or file write', function (string $field, int $limit) {
    $value = $field === 'email' ? str_repeat('a', 60).'@'.str_repeat('b', 60).'.'.str_repeat('c', $limit - 133).'.example.com' : str_repeat('é', $limit + 1);
    $before = registrationRows();
    $this->postJson('/temp/register', registrationWithBusinessFields([$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);

    expect(registrationRows())->toBe($before)
        ->and(Storage::disk('local')->allFiles())->toEqualCanonicalizing(['outside.txt', 'uploads/licenses/existing.pdf'])
        ->and(Auth::guard('store')->check())->toBeFalse();
})->with([
    ['name', 191], ['company_name', 191], ['email', 191], ['phone', 20], ['country', 30],
    ['tax_number', 100], ['company_address', 191],
]);

test('registration rejects invalid optional fields before any database or file write', function (string $field, mixed $value) {
    $before = registrationRows();
    $this->postJson('/temp/register', registrationWithBusinessFields([$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);

    expect(registrationRows())->toBe($before)
        ->and(Storage::disk('local')->allFiles())->toEqualCanonicalizing(['outside.txt', 'uploads/licenses/existing.pdf'])
        ->and(Auth::guard('store')->check())->toBeFalse();
})->with([
    'tax array' => ['tax_number', ['NL123']],
    'address array' => ['company_address', ['Damrak']],
    'outside URL' => ['trade_license', 'https://example.com/license.pdf'],
    'existing private name' => ['trade_license', 'uploads/licenses/existing.pdf'],
    'license array' => ['trade_license', ['license.pdf']],
]);

test('registration rejects paths outside the store at the WAF without any write', function (string $path) {
    $before = registrationRows();
    $this->post('/temp/register', registrationWithBusinessFields(['trade_license' => $path]))->assertForbidden();

    expect(registrationRows())->toBe($before)
        ->and(Storage::disk('local')->allFiles())->toEqualCanonicalizing(['outside.txt', 'uploads/licenses/existing.pdf']);
})->with(['../../outside.txt', '/etc/passwd']);

test('registration enforces the actual license size at 20000 KiB and one KiB over', function (int $size, bool $accepted) {
    $file = UploadedFile::fake()->createWithContent('license.pdf', "%PDF-1.4\n".str_repeat('x', $size * 1024 - 9));
    $before = registrationRows();
    $response = $this->post('/temp/register', registrationWithBusinessFields(['trade_license' => $file]));
    if ($accepted) {
        $response->assertRedirect(route('home'))->assertSessionHasNoErrors();
        $customer = StoreUser::where('email', 'new-customer@example.com')->sole();
        expect(Storage::disk('local')->size('uploads/licenses/'.$customer->trade_license))->toBe($size * 1024);
    } else {
        $response->assertSessionHasErrors('trade_license');
        expect(registrationRows())->toBe($before)
            ->and(Storage::disk('local')->allFiles())->toEqualCanonicalizing(['outside.txt', 'uploads/licenses/existing.pdf']);
    }
})->with([[20000, true], [20001, false]]);

test('registration refuses invalid document content before creating anything', function (string $name, string $contents) {
    $before = registrationRows();
    $this->post('/temp/register', registrationWithBusinessFields([
        'trade_license' => UploadedFile::fake()->createWithContent($name, $contents),
    ]))->assertSessionHasErrors('trade_license');

    expect(registrationRows())->toBe($before)
        ->and(Storage::disk('local')->allFiles())->toEqualCanonicalizing(['outside.txt', 'uploads/licenses/existing.pdf']);
})->with([
    'text' => ['license.txt', 'plain text'],
    'disguised HTML' => ['license.pdf', '<html><body>not a license</body></html>'],
    'GIF' => ['license.gif', 'GIF89a'.str_repeat("\0", 20)],
]);

test('an existing registration email returns 422 without changing anything', function () {
    $before = registrationRows();
    $this->postJson('/temp/register', registrationWithBusinessFields(['email' => 'existing@example.com']))
        ->assertUnprocessable()->assertJsonValidationErrors('email');

    expect(registrationRows())->toBe($before)
        ->and(Storage::disk('local')->allFiles())->toEqualCanonicalizing(['outside.txt', 'uploads/licenses/existing.pdf']);
});

test('registration still requires an email when the phone addon is enabled', function () {
    DB::table('addons')->insert(['unique_identifier' => 'otp_system', 'activated' => 1]);
    $before = registrationRows();
    $this->postJson('/temp/register', registrationWithBusinessFields(['email' => '', 'country_code' => '31']))
        ->assertUnprocessable()->assertJsonValidationErrors('email');

    expect(registrationRows())->toBe($before)
        ->and(Storage::disk('local')->allFiles())->toEqualCanonicalizing(['outside.txt', 'uploads/licenses/existing.pdf']);
});

test('registration still sends verification and leaves the email unverified when verification is enabled', function () {
    Notification::fake();
    storeSetting('email_verification', 1);
    $this->post('/temp/register', registrationWithBusinessFields())->assertRedirect(route('home'))->assertSessionHasNoErrors();

    $customer = StoreUser::where('email', 'new-customer@example.com')->sole();
    expect($customer->email_verified_at)->toBeNull()
        ->and(Auth::guard('store')->id())->toBe($customer->id);
    Notification::assertSentTo($customer, EmailVerificationNotification::class);
    Notification::assertCount(1);
    Storage::disk('local')->assertExists('uploads/licenses/'.$customer->trade_license);
});

test('registration still permits thirty guest attempts per minute and isolates guest addresses', function () {
    $before = registrationRows();
    foreach (range(1, 30) as $attempt) {
        $this->postJson('/temp/register')->assertUnprocessable();
    }
    $this->postJson('/temp/register')->assertTooManyRequests();
    $this->withServerVariables(['REMOTE_ADDR' => '10.16.0.2'])->postJson('/temp/register')->assertUnprocessable();

    expect(registrationRows())->toBe($before);
});

test('registration renders the business fields as optional with their server limits and multipart encoding', function () {
    $xpath = htmlXPath($this->get('/temp/register')->assertOk());
    $form = $xpath->query('//form[@id="reg-form"]')->item(0);
    expect($form->getAttribute('enctype'))->toBe('multipart/form-data');
    foreach (['tax_number' => '100', 'company_address' => '191', 'trade_license' => ''] as $name => $limit) {
        $control = $xpath->query('.//input[@name="'.$name.'"]', $form)->item(0);
        expect($control->hasAttribute('required'))->toBeFalse()
            ->and($control->getAttribute('maxlength'))->toBe($limit)
            ->and($control->getAttribute('id'))->toBe($name)
            ->and($xpath->query('.//label[@for="'.$name.'"]//*[@data-optional-mark]', $form)->length)->toBe(1);
    }
    expect($xpath->query('.//input[@name="trade_license"]', $form)->item(0)->getAttribute('accept'))->toBe('.pdf,.doc,.docx,.png,.jpg,.jpeg');
});

test('registration renders no shipping address control', function () {
    $xpath = htmlXPath($this->get('/temp/register')->assertOk());
    expect($xpath->query('//form[@id="reg-form"]//*[@name="shipping_address"]')->length)->toBe(0);
});

test('both registration forms render the required text limits enforced by the endpoint', function (string $url) {
    $xpath = htmlXPath($this->get($url)->assertOk());
    foreach (['name' => '191', 'company_name' => '191', 'email' => '191', 'phone' => '20'] as $name => $limit) {
        expect($xpath->query('//form[@id="reg-form"]//input[@name="'.$name.'"]')->item(0)->getAttribute('maxlength'))->toBe($limit);
    }
})->with(['/temp/register', '/temp/users/registration']);

test('neither registration view renders country code with the phone addon enabled', function (string $url) {
    DB::table('addons')->insert(['unique_identifier' => 'otp_system', 'activated' => 1]);
    $html = $this->get($url)->assertOk()->getContent();
    $xpath = htmlXPath($html);

    expect($xpath->query('//form[@id="reg-form"]//*[@name="country_code"]')->length)->toBe(0);
})->with(['/temp/register', '/temp/users/registration']);

test('registration keeps optional values and shows field errors after a refusal', function () {
    $this->from('/temp/register')->post('/temp/register', registrationWithBusinessFields([
        'tax_number' => str_repeat('x', 101), 'company_address' => 'Keep this address',
    ]))->assertRedirect('/temp/register');
    $xpath = htmlXPath($this->get('/temp/register')->assertOk());
    expect($xpath->query('//input[@name="company_address"]')->item(0)->getAttribute('value'))->toBe('Keep this address')
        ->and($xpath->query('//input[@name="tax_number"]/following-sibling::*[@role="alert"]')->length)->toBe(1)
        ->and($xpath->query('//input[@name="trade_license"]')->item(0)->getAttribute('value'))->toBe('');
});
