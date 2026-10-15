<?php

use App\Models\OffersCatalog;
use App\Services\OffersCatalogStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

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

    $this->delete(route('offers.destroy', $catalog->id))->assertRedirect();

    expect(OffersCatalog::count())->toBe(0);
    Storage::disk('local')->assertMissing("uploads/offers/catalogs/{$catalog->file}");

    $orphan = OffersCatalog::create(['file' => 'orphan.pdf']);

    $this->delete(route('offers.destroy', $orphan->id))->assertRedirect();

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
        ->and($this->delete(route('offers.destroy', $catalog->id))->getStatusCode())->toBeIn([302, 401, 403])
        ->and(OffersCatalog::count())->toBe(1)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('the offers catalogue page can be rendered by its reader', function () {
    actingAsStoreUser(createStoreStaff('view_offers_catalog'));
    $this->get('/temp/admin/offers/index')->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Store/Admin/Catalogue/Offers/Index')
            ->has('catalogs.data', 0)->where('hasCatalog', false)
    );
});

test('the catalogue list exposes a complete narrow record without a private filename', function () {
    actingAsStoreUser(createStoreStaff('view_offers_catalog'));
    $catalog = OffersCatalog::create(['file' => 'private.pdf']);
    backdate($catalog, '2026-10-09 00:00:00');
    $this->get('/temp/admin/offers/index')->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('filters', ['per_page' => 20])->where('hasCatalog', true)
            ->has('catalogs.data.0', fn (Assert $record) => $record->where('id', $catalog->id)
                ->where('created_at', '2026-10-09T00:00:00+00:00'))
    );
});

test('the catalogue list paginates historical records and validates page size', function () {
    actingAsStoreUser(createStoreStaff('view_offers_catalog'));
    foreach (range(1, 23) as $number) {
        OffersCatalog::create(['file' => "old-$number.pdf"]);
    }
    $this->get('/temp/admin/offers/index')->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('catalogs.data', 20)->where('catalogs.meta.total', 23)
    );
    $this->get('/temp/admin/offers/index?per_page=10')->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('catalogs.data', 10)->where('filters.per_page', 10)
    );
    $this->get('/temp/admin/offers/index?per_page=7')->assertSessionHasErrors('per_page');
});

test('a catalogue uploader needs only the upload permission and duplicate upload has a field error', function () {
    Storage::fake('local');
    actingAsStoreUser(createStoreStaff('add_offers_catalog'));
    $this->post(route('offers.store'), ['catalog' => catalogFile()])->assertRedirect(route('offers.index'));
    $this->post(route('offers.store'), ['catalog' => catalogFile()])->assertSessionHasErrors('catalog');
    expect(OffersCatalog::count())->toBe(1)->and(Storage::disk('local')->allFiles())->toHaveCount(1);
    $this->get('/temp/admin/offers/index')->assertForbidden();
    $this->get('/temp/admin/offers/download')->assertForbidden();
    $this->delete(route('offers.destroy', OffersCatalog::sole()->id))->assertForbidden();
    expect(OffersCatalog::count())->toBe(1)->and(Storage::disk('local')->allFiles())->toHaveCount(1);
});

test('a missing or oversized catalogue upload is refused without a write', function () {
    Storage::fake('local');
    actingAsStoreUser(createStoreStaff('add_offers_catalog'));
    $this->post(route('offers.store'))->assertSessionHasErrors('catalog');
    $this->post(route('offers.store'), ['catalog' => UploadedFile::fake()->create('big.pdf', 51201, 'application/pdf')])->assertSessionHasErrors('catalog');
    expect(OffersCatalog::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('catalogue deletion preserves the current query and needs only the delete permission', function () {
    Storage::fake('local');
    actingAsStoreUser(createStoreStaff('delete_offers_catalog'));
    $catalog = OffersCatalog::create(['file' => 'orphan.pdf']);
    $this->delete(route('offers.destroy', ['catalog' => $catalog, 'per_page' => 10, 'page' => 2]))
        ->assertRedirect(route('offers.index', ['per_page' => 10, 'page' => 2]));
    expect(OffersCatalog::count())->toBe(0);
});

test('deleting a missing catalogue returns 404', function () {
    actingAsStoreUser(createStoreStaff('delete_offers_catalog'));
    $this->delete(route('offers.destroy', 999999))->assertNotFound();
});

test('catalogue listing refuses an unauthorized caller', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get('/temp/admin/offers/index'), $caller);
})->with('refused store admin callers');

test('admin catalogue download refuses an unauthorized caller', function (string $caller) {
    Storage::fake('local');
    Storage::disk('local')->put('uploads/offers/catalogs/stored.pdf', 'private PDF');
    OffersCatalog::create(['file' => 'stored.pdf']);
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get('/temp/admin/offers/download'), $caller);
})->with('refused store admin callers');

test('catalogue upload refuses an unauthorized caller without changing database or files', function (string $caller) {
    Storage::fake('local');
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post(route('offers.store'), ['catalog' => catalogFile()]), $caller);
    expect(OffersCatalog::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
})->with('refused store admin callers');

test('catalogue deletion refuses an unauthorized caller without changing database or files', function (string $caller) {
    Storage::fake('local');
    Storage::disk('local')->put('uploads/offers/catalogs/stored.pdf', 'private PDF');
    $catalog = OffersCatalog::create(['file' => 'stored.pdf']);
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->delete(route('offers.destroy', $catalog)), $caller);
    expect(OffersCatalog::sole()->file)->toBe('stored.pdf');
    Storage::disk('local')->assertExists('uploads/offers/catalogs/stored.pdf');
})->with('refused store admin callers');
