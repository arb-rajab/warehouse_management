<?php

use App\Models\StoreUser;
use App\Services\TradeLicenseStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;

/**
 * A customer's trade licence is a business document. It was saved as
 * `uploads/licenses/<the client's filename>`, so one customer's upload overwrote
 * another's of the same name, nothing checked what the file was, and the admin
 * download built its path from the request. TradeLicenseStorage keeps it on the
 * private disk under a random name, with the extension its content has, and
 * hands it back only through a controller that knows who is asking.
 */
function licenseFile(string $name = 'licence.pdf', ?string $contents = null): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents ?? "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
}

function uploadLicenseAs(StoreUser $customer, UploadedFile $file): TestResponse
{
    actingAsStoreUser($customer);

    // The profile form posts every field, and the controller writes each one.
    return test()->post(route('user.profile.update'), [
        'name' => $customer->name, 'company_name' => 'Acme BV', 'address' => 'Damrak 1', 'country' => 'Netherlands',
        'city' => 'Amsterdam', 'postal_code' => '1012', 'phone' => $customer->phone, 'tax_number' => 'NL123',
        'bank_account_number' => 'NL00BANK0123456789', 'company_address' => 'Damrak 1', 'shipping_address' => 'Damrak 1',
        'trade_license' => $file,
    ]);
}

test('a licence is stored privately under a random name, with the extension its content has', function () {
    Storage::fake('local');
    $customer = createStoreUser('customer');

    uploadLicenseAs($customer, licenseFile('My Licence.PDF'))->assertRedirect();

    $name = $customer->fresh()->trade_license;

    expect($name)->toMatch('/^[A-Za-z0-9]{40}\.pdf$/');
    Storage::disk('local')->assertExists("uploads/licenses/$name");
    expect(file_exists(public_path("uploads/licenses/$name")))->toBeFalse();
});

test('two customers uploading the same filename do not overwrite each other', function () {
    Storage::fake('local');
    $first = createStoreUser('customer');
    $second = createStoreUser('customer');

    uploadLicenseAs($first, licenseFile('licence.pdf', "%PDF-1.4\nfirst\n%%EOF\n"));
    uploadLicenseAs($second, licenseFile('licence.pdf', "%PDF-1.4\nsecond\n%%EOF\n"));

    $firstName = $first->fresh()->trade_license;
    $secondName = $second->fresh()->trade_license;

    expect($firstName)->not->toBe($secondName)
        ->and(Storage::disk('local')->get("uploads/licenses/$firstName"))->toContain('first')
        ->and(Storage::disk('local')->get("uploads/licenses/$secondName"))->toContain('second');
});

test('a Word document that a detector reports as a zip is accepted', function () {
    Storage::fake('local');
    $zipPath = tempnam(sys_get_temp_dir(), 'docx');
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::OVERWRITE);
    $zip->addFromString('word/document.xml', '<w:document/>');
    $zip->addFromString('[Content_Types].xml', '<Types/>');
    $zip->close();
    $customer = createStoreUser('customer');

    uploadLicenseAs($customer, licenseFile('licence.docx', (string) file_get_contents($zipPath)))->assertSessionHasNoErrors();
    unlink($zipPath);

    expect($customer->fresh()->trade_license)->toMatch('/^[A-Za-z0-9]{40}\.docx$/');
});

test('a licence that is not a PDF, Word document or image is refused and stores nothing', function (string $filename, string $contents) {
    Storage::fake('local');
    $customer = createStoreUser('customer');

    uploadLicenseAs($customer, licenseFile($filename, $contents))->assertSessionHasErrors('trade_license');

    expect($customer->fresh()->trade_license)->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'html called pdf' => ['licence.pdf', '<html><script>alert(1)</script></html>'],
    'php called pdf' => ['licence.pdf', '<?php system($_GET[1]); ?>'],
    'text called png' => ['licence.png', 'not an image'],
    'a zip that is not a word document' => ['licence.docx', "PK\x03\x04 not really"],
]);

test('a customer downloads their own licence, and a customer with none gets a 404', function () {
    Storage::fake('local');
    $owner = createStoreUser('customer');
    $stranger = createStoreUser('customer');
    uploadLicenseAs($owner, licenseFile());

    $this->get(route('profile.trade_license'))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

    actingAsStoreUser($stranger);

    $this->get(route('profile.trade_license'))->assertNotFound();
});

test('a visitor who is not signed in cannot download a licence', function () {
    expect($this->get(route('profile.trade_license'))->getStatusCode())->toBeIn([302, 401, 403]);
});

test('the admin download sends the stored file to a staff member with the customer permission and no one else', function () {
    Storage::fake('local');
    Permission::findOrCreate('view_all_customers', 'store');
    $owner = createStoreUser('customer');
    uploadLicenseAs($owner, licenseFile());
    $name = $owner->fresh()->trade_license;

    $staff = createStoreUser('staff');
    actingAsStoreUser($staff);

    $this->get(route('download.trade_license', ['filename' => $name]))->assertForbidden();

    $staff->givePermissionTo('view_all_customers');

    $this->get(route('download.trade_license', ['filename' => $name]))->assertOk();
    $this->get(route('download.trade_license', ['filename' => 'missing.pdf']))->assertNotFound();
});

test('a name that points outside the licence directory finds nothing', function (?string $hostile) {
    Storage::fake('local');
    Storage::disk('local')->put('secret.txt', 'private');
    Storage::disk('local')->put('uploads/licenses/real.pdf', 'x');
    $licenses = app(TradeLicenseStorage::class);

    expect($licenses->download($hostile))->toBeNull();
})->with([
    'parent directory' => ['../secret.txt'],
    'deep traversal' => ['../../../../etc/passwd'],
    'windows separators' => ['..\\..\\secret.txt'],
    'the directory itself' => ['.'],
    'empty' => [''],
    'null' => [null],
]);
