<?php

use App\Models\Product;
use App\Services\ProductFieldSourceService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config(['store.products_sync_url' => 'https://stores.otajer.com/api/rest/listfullproducts/fake-key/2']);
});

test('the sync command creates a new product from the api response', function () {
    Http::fake([
        'stores.otajer.com/*' => Http::response([
            'Result' => 'OK',
            'Products' => [
                [
                    'Mat_ID' => '100027',
                    'enName' => 'KOMKOMMER PICKLES DURRA 3000 G',
                    'arName' => 'مخلل الدرة',
                    'Active' => 1,
                ],
            ],
        ]),
    ]);

    $this->artisan('products:sync')->assertSuccessful();

    $product = Product::find(100027);

    expect($product)->not->toBeNull();
    expect($product->name)->toBe('KOMKOMMER PICKLES DURRA 3000 G');
    expect($product->ar_name)->toBe('مخلل الدرة');
    expect($product->published)->toBeTrue();
    expect($product->getAttribute('synced_at'))->not->toBeNull();
});

test('the sync command updates an existing product from the feed, with an untouched product as noise', function () {
    $existing = Product::factory()->feedOrigin()->create(['id' => 100027, 'name' => 'Old Name', 'ar_name' => 'قديم', 'published' => false]);
    $untouched = Product::factory()->create(['name' => 'Untouched']);

    Http::fake([
        'stores.otajer.com/*' => Http::response([
            'Result' => 'OK',
            'Products' => [
                [
                    'Mat_ID' => (string) $existing->id,
                    'enName' => 'New Name',
                    // The feed sends null rather than omitting the key for an
                    // untranslated product.
                    'arName' => null,
                    'Active' => 1,
                ],
            ],
        ]),
    ]);

    $this->artisan('products:sync')->assertSuccessful();

    expect($existing->fresh())
        ->name->toBe('New Name')
        ->ar_name->toBe('')
        ->published->toBeTrue();

    expect($untouched->fresh()->name)->toBe('Untouched');
});

test('the sync command deactivates a product the feed marks inactive', function () {
    $product = Product::factory()->feedOrigin()->create(['id' => 100027, 'published' => true]);

    Http::fake([
        'stores.otajer.com/*' => Http::response([
            'Result' => 'OK',
            'Products' => [
                ['Mat_ID' => '100027', 'enName' => 'Widgets', 'arName' => '', 'Active' => 0],
            ],
        ]),
    ]);

    $this->artisan('products:sync')->assertSuccessful();

    expect($product->fresh()->published)->toBeFalse();
});

test('the sync command skips a row with a non-numeric Mat_ID or a blank name', function () {
    Http::fake([
        'stores.otajer.com/*' => Http::response([
            'Result' => 'OK',
            'Products' => [
                ['Mat_ID' => 'not-a-number', 'enName' => 'Bad Id', 'arName' => null, 'Active' => 1],
                ['Mat_ID' => '100028', 'enName' => null, 'arName' => null, 'Active' => 1],
            ],
        ]),
    ]);

    $this->artisan('products:sync')->assertSuccessful();

    expect(Product::count())->toBe(0);
});

test('the sync command fails without writing when the api reports a non-OK result', function () {
    Http::fake([
        'stores.otajer.com/*' => Http::response(['Result' => 'ERROR', 'Products' => []]),
    ]);

    $this->artisan('products:sync')->assertFailed();

    expect(Product::count())->toBe(0);
});

test('the sync command fails without throwing when the request itself fails', function () {
    Http::fake(function () {
        throw new ConnectionException('Connection timed out.');
    });

    $this->artisan('products:sync')->assertFailed();

    expect(Product::count())->toBe(0);
});

test('the sync command chunks the upsert instead of building one oversized statement', function () {
    // upsert() compiles one statement with all its bindings and never chunks
    // on its own — with DB_CONNECTION=sqlite (this suite's default), a single
    // statement over ~8,200 rows (4 bindings/row) would exceed SQLite's
    // 32766-variable limit. 1,200 rows across a chunk size of 500 forces 3
    // separate insert statements, proving the command chunks rather than
    // relying on row count alone (which a single, larger upsert would also
    // satisfy for the row-count assertion but not the statement-count one).
    $products = collect(range(1, 1200))->map(fn (int $i) => [
        'Mat_ID' => (string) $i,
        'enName' => "Product {$i}",
        'arName' => '',
        'Active' => 1,
    ])->all();

    Http::fake([
        'stores.otajer.com/*' => Http::response(['Result' => 'OK', 'Products' => $products]),
    ]);

    $upsertStatements = [];
    DB::listen(function ($query) use (&$upsertStatements) {
        if (str_contains($query->sql, 'insert into '.DB::connection()->getQueryGrammar()->wrap('products'))) {
            $upsertStatements[] = $query;
        }
    });

    $this->artisan('products:sync')->assertSuccessful();

    expect(Product::count())->toBe(1200);
    expect($upsertStatements)->toHaveCount(3);
});

test('the sync command fails cleanly when no sync url is configured', function () {
    config(['store.products_sync_url' => null]);

    Http::fake();

    $this->artisan('products:sync')->assertFailed();

    Http::assertNothingSent();
});

test('a feed id colliding with a store product is skipped counted and logged without changing any column', function () {
    $store = Product::factory()->create(['name' => 'Seller name', 'ar_name' => 'اسم البائع', 'published' => false]);
    $before = $store->fresh()->getRawOriginal();
    Log::spy();
    Http::fake(['stores.otajer.com/*' => Http::response(['Result' => 'OK', 'Products' => [
        ['Mat_ID' => (string) $store->id, 'enName' => 'Feed name', 'arName' => 'تغذية', 'Active' => 1],
        ['Mat_ID' => (string) ($store->id + 1), 'enName' => 'New feed product', 'Active' => 1],
    ]])]);

    $this->artisan('products:sync')->expectsOutputToContain('1 product(s) synced.')
        ->expectsOutputToContain('Product sync conflict: id '.$store->id)
        ->expectsOutputToContain('1 product(s) skipped')->assertSuccessful();

    expect($store->fresh()->getRawOriginal())->toBe($before);
    Log::shouldHaveReceived('warning')->once()->with('Product sync skipped a store-origin ID conflict.', ['id' => $store->id]);
    expect(Product::findOrFail($store->id + 1)->getAttribute('synced_at'))->not->toBeNull();
});

test('sync refreshes the marker truncates both names and preserves every store-owned column', function () {
    $this->travelTo(now()->startOfSecond());
    $product = Product::factory()->feedOrigin()->create();
    $product->forceFill(['unit_price' => 12.50, 'description' => 'Store text', 'approved' => 0])->save();
    $before = $product->fresh()->getRawOriginal();
    $this->travel(1)->hours();
    Http::fake(['stores.otajer.com/*' => Http::response(['Result' => 'OK', 'Products' => [
        ['Mat_ID' => (string) $product->id, 'enName' => str_repeat('é', 201), 'arName' => str_repeat('ع', 192), 'Active' => 0, 'Price' => 999],
    ]])]);

    $this->artisan('products:sync')->assertSuccessful();

    $after = $product->fresh()->getRawOriginal();
    expect($after['name'])->toBe(str_repeat('é', 200))
        ->and($after['ar_name'])->toBe(str_repeat('ع', 191))
        ->and($after['synced_at'])->toBe(now()->toDateTimeString());
    foreach (array_diff(array_keys($before), ['name', 'ar_name', 'published', 'synced_at']) as $column) {
        expect($after[$column])->toBe($before[$column], $column);
    }
});

/**
 * @param  list<array<string, mixed>>  $products
 */
function fakeOtajerFeed(array $products): void
{
    Http::fake(['stores.otajer.com/*' => Http::response(['Result' => 'OK', 'Products' => $products])]);
}

test('the sync never updates an admin-owned field and keeps writing the Otajer-owned ones', function () {
    app(ProductFieldSourceService::class)->update(['name' => 'admin', 'published' => 'admin']);
    $product = Product::factory()->feedOrigin()->create(['id' => 100027, 'name' => 'Admin name', 'ar_name' => 'قديم', 'published' => false]);
    $noise = Product::factory()->feedOrigin()->create(['id' => 100028, 'name' => 'Noise', 'ar_name' => 'ضجيج', 'published' => true]);
    fakeOtajerFeed([['Mat_ID' => '100027', 'enName' => 'Feed name', 'arName' => 'جديد', 'Active' => 1]]);

    $this->artisan('products:sync')
        ->expectsOutputToContain('Admin-owned field(s) left to the admin panel: name, published.')
        ->expectsOutputToContain('1 product(s) synced.')
        ->assertSuccessful();

    expect($product->fresh())
        ->name->toBe('Admin name')
        ->published->toBeFalse()
        ->ar_name->toBe('جديد')
        ->and($product->fresh()->getAttribute('synced_at'))->not->toBeNull()
        ->and($noise->fresh())->name->toBe('Noise')->ar_name->toBe('ضجيج');
});

test('a product the sync creates gets the insert value of every admin-owned field, never the feed value', function () {
    app(ProductFieldSourceService::class)->update(['name' => 'admin', 'ar_name' => 'admin', 'published' => 'admin']);
    fakeOtajerFeed([['Mat_ID' => '100027', 'enName' => 'Feed name', 'arName' => 'اسم', 'Active' => 1]]);

    $this->artisan('products:sync')->assertSuccessful();

    $product = Product::findOrFail(100027);
    expect($product)->name->toBe('')->ar_name->toBe('')->published->toBeFalse()
        ->and($product->getAttribute('synced_at'))->not->toBeNull();
});

test('a created product takes the feed value of every Otajer-owned field beside an admin-owned one', function () {
    app(ProductFieldSourceService::class)->update(['ar_name' => 'admin']);
    fakeOtajerFeed([['Mat_ID' => '100027', 'enName' => 'Feed name', 'arName' => 'اسم', 'Active' => 1]]);

    $this->artisan('products:sync')->assertSuccessful();

    expect(Product::findOrFail(100027))->name->toBe('Feed name')->ar_name->toBe('')->published->toBeTrue();
});

test('with every field Otajer-owned the sync prints no admin-owned notice and writes all three', function () {
    app(ProductFieldSourceService::class)->update(['name' => 'otajer', 'ar_name' => 'otajer', 'published' => 'otajer']);
    $product = Product::factory()->feedOrigin()->create(['id' => 100027, 'name' => 'Old', 'ar_name' => 'قديم', 'published' => false]);
    fakeOtajerFeed([['Mat_ID' => '100027', 'enName' => 'New', 'arName' => 'جديد', 'Active' => 1]]);

    $this->artisan('products:sync')->doesntExpectOutputToContain('Admin-owned field(s)')->assertSuccessful();

    expect($product->fresh())->name->toBe('New')->ar_name->toBe('جديد')->published->toBeTrue();
});

test('an admin-owned field does not open store-origin rows to the sync', function () {
    app(ProductFieldSourceService::class)->update(['name' => 'admin']);
    $store = Product::factory()->create(['name' => 'Store', 'ar_name' => 'متجر', 'published' => true]);
    $before = $store->fresh()->getRawOriginal();
    fakeOtajerFeed([['Mat_ID' => (string) $store->id, 'enName' => 'Feed', 'arName' => 'تغذية', 'Active' => 0]]);

    $this->artisan('products:sync')->expectsOutputToContain('1 product(s) skipped')->assertSuccessful();

    expect($store->fresh()->getRawOriginal())->toBe($before);
});

test('an admin-owned field keeps its value when every field is admin-owned, while the marker still refreshes', function () {
    $this->travelTo(now()->startOfSecond());
    app(ProductFieldSourceService::class)->update(['name' => 'admin', 'ar_name' => 'admin', 'published' => 'admin']);
    $product = Product::factory()->feedOrigin()->create(['id' => 100027, 'name' => 'Admin', 'ar_name' => 'إدارة', 'published' => true]);
    $this->travel(1)->hours();
    fakeOtajerFeed([['Mat_ID' => '100027', 'enName' => 'Feed', 'arName' => 'تغذية', 'Active' => 0]]);

    $this->artisan('products:sync')->assertSuccessful();

    expect($product->fresh())->name->toBe('Admin')->ar_name->toBe('إدارة')->published->toBeTrue()
        ->and($product->fresh()->getRawOriginal('synced_at'))->toBe(now()->toDateTimeString());
});
