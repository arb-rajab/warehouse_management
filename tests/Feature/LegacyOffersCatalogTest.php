<?php

use App\Models\OffersCatalog;
use App\Services\OffersCatalogStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The offers catalogue was saved under the client's filename (any file, no
 * check) on the private disk and read back from a public path it was never
 * written to, so the download always 404ed, and deleting one crashed when the
 * file was not there. OffersCatalogStorage keeps it as a content-checked PDF
 * under a random name and sends it from where it is.
 */
function catalogFile(string $name = 'Offers June.pdf', ?string $contents = null): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents ?? "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
}

test('an admin uploads the catalogue and the admin download sends it', function () {
    Storage::fake('local');
    actingAsStoreUser(createStoreAdmin());

    $this->post(route('offers.store'), ['catalog' => catalogFile()])->assertRedirect();

    $catalog = OffersCatalog::sole();

    expect($catalog->file)->toMatch('/^[A-Za-z0-9]{40}\.pdf$/');
    Storage::disk('local')->assertExists("uploads/offers/catalogs/{$catalog->file}");

    $this->get('/temp/admin/offers/download')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('a visitor who is not signed in can download the catalogue, which is the shop\'s to hand out', function () {
    Storage::fake('local');
    Storage::disk('local')->put('uploads/offers/catalogs/stored.pdf', 'pdf bytes');
    OffersCatalog::create(['file' => 'stored.pdf']);

    $this->get('/temp/offers/download')->assertOk();
});

test('a catalogue that is not a PDF is refused and stores nothing', function (string $filename, string $contents) {
    Storage::fake('local');
    actingAsStoreUser(createStoreAdmin());

    $this->post(route('offers.store'), ['catalog' => catalogFile($filename, $contents)])->assertSessionHasErrors('catalog');

    expect(OffersCatalog::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'html called pdf' => ['offers.pdf', '<html><script>alert(1)</script></html>'],
    'php called pdf' => ['offers.pdf', '<?php system($_GET[1]); ?>'],
    'an svg' => ['offers.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>'],
]);

test('only one catalogue can be held, and the second upload stores nothing', function () {
    Storage::fake('local');
    actingAsStoreUser(createStoreAdmin());

    $this->post(route('offers.store'), ['catalog' => catalogFile()]);
    $this->post(route('offers.store'), ['catalog' => catalogFile('Second.pdf')]);

    expect(OffersCatalog::count())->toBe(1)
        ->and(Storage::disk('local')->allFiles())->toHaveCount(1);
});

test('the download answers 404 when there is no catalogue, or its file is gone', function () {
    Storage::fake('local');

    $this->get('/temp/offers/download')->assertNotFound();

    OffersCatalog::create(['file' => 'gone.pdf']);

    $this->get('/temp/offers/download')->assertNotFound();
});

test('deleting a catalogue removes the file and the record, and survives a file that is already gone', function () {
    Storage::fake('local');
    actingAsStoreUser(createStoreAdmin());
    $this->post(route('offers.store'), ['catalog' => catalogFile()]);
    $catalog = OffersCatalog::sole();

    $this->post(route('offers.destroy', $catalog->id))->assertRedirect();

    expect(OffersCatalog::count())->toBe(0);
    Storage::disk('local')->assertMissing("uploads/offers/catalogs/{$catalog->file}");

    $orphan = OffersCatalog::create(['file' => 'orphan.pdf']);

    $this->post(route('offers.destroy', $orphan->id))->assertRedirect();

    expect(OffersCatalog::count())->toBe(0);
});

test('a name that points outside the catalogue directory finds nothing', function (string $hostile) {
    Storage::fake('local');
    Storage::disk('local')->put('secret.txt', 'private');

    expect(app(OffersCatalogStorage::class)->download($hostile))->toBeNull();

    app(OffersCatalogStorage::class)->delete($hostile);

    Storage::disk('local')->assertExists('secret.txt');
})->with(['parent directory' => ['../../secret.txt'], 'windows separators' => ['..\\..\\secret.txt'], 'the directory itself' => ['.']]);

test('a visitor cannot upload or delete a catalogue', function () {
    Storage::fake('local');
    $catalog = OffersCatalog::create(['file' => 'x.pdf']);

    expect($this->post(route('offers.store'), ['catalog' => catalogFile()])->getStatusCode())->toBeIn([302, 401, 403])
        ->and($this->post(route('offers.destroy', $catalog->id))->getStatusCode())->toBeIn([302, 401, 403])
        ->and(OffersCatalog::count())->toBe(1)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});
