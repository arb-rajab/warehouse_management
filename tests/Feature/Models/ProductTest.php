<?php

use App\Models\Product;
use App\Models\Upload;
use Illuminate\Support\Facades\DB;

test('a product can be created with its fillable attributes', function () {
    $product = Product::factory()->imageUrl('https://example.com/widgets.png')->boxesCount(24)->create([
        'name' => 'Widgets',
    ]);

    expect($product->fresh())
        ->name->toBe('Widgets')
        ->image_url->toBe('https://example.com/widgets.png')
        ->boxes_count->toBe(24);
});

test('a product can be created without an image_url', function () {
    $product = Product::factory()->imageUrl(null)->create();

    expect($product->fresh()->image_url)->toBeNull();
});

test('filterOptions returns every product ordered by name', function () {
    $c = Product::factory()->create(['name' => 'Charlie']);
    $a = Product::factory()->create(['name' => 'Alpha']);
    $b = Product::factory()->create(['name' => 'Bravo']);

    $options = Product::filterOptions();

    expect($options->pluck('name')->all())->toBe(['Alpha', 'Bravo', 'Charlie']);
    expect($options->pluck('id')->all())->toBe([$a->id, $b->id, $c->id]);
});

test('filterOptions returns an empty collection when there are no products', function () {
    expect(Product::filterOptions())->toBeEmpty();
});

test('selectedOptions returns only the given ids, ordered by name, excluding an unselected product', function () {
    $b = Product::factory()->create(['name' => 'Bravo']);
    $a = Product::factory()->create(['name' => 'Alpha']);
    Product::factory()->create(['name' => 'Unselected Charlie']);

    $options = Product::selectedOptions([$a->id, $b->id]);

    expect($options->pluck('name')->all())->toBe(['Alpha', 'Bravo']);
});

test('selectedOptions returns an empty collection when given no ids, without querying at all', function () {
    Product::factory()->create();

    DB::enableQueryLog();
    DB::flushQueryLog();

    $options = Product::selectedOptions();

    expect($options)->toBeEmpty();
    expect(DB::getQueryLog())->toBeEmpty();

    DB::disableQueryLog();
});

test('searchByName filters to products whose name contains the term, excluding a non-matching product', function () {
    $matching = Product::factory()->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    Product::factory()->create(['name' => 'Unrelated Gadgets', 'ar_name' => 'أدوات']);

    $results = Product::query()->searchByName('Widg')->get();

    expect($results->pluck('id')->all())->toBe([$matching->id]);
});

test('searchByName matches an Arabic term against ar_name, excluding a product with a different Arabic name', function () {
    // The store's `products.ar_name`: never displayed by this app, but the
    // warehouse staff know products by it and type it into the search box.
    $matching = Product::factory()->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    Product::factory()->create(['name' => 'Gadgets', 'ar_name' => 'أدوات']);

    $results = Product::query()->searchByName('ودجات')->get();

    expect($results->pluck('id')->all())->toBe([$matching->id]);
});

test('searchByName matches an Arabic term regardless of the active locale', function () {
    // The columns searched do not depend on the request locale — the same term
    // must return the same products whichever language the UI is showing.
    $matching = Product::factory()->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    Product::factory()->create(['name' => 'Gadgets', 'ar_name' => 'أدوات']);

    app()->setLocale('en');
    expect(Product::query()->searchByName('ودجات')->pluck('id')->all())->toBe([$matching->id]);

    app()->setLocale('ar');
    expect(Product::query()->searchByName('Widg')->pluck('id')->all())->toBe([$matching->id]);
});

test('searchByName ignores an empty ar_name rather than matching every product through it', function () {
    // `ar_name` is NOT NULL upstream, so a product the store never translated
    // carries an empty string. A LIKE '%term%' can never match it.
    $matching = Product::factory()->create(['name' => 'Widgets', 'ar_name' => '']);
    Product::factory()->create(['name' => 'Gadgets', 'ar_name' => '']);

    $results = Product::query()->searchByName('Widg')->get();

    expect($results->pluck('id')->all())->toBe([$matching->id]);
});

test('searchByName matches every product when the term is null or blank', function () {
    Product::factory()->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    Product::factory()->create(['name' => 'Gadgets', 'ar_name' => 'أدوات']);

    expect(Product::query()->searchByName(null)->count())->toBe(2);
    expect(Product::query()->searchByName('')->count())->toBe(2);
    expect(Product::query()->searchByName('   ')->count())->toBe(2);
});

test('searchByName matches multi-word terms regardless of word order, excluding a partial match', function () {
    $matching = Product::factory()->create(['name' => 'Large Blue Widget', 'ar_name' => 'ودجة']);
    Product::factory()->create(['name' => 'Large Red Widget', 'ar_name' => 'ودجة']);

    $results = Product::query()->searchByName('Widget Blue')->get();

    expect($results->pluck('id')->all())->toBe([$matching->id]);
});

test('searchByName matches a multi-word term whose words split across name and ar_name', function () {
    $matching = Product::factory()->create(['name' => 'Large Blue Widget', 'ar_name' => 'ودجة زرقاء كبيرة']);
    // Noise: matches "Widget" through `name`, but nothing matches "زرقاء" in
    // either of its columns. A flat orWhere() chain would OR across the word
    // boundary and wrongly return it alongside the real match.
    Product::factory()->create(['name' => 'Small Widget', 'ar_name' => 'ودجة صغيرة']);

    $results = Product::query()->searchByName('Widget زرقاء')->get();

    expect($results->pluck('id')->all())->toBe([$matching->id]);
});

test('image_url resolves through the thumbnail upload, with noise from another product', function () {
    $product = Product::factory()->imageUrl('https://cdn.example.com/widgets.png')->create();
    Product::factory()->imageUrl('https://cdn.example.com/other.png')->create();

    expect($product->fresh()->image_url)->toBe('https://cdn.example.com/widgets.png');
});

test('image_url resolves a stored upload through the store asset base url', function () {
    config(['store.asset_base_url' => 'https://store.example.com']);
    $product = Product::factory()->create([
        'thumbnail_img' => Upload::factory()->stored('uploads/all/widgets.png'),
    ]);

    expect($product->fresh()->image_url)->toBe('https://store.example.com/uploads/all/widgets.png');
});

test('image_url is null when the product has no thumbnail', function () {
    $product = Product::factory()->imageUrl(null)->create();

    expect($product->fresh())
        ->thumbnail_img->toBeNull()
        ->image_url->toBeNull();
});

test('thumbnail_img is cast to an integer so the upload relation matches on sqlite', function () {
    // The store declares the column varchar(100) though it holds an uploads
    // row id; without the cast the relation silently resolves null here.
    $upload = Upload::factory()->create();
    $product = Product::factory()->create(['thumbnail_img' => $upload->id]);

    expect($product->fresh()->thumbnail_img)->toBe($upload->id);
    expect($product->fresh()->thumbnailUpload?->id)->toBe($upload->id);
});

test('boxes_count reads the wms-owned settings row, with noise from another product', function () {
    $product = Product::factory()->boxesCount(24)->create();
    Product::factory()->boxesCount(7)->create();

    expect($product->fresh()->boxes_count)->toBe(24);
});

test('boxes_count falls back to its default for a product this app has never configured', function () {
    // The store can add a product at any time without this app knowing.
    $product = Product::factory()->unconfigured()->create();

    expect($product->fresh())
        ->setting->toBeNull()
        ->boxes_count->toBe(Product::DEFAULT_BOXES_COUNT);
});
