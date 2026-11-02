<?php

use App\Models\Category;
use App\Models\Language;
use App\Models\Product;
use App\Services\ProductFieldSourceService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The physical product edit form under the per-field owner choice
 * (.ai/rules/product-field-sources.md): an Otajer-owned synced field of a
 * feed-origin product is read-only on the form and ignored on save; an
 * admin-owned one, or any field of a store-origin product, is the admin's.
 */
beforeEach(function () {
    seedStoreCurrency();
    config(['app.default_language' => 'en']);
    Language::forceCreate(['code' => 'en', 'name' => 'English', 'rtl' => 0]);
    Language::forceCreate(['code' => 'ar', 'name' => 'Arabic', 'rtl' => 1]);
    createStoreAdmin();
    $this->category = Category::forceCreate(['name' => 'Pantry', 'parent_id' => 0, 'level' => 0, 'slug' => 'pantry', 'digital' => 0]);
    $this->feed = fieldSourceEditProduct(['name' => 'Feed oil', 'ar_name' => 'زيت التغذية', 'unit_price' => 9.5, 'description' => '<p>Synced text</p>'], feedOrigin: true);
    $this->store = fieldSourceEditProduct(['name' => 'Store oil', 'ar_name' => 'زيت المتجر'], feedOrigin: false);
    // A stale default-language translation the sync never refreshes.
    DB::table('product_translations')->insert([
        ['product_id' => $this->feed->id, 'lang' => 'en', 'name' => 'Old feed oil', 'unit' => 'old bottle', 'description' => '<p>Old text</p>'],
        ['product_id' => $this->feed->id, 'lang' => 'ar', 'name' => 'ترجمة', 'unit' => 'زجاجة', 'description' => null],
    ]);
    actingAsStoreUser(createStoreStaff('product_edit', 'show_in_house_products'));
});

function fieldSourceEditProduct(array $attributes, bool $feedOrigin): Product
{
    $product = createStoreProduct([
        'added_by' => 'admin', 'digital' => 0, 'auction_product' => 0, 'wholesale_product' => 0, 'thumbnail_img' => null,
        'category_id' => test()->category->id, 'unit' => 'bottle', 'colors' => '[]', 'choice_options' => '[]', 'variant_product' => 0,
        'published' => 1, ...$attributes,
    ]);
    if ($feedOrigin) {
        DB::table('products')->where('id', $product->id)->update(['synced_at' => now()]);
    }

    return $product->fresh();
}

/** Every field products:sync writes, in registry order. */
const FEED_FIELDS = [
    'name', 'ar_name', 'published', 'collection_id',
    'serial', 'unit_price', 'unit', 'api_unitId', 'api_unit_name', 'tax', 'description', 'unit_equal',
];

/** @return array<string, mixed> */
function fieldSourceEditForm(array $overrides = []): array
{
    return [
        'lang' => 'en',
        'name' => 'Posted name',
        'ar_name' => 'اسم مرسل',
        'category_id' => test()->category->id,
        'unit' => 'jar',
        'max_qty' => '10',
        'min_qty' => '1',
        'unit_price' => '3.5',
        'discount' => '0',
        'discount_type' => 'amount',
        'current_stock' => '7',
        'stock_visibility_state' => 'quantity',
        ...$overrides,
    ];
}

test('a feed-origin product shows every Otajer-owned field read-only, with the synced name', function () {
    $this->get(route('products.admin.edit', $this->feed))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Product/Products/Edit')
        ->where('readOnlyFields', FEED_FIELDS)
        ->where('name', 'Feed oil')
        ->where('arName', 'زيت التغذية')
        // The synced columns, not the stale default-language translation.
        ->where('unit', 'bottle')
        ->where('description', '<p>Synced text</p>')
        ->where('product.unit_price', '9.5'));
});

test('another language keeps its translated name editable, while the other synced fields stay read-only', function () {
    $this->get(route('products.admin.edit', [$this->feed, 'lang' => 'ar']))->assertOk()->assertInertia(fn (Assert $page) => $page
        // The name, unit and description are the default language's only.
        ->where('readOnlyFields', array_values(array_diff(FEED_FIELDS, ['name', 'unit', 'description'])))
        ->where('name', 'ترجمة')
        ->where('unit', 'زجاجة')
        ->where('arName', 'زيت التغذية'));
});

test('an admin-owned field is editable on a feed-origin product', function () {
    app(ProductFieldSourceService::class)->update(['name' => 'admin', 'ar_name' => 'admin']);

    $this->get(route('products.admin.edit', $this->feed))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('readOnlyFields', array_values(array_diff(FEED_FIELDS, ['name', 'ar_name'])))
        // Editable again, the name comes from the translation as before.
        ->where('name', 'Old feed oil'));
});

test('a store-origin product has no read-only field whatever the owners are', function () {
    $this->get(route('products.admin.edit', $this->store))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('readOnlyFields', [])
        ->where('arName', 'زيت المتجر'));
});

test('saving a feed-origin product ignores posted Otajer-owned names but saves the other fields', function () {
    $this->post(route('products.update', $this->feed), fieldSourceEditForm())
        ->assertRedirect(route('products.admin.edit', ['product' => $this->feed->id, 'lang' => 'en']))
        ->assertSessionHasNoErrors();

    $product = $this->feed->fresh();
    expect($product)->name->toBe('Feed oil')->ar_name->toBe('زيت التغذية')->published->toBeTrue()
        ->and((int) $product->getAttribute('max_qty'))->toBe(10)
        // The synced unit, price and description are ignored too.
        ->and($product->getAttribute('unit'))->toBe('bottle')
        ->and((float) $product->getAttribute('unit_price'))->toBe(9.5)
        ->and($product->getAttribute('description'))->toBe('<p>Synced text</p>')
        ->and($product->getAttribute('synced_at'))->not->toBeNull()
        // The default-language translation now mirrors the synced name.
        ->and(DB::table('product_translations')->where('product_id', $product->id)->where('lang', 'en')->first(['name', 'unit', 'description']))
        ->name->toBe('Feed oil')->unit->toBe('bottle')->description->toBe('<p>Synced text</p>')
        ->and($this->store->fresh()->name)->toBe('Store oil');
});

test('a posted unit price cannot move the discount check or the single stock row of a feed-origin product', function () {
    // Posted 3.5 would make a 5 discount invalid; the stored 9.5 is what counts.
    $this->post(route('products.update', $this->feed), fieldSourceEditForm(['unit_price' => '3.5', 'discount' => '5']))
        ->assertSessionHasNoErrors();

    expect((float) $this->feed->fresh()->getAttribute('unit_price'))->toBe(9.5)
        ->and((float) DB::table('product_stocks')->where('product_id', $this->feed->id)->value('price'))->toBe(9.5);
});

test('saving writes an admin-owned unit price, unit and description on a feed-origin product and mirrors the translation', function () {
    app(ProductFieldSourceService::class)->update(['unit_price' => 'admin', 'unit' => 'admin', 'description' => 'admin']);

    $this->post(route('products.update', $this->feed), fieldSourceEditForm(['description' => '<p>Admin text</p>']))->assertSessionHasNoErrors();

    $product = $this->feed->fresh();
    expect($product->getAttribute('unit'))->toBe('jar')
        ->and((float) $product->getAttribute('unit_price'))->toBe(3.5)
        ->and($product->getAttribute('description'))->toBe('<p>Admin text</p>')
        ->and($product)->name->toBe('Feed oil')
        ->and(DB::table('product_translations')->where('product_id', $product->id)->where('lang', 'en')->first(['unit', 'description']))
        ->unit->toBe('jar')->description->toBe('<p>Admin text</p>');
});

test('another language edits its own translation of the unit and description without touching the synced columns', function () {
    $this->post(route('products.update', $this->feed), fieldSourceEditForm(['lang' => 'ar', 'name' => 'اسم', 'unit' => 'برطمان', 'description' => '<p>نص</p>']))
        ->assertSessionHasNoErrors();

    $product = $this->feed->fresh();
    expect($product->getAttribute('unit'))->toBe('bottle')
        ->and($product->getAttribute('description'))->toBe('<p>Synced text</p>')
        ->and(DB::table('product_translations')->where('product_id', $this->feed->id)->where('lang', 'ar')->first(['name', 'unit', 'description']))
        ->name->toBe('اسم')->unit->toBe('برطمان')->description->toBe('<p>نص</p>');
});

test('a store-origin product saves its unit price, unit and description whatever the owners are', function () {
    $this->post(route('products.update', $this->store), fieldSourceEditForm(['description' => '<p>Store text</p>']))->assertSessionHasNoErrors();

    $product = $this->store->fresh();
    expect($product->getAttribute('unit'))->toBe('jar')
        ->and((float) $product->getAttribute('unit_price'))->toBe(3.5)
        ->and($product->getAttribute('description'))->toBe('<p>Store text</p>');
});

test('saving writes an admin-owned name and Arabic name on a feed-origin product', function () {
    app(ProductFieldSourceService::class)->update(['name' => 'admin', 'ar_name' => 'admin']);

    $this->post(route('products.update', $this->feed), fieldSourceEditForm())->assertSessionHasNoErrors();

    expect($this->feed->fresh())->name->toBe('Posted name')->ar_name->toBe('اسم مرسل')
        ->and(DB::table('product_translations')->where('product_id', $this->feed->id)->where('lang', 'en')->value('name'))->toBe('Posted name');
});

test('only the field the admin owns is written when the other stays Otajer-owned', function () {
    app(ProductFieldSourceService::class)->update(['ar_name' => 'admin']);

    $this->post(route('products.update', $this->feed), fieldSourceEditForm())->assertSessionHasNoErrors();

    expect($this->feed->fresh())->name->toBe('Feed oil')->ar_name->toBe('اسم مرسل');
});

test('a store-origin product saves its names whatever the owners are', function () {
    $this->post(route('products.update', $this->store), fieldSourceEditForm())->assertSessionHasNoErrors();

    expect($this->store->fresh())->name->toBe('Posted name')->ar_name->toBe('اسم مرسل');
});

test('an empty Arabic name stores the column default, and an absent one leaves it alone', function () {
    $this->post(route('products.update', $this->store), fieldSourceEditForm(['ar_name' => null]))->assertSessionHasNoErrors();
    expect($this->store->fresh()->ar_name)->toBe('');

    DB::table('products')->where('id', $this->store->id)->update(['ar_name' => 'باق']);
    $form = fieldSourceEditForm();
    unset($form['ar_name']);
    $this->post(route('products.update', $this->store), $form)->assertSessionHasNoErrors();
    expect($this->store->fresh()->ar_name)->toBe('باق');
});

test('the Arabic name is validated before any write', function (mixed $arName) {
    $before = $this->store->fresh()->getRawOriginal();

    $this->postJson(route('products.update', $this->store), fieldSourceEditForm(['ar_name' => $arName]))
        ->assertUnprocessable()->assertJsonValidationErrors('ar_name');

    expect($this->store->fresh()->getRawOriginal())->toBe($before);
})->with(['too long' => [str_repeat('ع', 192)], 'not a string' => [['x']]]);

test('the list switch refuses an Otajer-owned active status on a feed-origin product and allows an admin-owned one', function () {
    $this->post(route('products.list.published', [$this->feed, 'list' => 'admin']), ['status' => 0])
        ->assertSessionHasErrors('published');
    expect($this->feed->fresh()->published)->toBeTrue();

    app(ProductFieldSourceService::class)->update(['published' => 'admin']);

    $this->post(route('products.list.published', [$this->feed, 'list' => 'admin']), ['status' => 0])
        ->assertSessionHasNoErrors();
    expect($this->feed->fresh()->published)->toBeFalse();
});

test('a caller without product_edit cannot save, whatever the owners are', function () {
    app(ProductFieldSourceService::class)->update(['name' => 'admin']);
    actingAsStoreUser(createStoreStaff('product_field_sources'));
    $before = $this->feed->fresh()->getRawOriginal();

    $this->post(route('products.update', $this->feed), fieldSourceEditForm())->assertForbidden();

    expect($this->feed->fresh()->getRawOriginal())->toBe($before);
});
