<?php

use App\Models\Product;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

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
});

test('the sync command updates an existing product from the feed, with an untouched product as noise', function () {
    $existing = Product::factory()->create(['id' => 100027, 'name' => 'Old Name', 'ar_name' => 'قديم', 'published' => false]);
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
    $product = Product::factory()->create(['id' => 100027, 'published' => true]);

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

test('the sync command fails cleanly when no sync url is configured', function () {
    config(['store.products_sync_url' => null]);

    Http::fake();

    $this->artisan('products:sync')->assertFailed();

    Http::assertNothingSent();
});
