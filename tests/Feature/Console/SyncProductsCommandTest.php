<?php

use App\Models\Product;
use App\Models\ProductCollection;
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
    // statement over ~2,000 rows (16 bindings/row) would exceed SQLite's
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
    $product->forceFill(['weight' => 12.50, 'tags' => 'Store text', 'approved' => 0])->save();
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
    // The price is a synced column now; every other column stays the store's.
    expect((float) $after['unit_price'])->toBe(999.0);
    foreach (array_diff(array_keys($before), ['name', 'ar_name', 'published', 'synced_at', 'unit_price']) as $column) {
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

test('the sync links a product to the collection whose serial is its Mat_Class2_ID, and keeps the current one when none matches', function () {
    $five = ProductCollection::create(['name' => 'Margin 5%', 'serial' => '6', 'amount' => 5]);
    $kept = ProductCollection::create(['name' => 'Margin 3%', 'serial' => '9', 'amount' => 3]);
    $relinked = Product::factory()->feedOrigin()->create(['id' => 100041, 'collection_id' => $kept->id]);
    $unmatched = Product::factory()->feedOrigin()->create(['id' => 100042, 'collection_id' => $kept->id]);
    $store = Product::factory()->create(['id' => 100043, 'collection_id' => $kept->id]);
    fakeOtajerFeed([
        ['Mat_ID' => '100040', 'enName' => 'New', 'Active' => 1, 'Mat_Class2_ID' => 6],
        ['Mat_ID' => '100041', 'enName' => 'Relinked', 'Active' => 1, 'Mat_Class2_ID' => 6],
        ['Mat_ID' => '100042', 'enName' => 'Unmatched', 'Active' => 1, 'Mat_Class2_ID' => 99],
    ]);

    $this->artisan('products:sync')->assertSuccessful();

    expect(Product::find(100040)->collection_id)->toBe($five->id)
        ->and($relinked->fresh()->collection_id)->toBe($five->id)
        ->and($unmatched->fresh()->collection_id)->toBe($kept->id)
        ->and($store->fresh()->collection_id)->toBe($kept->id);
});

test('an admin-owned collection is never written by the sync', function () {
    app(ProductFieldSourceService::class)->update(['collection_id' => 'admin']);
    $five = ProductCollection::create(['name' => 'Margin 5%', 'serial' => '6', 'amount' => 5]);
    $kept = ProductCollection::create(['name' => 'Margin 3%', 'serial' => '9', 'amount' => 3]);
    $product = Product::factory()->feedOrigin()->create(['id' => 100051, 'collection_id' => $kept->id]);
    fakeOtajerFeed([
        ['Mat_ID' => '100051', 'enName' => 'Existing', 'Active' => 1, 'Mat_Class2_ID' => 6],
        ['Mat_ID' => '100052', 'enName' => 'Created', 'Active' => 1, 'Mat_Class2_ID' => 6],
    ]);

    $this->artisan('products:sync')->assertSuccessful();

    expect($product->fresh()->collection_id)->toBe($kept->id)
        ->and(Product::find(100052)->collection_id)->toBe(0)
        ->and($five->id)->not->toBe(0);
});

/** A feed row carrying every property the sync maps, for the given product. */
function fullFeedRow(int $id, array $overrides = []): array
{
    return [
        'Mat_ID' => (string) $id, 'enName' => "Product {$id}", 'arName' => 'منتج', 'Active' => 1,
        'Serial' => "SER-{$id}", 'Price' => 12.346, 'UnitEnName' => 'Box of twelve pieces of tea', 'UnitID' => 7,
        'TaxPercent' => '15', 'Desc' => '<p>Fresh <script>alert(1)</script>tea</p>', 'Unit_Equal' => '12',
        ...$overrides,
    ];
}

/** Raw column values of a product, bypassing the model's casts. */
function rawProduct(int $id): object
{
    return DB::table('products')->where('id', $id)->first();
}

test('the sync writes every plain scalar property of the feed on create and on update, cut or cast to the column', function () {
    $existing = Product::factory()->feedOrigin()->create(['id' => 100061]);
    $noise = Product::factory()->feedOrigin()->create(['id' => 100069]);
    $noiseBefore = rawProduct(100069);
    fakeOtajerFeed([fullFeedRow(100061), fullFeedRow(100062, ['Serial' => str_repeat('s', 300), 'UnitID' => str_repeat('9', 300), 'Unit_Equal' => 6.0])]);

    $this->artisan('products:sync')->expectsOutputToContain('2 product(s) synced.')->assertSuccessful();

    foreach ([100061, 100062] as $id) {
        $row = rawProduct($id);
        expect((float) $row->unit_price)->toBe(12.35)
            ->and($row->unit)->toBe('Box of twelve pieces')
            ->and($row->api_unit_name)->toBe('Box of twelve pieces of tea')
            ->and((float) $row->tax)->toBe(15.0)
            ->and($row->description)->toContain('Fresh')->not->toContain('<script')
            ->and($row->synced_at)->not->toBeNull();
    }
    expect(rawProduct(100061))->serial->toBe('SER-100061')->api_unitId->toBe('7')->unit_equal->toBe(12)
        ->and(rawProduct(100062))->serial->toBe(str_repeat('s', 191))->api_unitId->toBe(str_repeat('9', 191))->unit_equal->toBe(6)
        ->and(rawProduct(100069))->toEqual($noiseBefore);
});

test('the unit name is cut to its 191-character column and the unit to 20', function () {
    fakeOtajerFeed([fullFeedRow(100063, ['UnitEnName' => str_repeat('u', 300)])]);

    $this->artisan('products:sync')->assertSuccessful();

    expect(rawProduct(100063))->api_unit_name->toBe(str_repeat('u', 191))->unit->toBe(str_repeat('u', 20));
});

test('a missing, blank or unusable feed value keeps the current value of an existing product', function () {
    Product::factory()->feedOrigin()->create(['id' => 100071]);
    DB::table('products')->where('id', 100071)->update([
        'serial' => 'KEEP', 'unit_price' => 8.5, 'unit' => 'bag', 'api_unitId' => '3', 'api_unit_name' => 'Bag', 'tax' => 9, 'description' => 'Kept text', 'unit_equal' => 4,
    ]);
    fakeOtajerFeed([
        // Every property blank or unusable.
        fullFeedRow(100071, ['Serial' => '  ', 'Price' => 'abc', 'UnitEnName' => null, 'UnitID' => ['x'], 'TaxPercent' => -3, 'Desc' => '', 'Unit_Equal' => 0]),
        // Absent keys and out-of-range numbers.
        ['Mat_ID' => '100072', 'enName' => 'Created bare', 'Active' => 1],
    ]);
    Product::factory()->feedOrigin()->create(['id' => 100072]);
    DB::table('products')->where('id', 100072)->update(['unit_price' => 5, 'unit_equal' => 2, 'tax' => 1]);

    $this->artisan('products:sync')->expectsOutputToContain('2 product(s) synced.')->assertSuccessful();

    expect(rawProduct(100071))->serial->toBe('KEEP')->unit->toBe('bag')->api_unitId->toBe('3')->api_unit_name->toBe('Bag')
        ->description->toBe('Kept text')->unit_equal->toBe(4)
        ->and((float) rawProduct(100071)->unit_price)->toBe(8.5)
        ->and((float) rawProduct(100071)->tax)->toBe(9.0)
        ->and((float) rawProduct(100072)->unit_price)->toBe(5.0)
        ->and(rawProduct(100072))->unit_equal->toBe(2);
});

test('a created product with no usable price or unit conversion gets the insert values', function () {
    fakeOtajerFeed([['Mat_ID' => '100073', 'enName' => 'Bare', 'Active' => 1, 'Price' => null, 'Unit_Equal' => 'many']]);

    $this->artisan('products:sync')->assertSuccessful();

    $row = rawProduct(100073);
    expect((float) $row->unit_price)->toBe(0.0)
        ->and($row->unit_equal)->toBeNull()->and($row->tax)->toBeNull()->and($row->description)->toBeNull()
        ->and($row->synced_at)->not->toBeNull();
});

test('an admin-owned scalar field is never written by the sync and takes its insert value on create', function () {
    app(ProductFieldSourceService::class)->update([
        'serial' => 'admin', 'unit_price' => 'admin', 'unit' => 'admin', 'api_unitId' => 'admin', 'api_unit_name' => 'admin',
        'tax' => 'admin', 'description' => 'admin', 'unit_equal' => 'admin',
    ]);
    Product::factory()->feedOrigin()->create(['id' => 100081]);
    DB::table('products')->where('id', 100081)->update([
        'serial' => 'KEEP', 'unit_price' => 8.5, 'unit' => 'bag', 'api_unitId' => '3', 'api_unit_name' => 'Bag', 'tax' => 9, 'description' => 'Kept text', 'unit_equal' => 4,
    ]);
    fakeOtajerFeed([fullFeedRow(100081), fullFeedRow(100082)]);

    $this->artisan('products:sync')
        ->expectsOutputToContain('Admin-owned field(s) left to the admin panel: serial, unit_price, unit, api_unitId, api_unit_name, tax, description, unit_equal.')
        ->assertSuccessful();

    expect(rawProduct(100081))->serial->toBe('KEEP')->unit->toBe('bag')->api_unitId->toBe('3')->api_unit_name->toBe('Bag')
        ->description->toBe('Kept text')->unit_equal->toBe(4)->name->toBe('Product 100081')
        ->and((float) rawProduct(100081)->unit_price)->toBe(8.5)
        ->and((float) rawProduct(100081)->tax)->toBe(9.0)
        ->and(rawProduct(100082))->serial->toBe('')->unit->toBe('')->api_unitId->toBe('')->api_unit_name->toBe('')
        ->description->toBe('')->unit_equal->toBe(1)->name->toBe('Product 100082')
        ->and((float) rawProduct(100082)->unit_price)->toBe(0.0)
        ->and((float) rawProduct(100082)->tax)->toBe(0.0);
});

test('a store-origin product is untouched by every scalar field, even a colliding feed id', function () {
    $store = Product::factory()->create();
    DB::table('products')->where('id', $store->id)->update(['serial' => 'MINE', 'unit_price' => 3, 'description' => 'Mine', 'unit_equal' => 2]);
    DB::table('product_stocks')->insert(['product_id' => $store->id, 'variant' => 'PerBox', 'price' => 3]);
    $before = rawProduct($store->id);
    fakeOtajerFeed([fullFeedRow($store->id)]);

    $this->artisan('products:sync')->expectsOutputToContain('1 product(s) skipped')->assertSuccessful();

    expect(rawProduct($store->id))->toEqual($before)
        ->and((float) DB::table('product_stocks')->where('product_id', $store->id)->value('price'))->toBe(3.0);
});

test('while the price is Otajer-owned the sync reprices the PerBox and PerPiece stocks of feed products only', function () {
    $this->travelTo(now()->startOfSecond());
    $feed = Product::factory()->feedOrigin()->create(['id' => 100091]);
    $noUnitEqual = Product::factory()->feedOrigin()->create(['id' => 100092]);
    $unlisted = Product::factory()->feedOrigin()->create(['id' => 100093]);
    $store = Product::factory()->create(['id' => 100094]);
    $stock = fn (int $product, string $variant, float $price) => DB::table('product_stocks')->insert(['product_id' => $product, 'variant' => $variant, 'price' => $price, 'qty' => 3]);
    foreach ([100091, 100092, 100093, 100094] as $id) {
        $stock($id, 'PerBox', 1);
        $stock($id, 'PerPiece', 1);
    }
    $stock(100091, 'PerBox-Green', 1);
    $stock(100091, 'PerPiece-Green', 1);
    $stock(100091, 'Special', 4.44);
    $this->travel(1)->hours();
    fakeOtajerFeed([
        fullFeedRow(100091, ['Price' => 24, 'Unit_Equal' => 6]),
        // No usable unit conversion and none stored: the PerPiece price waits.
        fullFeedRow(100092, ['Price' => 30, 'Unit_Equal' => 0]),
        fullFeedRow(100094, ['Price' => 99, 'Unit_Equal' => 3]),
    ]);

    $this->artisan('products:sync')->expectsOutputToContain('2 product(s) synced.')->assertSuccessful();

    $prices = fn (int $product): array => DB::table('product_stocks')->where('product_id', $product)->orderBy('id')->pluck('price', 'variant')->map(fn ($price) => (float) $price)->all();
    expect($prices(100091))->toBe(['PerBox' => 24.0, 'PerPiece' => 4.0, 'PerBox-Green' => 24.0, 'PerPiece-Green' => 4.0, 'Special' => 4.44])
        ->and($prices(100092))->toBe(['PerBox' => 30.0, 'PerPiece' => 1.0])
        ->and($prices(100093))->toBe(['PerBox' => 1.0, 'PerPiece' => 1.0])
        ->and($prices(100094))->toBe(['PerBox' => 1.0, 'PerPiece' => 1.0])
        ->and((int) DB::table('product_stocks')->where('product_id', 100091)->value('qty'))->toBe(3)
        ->and(DB::table('product_stocks')->where('product_id', 100093)->value('updated_at'))->not->toBe(now()->toDateTimeString());
});

test('a PerPiece price rounds to cents and divides by the stored unit conversion when the feed sends none', function () {
    Product::factory()->feedOrigin()->create(['id' => 100095]);
    DB::table('products')->where('id', 100095)->update(['unit_equal' => 3]);
    DB::table('product_stocks')->insert([['product_id' => 100095, 'variant' => 'PerPiece', 'price' => 1], ['product_id' => 100095, 'variant' => 'PerBox', 'price' => 1]]);
    fakeOtajerFeed([['Mat_ID' => '100095', 'enName' => 'Thirds', 'Active' => 1, 'Price' => 10]]);

    $this->artisan('products:sync')->assertSuccessful();

    expect(DB::table('product_stocks')->where('product_id', 100095)->pluck('price', 'variant')->map(fn ($price) => (float) $price)->all())
        ->toBe(['PerPiece' => 3.33, 'PerBox' => 10.0]);
});

test('an admin-owned price leaves every stock price alone', function () {
    app(ProductFieldSourceService::class)->update(['unit_price' => 'admin']);
    Product::factory()->feedOrigin()->create(['id' => 100096]);
    DB::table('product_stocks')->insert([['product_id' => 100096, 'variant' => 'PerBox', 'price' => 2], ['product_id' => 100096, 'variant' => 'PerPiece', 'price' => 1]]);
    fakeOtajerFeed([fullFeedRow(100096, ['Price' => 50, 'Unit_Equal' => 5])]);

    $this->artisan('products:sync')->assertSuccessful();

    expect(DB::table('product_stocks')->where('product_id', 100096)->pluck('price', 'variant')->map(fn ($price) => (float) $price)->all())
        ->toBe(['PerBox' => 2.0, 'PerPiece' => 1.0]);
});
