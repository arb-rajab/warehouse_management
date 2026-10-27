<?php

use App\Http\Middleware\BlockMaliciousRequests;
use App\Models\Category;
use App\Models\Language;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\StoreProductBarcode;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The physical product edit form (.ai/archive/store-backoffice/changes-20261014.md
 * §23): one stored variant product with an Arabic translation, a foreign
 * variant photo, a tax, a flash deal, a type and a store barcode, beside a
 * second product whose rows must never change.
 */
beforeEach(function () {
    seedStoreCurrency();
    config(['app.default_language' => 'en']);
    Language::forceCreate(['code' => 'en', 'name' => 'English', 'rtl' => 0]);
    Language::forceCreate(['code' => 'ar', 'name' => 'Arabic', 'rtl' => 1]);
    createStoreAdmin(raizerAdmin: true);
    $this->owner = createStoreAdmin();
    $this->root = Category::forceCreate(['name' => 'Pantry', 'parent_id' => 0, 'level' => 0, 'slug' => 'pantry', 'digital' => 0]);
    $this->child = Category::forceCreate(['name' => 'Oils', 'parent_id' => $this->root->id, 'level' => 1, 'slug' => 'oils', 'digital' => 0]);
    $this->digitalRoot = Category::forceCreate(['name' => 'E-books', 'parent_id' => 0, 'level' => 0, 'slug' => 'e-books', 'digital' => 1]);
    $this->brand = DB::table('brands')->insertGetId(['name' => 'Olivia', 'slug' => 'olivia']);
    $this->red = DB::table('colors')->insertGetId(['name' => 'Red', 'code' => '#FF0000']);
    $this->blue = DB::table('colors')->insertGetId(['name' => 'Light Blue', 'code' => '#ADD8E6']);
    $this->size = DB::table('attributes')->insertGetId(['name' => 'Size']);
    $this->material = DB::table('attributes')->insertGetId(['name' => 'Bottle']);
    $this->values = [];
    foreach ([[$this->size, 'S'], [$this->size, 'M'], [$this->size, 'Extra Large'], [$this->material, '1.5 L'], [$this->material, 'Glass']] as [$attribute, $value]) {
        $this->values[$value] = DB::table('attribute_values')->insertGetId(['attribute_id' => $attribute, 'value' => $value]);
    }
    $this->flashDeal = DB::table('flash_deals')->insertGetId(['title' => 'Autumn deals', 'status' => 1, 'start_date' => productEditUtcTimestamp('2026-11-01 00:00:00'), 'end_date' => productEditUtcTimestamp('2026-11-07 23:59:59'), 'slug' => 'autumn']);
    $this->vat = DB::table('taxes')->insertGetId(['name' => 'VAT', 'tax_status' => 1]);
    $this->service = DB::table('taxes')->insertGetId(['name' => 'Service', 'tax_status' => 1]);
    $this->oldTax = DB::table('taxes')->insertGetId(['name' => 'Old levy', 'tax_status' => 0]);
    foreach (['Setup_Shipping_section' => '1', 'shipping_type' => 'product_wise_shipping', 'Flash_deals' => '1', 'VAT_TAX_section' => '1', 'cash_payment' => '1'] as $type => $value) {
        DB::table('business_settings')->insert(['type' => $type, 'value' => $value]);
    }
    // The S row's photo belongs to someone else: it must round-trip.
    $this->photo = productEditImage();

    $this->product = createStoreProduct(['name' => 'Olive oil', 'slug' => 'olive-oil', 'added_by' => 'admin', 'user_id' => $this->owner->id, 'thumbnail_img' => null, 'unit_price' => 12.5, 'digital' => 0]);
    DB::table('products')->where('id', $this->product->id)->update([
        'category_id' => $this->child->id, 'brand_id' => $this->brand, 'unit' => 'bottle', 'weight' => 1.25, 'max_qty' => 24, 'min_qty' => 2,
        'tags' => 'oil,olive', 'photos' => null, 'old_photo' => null, 'video_provider' => 'youtube', 'video_link' => 'https://www.youtube.com/watch?v=abc123',
        'pdf' => null, 'discount' => 1.5, 'discount_type' => 'amount', 'discount_start_date' => productEditUtcTimestamp('2026-11-01 08:00:00'),
        'discount_end_date' => productEditUtcTimestamp('2026-11-30 20:30:00'), 'current_stock' => 4, 'external_link' => null, 'external_link_btn' => null,
        'description' => '<p>Olive</p>', 'meta_title' => 'Olive oil', 'meta_description' => 'Olive', 'meta_img' => null, 'shipping_type' => 'flat_rate',
        'shipping_cost' => 4.95, 'is_quantity_multiplied' => 1, 'est_shipping_days' => 3, 'low_stock_quantity' => 5, 'stock_visibility_state' => 'text',
        'cash_on_delivery' => 0, 'featured' => 1, 'todays_deal' => 0, 'published' => 1, 'approved' => 1, 'variant_product' => 1, 'colors' => '[]',
        'choice_options' => json_encode([['attribute_id' => (string) $this->size, 'values' => ['S', 'Extra Large']]]), 'attributes' => json_encode([(string) $this->size]),
    ]);
    DB::table('product_stocks')->where('product_id', $this->product->id)->delete();
    $this->small = DB::table('product_stocks')->insertGetId(['product_id' => $this->product->id, 'variant' => 'S', 'price' => 13.5, 'sku' => 'OIL-S', 'qty' => 4, 'image' => $this->photo->id]);
    $this->large = DB::table('product_stocks')->insertGetId(['product_id' => $this->product->id, 'variant' => 'ExtraLarge', 'price' => 15.25, 'sku' => null, 'qty' => 0, 'image' => null]);
    DB::table('product_translations')->insert([
        ['product_id' => $this->product->id, 'lang' => 'en', 'name' => 'Olive oil', 'unit' => 'bottle', 'description' => '<p>Olive</p>'],
        ['product_id' => $this->product->id, 'lang' => 'ar', 'name' => 'زيت زيتون', 'unit' => 'زجاجة', 'description' => '<p>زيت</p>'],
    ]);
    DB::table('product_variations')->insert(['product_id' => $this->product->id, 'name' => 'Cold pressed']);
    StoreProductBarcode::query()->create(['product_id' => $this->product->id, 'barcode' => '8710000000001']);
    DB::table('product_taxes')->insert(['product_id' => $this->product->id, 'tax_id' => $this->vat, 'tax' => 21, 'tax_type' => 'percent']);
    DB::table('flash_deal_products')->insert(['flash_deal_id' => $this->flashDeal, 'product_id' => $this->product->id]);

    // Noise: another product's rows and barcode never change.
    $this->other = createStoreProduct(['name' => 'Sunflower oil', 'slug' => 'sunflower-oil', 'added_by' => 'seller', 'thumbnail_img' => null]);
    StoreProductBarcode::query()->create(['product_id' => $this->other->id, 'barcode' => '8710000000002']);
    DB::table('product_translations')->insert(['product_id' => $this->other->id, 'lang' => 'ar', 'name' => 'زيت دوار الشمس', 'unit' => 'زجاجة', 'description' => null]);
});

/** @return array<string, list<mixed>> */
function productEditSnapshot(): array
{
    return collect(['products', 'product_stocks', 'product_translations', 'product_taxes', 'product_variations', 'product_barcodes', 'wms_product_barcodes', 'flash_deal_products'])
        ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->orderBy('id')->get()->toArray()])->all();
}

test('the in-house writer refuses a feed name change before touching related rows', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    DB::table('products')->where('id', $this->product->id)->update(['synced_at' => now()]);
    $before = productEditSnapshot();

    $this->post(route('products.update', $this->product), productEditForm(['name' => 'Store rename']))->assertSessionHasErrors('name');

    expect(productEditSnapshot())->toEqual($before);
});

/**
 * The form as the edit page posts it for the stored product in English,
 * unchanged.
 *
 * @return array<string, mixed>
 */
function productEditForm(array $overrides = []): array
{
    return [
        'lang' => 'en',
        'name' => 'Olive oil',
        'category_id' => test()->child->id,
        'brand_id' => test()->brand,
        'unit' => 'bottle',
        'weight' => '1.25',
        'max_qty' => '24',
        'min_qty' => '2',
        'tags' => 'oil,olive',
        'product_types' => ['Cold pressed'],
        'product_barcodes' => ['8710000000001'],
        'photos' => null,
        'thumbnail_img' => null,
        'old_photo' => null,
        'video_provider' => 'youtube',
        'video_link' => 'https://www.youtube.com/watch?v=abc123',
        'pdf' => null,
        'choice_options' => [['attribute_id' => test()->size, 'values' => ['S', 'Extra Large']]],
        'variants' => [
            ['variant' => 'S', 'price' => '13.5', 'sku' => 'OIL-S', 'qty' => '4', 'image' => test()->photo->id],
            ['variant' => 'ExtraLarge', 'price' => '15.25', 'sku' => null, 'qty' => '0', 'image' => null],
        ],
        'unit_price' => '12.5',
        'date_range' => '01-11-2026 08:00:00 to 30-11-2026 20:30:00',
        'discount' => '1.5',
        'discount_type' => 'amount',
        'current_stock' => '4',
        'sku' => 'OIL-S',
        'external_link' => null,
        'external_link_btn' => null,
        'description' => '<p>Olive</p>',
        'meta_title' => 'Olive oil',
        'meta_description' => 'Olive',
        'meta_img' => null,
        'slug' => 'olive-oil',
        'shipping_type' => 'flat_rate',
        'flat_shipping_cost' => '4.95',
        'is_quantity_multiplied' => '1',
        'est_shipping_days' => '3',
        'low_stock_quantity' => '5',
        'stock_visibility_state' => 'text',
        'cash_on_delivery' => null,
        'featured' => '1',
        'todays_deal' => null,
        'flash_deal_id' => null,
        'flash_discount' => '1.5',
        'flash_discount_type' => null,
        'tax_id' => [test()->vat, test()->service],
        'tax' => ['21', '0'],
        'tax_type' => ['percent', 'amount'],
        ...$overrides,
    ];
}

function productEditImage(?int $userId = null, string $type = 'image'): Upload
{
    return Upload::factory()->create(['user_id' => $userId ?? createStoreUser('customer')->id, 'type' => $type, 'file_original_name' => 'File '.Str::random(4), 'file_size' => 64, 'external_link' => 'https://example.com/'.Str::random(6).'.png']);
}

/** @return list<array<string, mixed>> */
function productEditStocks(int $productId): array
{
    return DB::table('product_stocks')->where('product_id', $productId)->orderBy('id')->get(['id', 'variant', 'price', 'sku', 'qty', 'image'])->map(fn ($row) => (array) $row)->all();
}

function productEditUtcTimestamp(string $dateTime): int
{
    return (new DateTimeImmutable($dateTime, new DateTimeZone('UTC')))->getTimestamp();
}

test('the edit form renders Inertia with the full payload in the requested language', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    $this->get(route('products.admin.edit', ['product' => $this->product->id, 'lang' => 'ar']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Product/Products/Edit')
        ->where('product', [
            'id' => $this->product->id, 'added_by' => 'admin', 'category_id' => $this->child->id, 'brand_id' => $this->brand, 'weight' => '1.25',
            'max_qty' => '24', 'min_qty' => '2', 'tags' => 'oil,olive', 'photos' => null, 'old_photo' => null, 'thumbnail_img' => null,
            'video_provider' => 'youtube', 'video_link' => 'https://www.youtube.com/watch?v=abc123', 'pdf' => null, 'unit_price' => '12.5',
            'discount' => '1.5', 'discount_type' => 'amount', 'date_range' => '01-11-2026 08:00:00 to 30-11-2026 20:30:00',
            'current_stock' => '4', 'sku' => 'OIL-S', 'external_link' => null, 'external_link_btn' => null, 'meta_title' => 'Olive oil',
            'meta_description' => 'Olive', 'meta_img' => null, 'slug' => 'olive-oil', 'shipping_type' => 'flat_rate', 'flat_shipping_cost' => '4.95',
            'is_quantity_multiplied' => true, 'est_shipping_days' => '3', 'low_stock_quantity' => '5', 'stock_visibility_state' => 'text',
            'cash_on_delivery' => false, 'featured' => true, 'todays_deal' => false, 'flash_deal_id' => $this->flashDeal,
            'product_types' => ['Cold pressed'], 'product_barcodes' => ['8710000000001'],
        ])
        ->where('name', 'زيت زيتون')
        ->where('unit', 'زجاجة')
        ->where('description', '<p>زيت</p>')
        ->where('lang', 'ar')
        ->where('languages', [['code' => 'en', 'name' => 'English'], ['code' => 'ar', 'name' => 'Arabic']])
        ->where('listType', 'admin')
        ->where('categories', [['id' => $this->root->id, 'name' => 'Pantry'], ['id' => $this->child->id, 'name' => '-- Oils']])
        ->where('brands', [['id' => $this->brand, 'name' => 'Olivia']])
        ->where('colors', [['id' => $this->blue, 'name' => 'Light Blue', 'code' => '#ADD8E6'], ['id' => $this->red, 'name' => 'Red', 'code' => '#FF0000']])
        ->where('attributes', [['id' => $this->size, 'name' => 'Size'], ['id' => $this->material, 'name' => 'Bottle']])
        ->where('flashDeals', [['id' => $this->flashDeal, 'name' => 'Autumn deals']])
        ->where('taxes', [
            ['id' => $this->vat, 'name' => 'VAT', 'tax' => '21', 'tax_type' => 'percent'],
            ['id' => $this->service, 'name' => 'Service', 'tax' => '0', 'tax_type' => 'amount'],
        ])
        ->where('sections', ['shipping' => true, 'productWiseShipping' => true, 'flashDeals' => true, 'vat' => true, 'cashOnDelivery' => true])
        ->where('variants', [
            'unmatched' => [],
            'colors_active' => false,
            'colors' => [],
            'choice_options' => [['attribute_id' => $this->size, 'values' => ['S', 'Extra Large']]],
            'attribute_values' => [$this->size => [
                ['id' => $this->values['S'], 'value' => 'S'],
                ['id' => $this->values['M'], 'value' => 'M'],
                ['id' => $this->values['Extra Large'], 'value' => 'Extra Large'],
            ]],
            // The foreign photo round-trips without a preview for this caller.
            'rows' => [
                ['variant' => 'S', 'price' => '13.5', 'sku' => 'OIL-S', 'qty' => '4', 'image' => (string) $this->photo->id, 'files' => []],
                ['variant' => 'ExtraLarge', 'price' => '15.25', 'sku' => '', 'qty' => '0', 'image' => null, 'files' => []],
            ],
        ])
        ->where('initialFiles', ['photos' => [], 'thumbnail_img' => [], 'old_photo' => [], 'pdf' => [], 'meta_img' => []]));
    $this->get(route('products.admin.edit', ['product' => $this->product->id, 'lang' => 'ar']), inertiaHeaders())->assertOk()->assertHeader('X-Inertia', 'true');
});

test('the edit form opens in the default language without a lang and refuses an unknown one', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    $this->get(route('products.admin.edit', $this->product->id))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('lang', 'en')->where('name', 'Olive oil')->where('unit', 'bottle')->where('description', '<p>Olive</p>'));
    // A language without a translation shows the base fields.
    DB::table('product_translations')->where('product_id', $this->product->id)->where('lang', 'ar')->delete();
    $this->get(route('products.admin.edit', ['product' => $this->product->id, 'lang' => 'ar']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('lang', 'ar')->where('name', 'Olive oil')->where('unit', 'bottle'));
    $this->get(route('products.admin.edit', ['product' => $this->product->id, 'lang' => 'nl']))->assertSessionHasErrors('lang');
});

test('the seller list opens the same form on its own route', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    $this->get(route('products.seller.edit', ['product' => $this->other->id, 'lang' => 'ar']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Product/Products/Edit')
        ->where('listType', 'seller')->where('product.id', $this->other->id)->where('product.added_by', 'seller')
        ->where('name', 'زيت دوار الشمس')->where('variants.rows', []));
});

test('library previews follow the canonical upload policy', function () {
    $staff = createStoreStaff('product_edit', 'view_uploads');
    actingAsStoreUser($staff);
    $own = productEditImage($staff->id);
    DB::table('products')->where('id', $this->product->id)->update(['thumbnail_img' => $own->id, 'photos' => $own->id.','.$this->photo->id]);
    $this->get(route('products.admin.edit', $this->product->id))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('initialFiles.thumbnail_img.0.id', $own->id)
        ->where('initialFiles.photos.0.id', $own->id)
        ->where('initialFiles.photos.1.id', $this->photo->id)
        ->where('variants.rows.0.files.0.id', $this->photo->id)
        ->where('variants.rows.1.files', []));
});

test('stored colours, attributes and values that no longer exist are left out, and the rows follow what is left', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    DB::table('products')->where('id', $this->product->id)->update([
        'colors' => json_encode(['#ADD8E6', '#000001']),
        'choice_options' => json_encode([
            ['attribute_id' => (string) $this->material, 'values' => ['Glass', 'Retired']],
            ['attribute_id' => '999999', 'values' => ['X']],
            ['attribute_id' => (string) $this->size, 'values' => ['S']],
        ]),
    ]);
    DB::table('attribute_values')->insert(['attribute_id' => $this->size, 'value' => 'S']);
    $this->get(route('products.admin.edit', $this->product->id))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('variants.colors_active', true)
        ->where('variants.colors', ['#ADD8E6'])
        // Stored order, not list order: it names the stored variants.
        ->where('variants.choice_options', [['attribute_id' => $this->material, 'values' => ['Glass']], ['attribute_id' => $this->size, 'values' => ['S']]])
        // A value listed twice is offered once.
        ->where('variants.attribute_values.'.$this->size.'.0.value', 'S')
        ->where('variants.attribute_values.'.$this->size.'.1.value', 'M')
        ->where('variants.rows.0.variant', 'Light Blue-Glass-S')
        ->where('variants.rows.0.price', '12.5')
        ->where('variants.rows.0.qty', '10'));
});

test('a digital product goes to its own form, and the edit routes refuse it', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    $digital = createStoreProduct(['name' => 'Guide', 'digital' => 1, 'thumbnail_img' => null]);
    $before = productEditSnapshot();
    $this->get(route('products.admin.edit', ['product' => $digital->id, 'lang' => 'ar']))
        ->assertRedirect(route('digitalproducts.edit', ['product' => $digital->id, 'lang' => 'ar']));
    $this->get(route('products.seller.edit', $digital->id))->assertRedirect(route('digitalproducts.edit', ['product' => $digital->id]));
    $this->post(route('products.update', $digital), productEditForm())->assertNotFound();
    $this->postJson(route('products.edit_variant_combinations', $digital), ['choice_options' => [['attribute_id' => $this->size, 'values' => ['S']]]])->assertNotFound();
    expect(productEditSnapshot())->toEqual($before);
});

test('a missing product is a 404 on every edit route', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    $before = productEditSnapshot();
    $this->get(route('products.admin.edit', 999999))->assertNotFound();
    $this->get(route('products.seller.edit', 999999))->assertNotFound();
    $this->post(route('products.update', 999999), productEditForm())->assertNotFound();
    $this->postJson(route('products.edit_variant_combinations', 999999), [])->assertNotFound();
    expect(productEditSnapshot())->toEqual($before);
});

test('saving the unchanged form keeps every stock row, its id and its values, and returns to the form', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    $stocks = productEditStocks($this->product->id);
    $level = DB::transactionLevel();
    $committed = [];
    Artisan::shouldReceive('call')->with('view:clear')->once()->andReturnUsing(function () use (&$committed, $level) {
        $committed[] = DB::transactionLevel() === $level;

        return 0;
    });
    Artisan::shouldReceive('call')->with('cache:clear')->once()->andReturn(0);

    $this->post(route('products.update', $this->product), productEditForm())
        ->assertSessionHasNoErrors()->assertRedirect(route('products.admin.edit', ['product' => $this->product->id, 'lang' => 'en']));

    expect($committed)->toBe([true])
        ->and(productEditStocks($this->product->id))->toEqual($stocks)
        ->and(productEditStocks($this->other->id))->toHaveCount(1)
        ->and(collect(DB::table('products')->where('id', $this->product->id)->first())->only(['slug', 'variant_product', 'choice_options', 'colors', 'published', 'approved', 'user_id'])->all())
        ->toEqual([
            'slug' => 'olive-oil', 'variant_product' => 1, 'colors' => '[]', 'published' => 1, 'approved' => 1, 'user_id' => $this->owner->id,
            'choice_options' => '[{"attribute_id":"'.$this->size.'","values":["S","Extra Large"]}]',
        ]);
});

test('changing the choices keeps the rows of the variants that remain, adds the new ones and deletes the rest', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    Artisan::shouldReceive('call')->andReturn(0);
    // The page asks for the rows of the new choice: S keeps its stored row,
    // M starts at the unit price as typed.
    $rows = $this->postJson(route('products.edit_variant_combinations', $this->product), ['choice_options' => [['attribute_id' => $this->size, 'values' => ['S', 'M']]], 'unit_price' => '14'])
        ->assertOk()->assertExactJson([
            ['variant' => 'S', 'price' => '13.5', 'sku' => 'OIL-S', 'qty' => '4', 'image' => (string) $this->photo->id, 'files' => []],
            ['variant' => 'M', 'price' => '14', 'sku' => '', 'qty' => '10', 'image' => null, 'files' => []],
        ])->json();

    $this->post(route('products.update', $this->product), productEditForm([
        'unit_price' => '14',
        'choice_options' => [['attribute_id' => $this->size, 'values' => ['S', 'M']]],
        'variants' => array_map(fn (array $row) => ['variant' => $row['variant'], 'price' => $row['price'], 'sku' => $row['sku'] ?: null, 'qty' => $row['qty'], 'image' => $row['image']], $rows),
    ]))->assertSessionHasNoErrors();

    $stocks = productEditStocks($this->product->id);
    expect(array_column($stocks, 'id'))->toHaveCount(2)->and($stocks[0]['id'])->toBe($this->small)
        ->and(array_map(fn (array $row) => array_diff_key($row, ['id' => true]), $stocks))->toEqual([
            ['variant' => 'S', 'price' => 13.5, 'sku' => 'OIL-S', 'qty' => 4, 'image' => $this->photo->id],
            ['variant' => 'M', 'price' => 14, 'sku' => null, 'qty' => 10, 'image' => null],
        ])
        ->and(DB::table('product_stocks')->where('id', $this->large)->exists())->toBeFalse()
        ->and(DB::table('products')->where('id', $this->product->id)->value('choice_options'))
        ->toBe('[{"attribute_id":"'.$this->size.'","values":["S","M"]}]');
});

test('dropping every choice replaces the variant rows with one row at the unit price and clears the variant flag', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    Artisan::shouldReceive('call')->andReturn(0);
    $this->post(route('products.update', $this->product), productEditForm(['choice_options' => [], 'variants' => null, 'current_stock' => '7', 'sku' => 'OIL']))
        ->assertSessionHasNoErrors();
    expect(array_map(fn (array $row) => array_diff_key($row, ['id' => true]), productEditStocks($this->product->id)))
        ->toEqual([['variant' => '', 'price' => 12.5, 'sku' => 'OIL', 'qty' => 7, 'image' => null]])
        ->and(collect(DB::table('products')->where('id', $this->product->id)->first())->only(['variant_product', 'choice_options', 'attributes', 'current_stock'])->all())
        ->toEqual(['variant_product' => 0, 'choice_options' => '[]', 'attributes' => '[]', 'current_stock' => 7]);
});

test('the edit variant endpoint names the rows as on create and refuses unknown values', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    $this->postJson(route('products.edit_variant_combinations', $this->product), ['unit_price' => '1'])->assertOk()->assertExactJson([]);
    $this->postJson(route('products.edit_variant_combinations', $this->product), ['colors_active' => true, 'colors' => ['#ADD8E6'], 'choice_options' => [['attribute_id' => $this->size, 'values' => ['S']]], 'unit_price' => '2'])
        ->assertOk()->assertJsonPath('0.variant', 'Light Blue-S')->assertJsonPath('0.price', '2')->assertJsonCount(1);
    $this->postJson(route('products.edit_variant_combinations', $this->product), ['choice_options' => [['attribute_id' => $this->size, 'values' => ['Glass']]]])
        ->assertUnprocessable()->assertJsonValidationErrors('choice_options.0.values.0');
});

test('a default-language save writes the base fields and its translation and keeps the others', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    Artisan::shouldReceive('call')->andReturn(0);
    $this->post(route('products.update', $this->product), productEditForm([
        'name' => 'Extra virgin olive oil', 'unit' => 'jar', 'description' => '<p>Cold</p>', 'weight' => '2', 'tags' => ' oil , press,oil',
        'brand_id' => null, 'meta_title' => null, 'meta_description' => null, 'featured' => null, 'todays_deal' => '1', 'cash_on_delivery' => '1',
        'is_quantity_multiplied' => null, 'shipping_type' => 'free', 'date_range' => null, 'product_types' => ['Organic', ''], 'product_barcodes' => ['8710000000001', '4006381333931'],
        'tax' => ['9', '1.5'], 'tax_type' => ['percent', 'amount'],
    ]))->assertSessionHasNoErrors()->assertRedirect(route('products.admin.edit', ['product' => $this->product->id, 'lang' => 'en']));

    expect(collect(DB::table('products')->where('id', $this->product->id)->first())->only([
        'name', 'unit', 'description', 'weight', 'tags', 'brand_id', 'meta_title', 'meta_description', 'featured', 'todays_deal', 'cash_on_delivery',
        'is_quantity_multiplied', 'shipping_type', 'shipping_cost', 'discount_start_date', 'discount_end_date', 'slug', 'added_by', 'digital',
    ])->all())->toEqual([
        'name' => 'Extra virgin olive oil', 'unit' => 'jar', 'description' => '<p>Cold</p>', 'weight' => 2, 'tags' => 'oil,press', 'brand_id' => null,
        'meta_title' => 'Extra virgin olive oil', 'meta_description' => 'Cold', 'featured' => 0, 'todays_deal' => 1, 'cash_on_delivery' => 1,
        'is_quantity_multiplied' => 0, 'shipping_type' => 'free', 'shipping_cost' => 0, 'discount_start_date' => null, 'discount_end_date' => null,
        // A typed slug is kept as it is: the name change does not move the URL.
        'slug' => 'olive-oil', 'added_by' => 'admin', 'digital' => 0,
    ])->and(DB::table('product_translations')->where('product_id', $this->product->id)->orderBy('lang')->get(['lang', 'name', 'unit', 'description'])->map(fn ($row) => (array) $row)->all())
        ->toBe([
            ['lang' => 'ar', 'name' => 'زيت زيتون', 'unit' => 'زجاجة', 'description' => '<p>زيت</p>'],
            ['lang' => 'en', 'name' => 'Extra virgin olive oil', 'unit' => 'jar', 'description' => '<p>Cold</p>'],
        ])
        ->and(DB::table('product_variations')->where('product_id', $this->product->id)->pluck('name')->all())->toBe(['Organic'])
        ->and(DB::table('product_barcodes')->orderBy('id')->get(['product_id', 'barcode'])->map(fn ($row) => [$row->product_id, $row->barcode])->all())->toBe([
            [$this->other->id, '8710000000002'], [$this->product->id, '8710000000001'], [$this->product->id, '4006381333931'],
        ])
        ->and(DB::table('product_taxes')->where('product_id', $this->product->id)->orderBy('id')->get(['tax_id', 'tax', 'tax_type'])->map(fn ($row) => (array) $row)->all())
        ->toEqual([['tax_id' => $this->vat, 'tax' => 9, 'tax_type' => 'percent'], ['tax_id' => $this->service, 'tax' => 1.5, 'tax_type' => 'amount']]);
});

test('another language save writes only its translation and the shared fields, and returns to that language', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    Artisan::shouldReceive('call')->andReturn(0);
    $this->post(route('products.update', $this->product), productEditForm([
        'lang' => 'ar', 'name' => 'زيت زيتون بكر', 'unit' => 'برطمان', 'description' => '<p>بارد</p>', 'weight' => '3', 'min_qty' => '1',
    ]))->assertSessionHasNoErrors()->assertRedirect(route('products.admin.edit', ['product' => $this->product->id, 'lang' => 'ar']));

    expect(collect(DB::table('products')->where('id', $this->product->id)->first())->only(['name', 'unit', 'description', 'weight', 'min_qty'])->all())
        ->toEqual(['name' => 'Olive oil', 'unit' => 'bottle', 'description' => '<p>Olive</p>', 'weight' => 3, 'min_qty' => 1])
        ->and(DB::table('product_translations')->where('product_id', $this->product->id)->orderBy('lang')->get(['lang', 'name', 'unit', 'description'])->map(fn ($row) => (array) $row)->all())
        ->toBe([
            ['lang' => 'ar', 'name' => 'زيت زيتون بكر', 'unit' => 'برطمان', 'description' => '<p>بارد</p>'],
            ['lang' => 'en', 'name' => 'Olive oil', 'unit' => 'bottle', 'description' => '<p>Olive</p>'],
        ]);
    // A language with no translation yet gains one.
    DB::table('product_translations')->where('product_id', $this->product->id)->where('lang', 'ar')->delete();
    $this->post(route('products.update', $this->product), productEditForm(['lang' => 'ar', 'name' => 'زيت', 'unit' => 'علبة']))->assertSessionHasNoErrors();
    expect(ProductTranslation::query()->where('product_id', $this->product->id)->where('lang', 'ar')->sole()->getAttribute('name'))->toBe('زيت');
});

test('a seller product returns to the seller route', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    Artisan::shouldReceive('call')->andReturn(0);
    DB::table('products')->where('id', $this->product->id)->update(['added_by' => 'seller']);
    $this->post(route('products.update', $this->product), productEditForm())
        ->assertSessionHasNoErrors()->assertRedirect(route('products.seller.edit', ['product' => $this->product->id, 'lang' => 'en']));
    expect(DB::table('products')->where('id', $this->product->id)->value('added_by'))->toBe('seller');
});

test('the slug is normalised, must be free, and is regenerated from the base name when left empty', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    Artisan::shouldReceive('call')->andReturn(0);
    $before = productEditSnapshot();
    $this->post(route('products.update', $this->product), productEditForm(['slug' => 'Sunflower Oil']))->assertSessionHasErrors('slug');
    expect(productEditSnapshot())->toEqual($before);

    $this->post(route('products.update', $this->product), productEditForm(['slug' => 'Cold Pressed Oil']))->assertSessionHasNoErrors();
    expect(DB::table('products')->where('id', $this->product->id)->value('slug'))->toBe('cold-pressed-oil');

    // Blank: numbered after the other products whose slug begins with it.
    createStoreProduct(['name' => 'Olive oil 2', 'slug' => 'olive-oil-press', 'thumbnail_img' => null]);
    $this->post(route('products.update', $this->product), productEditForm(['slug' => null]))->assertSessionHasNoErrors();
    expect(DB::table('products')->where('id', $this->product->id)->value('slug'))->toBe('olive-oil-2');
});

test('a chosen flash deal takes the flash discount and the deal period', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    Artisan::shouldReceive('call')->andReturn(0);
    $this->post(route('products.update', $this->product), productEditForm(['flash_deal_id' => $this->flashDeal, 'flash_discount' => '10', 'flash_discount_type' => 'percent']))
        ->assertSessionHasNoErrors();
    expect(DB::table('flash_deal_products')->where('product_id', $this->product->id)->count())->toBe(1)
        ->and(collect(DB::table('products')->where('id', $this->product->id)->first())->only(['discount', 'discount_type', 'discount_start_date', 'discount_end_date'])->all())
        ->toEqual(['discount' => 10, 'discount_type' => 'percent', 'discount_start_date' => productEditUtcTimestamp('2026-11-01 00:00:00'), 'discount_end_date' => productEditUtcTimestamp('2026-11-07 23:59:59')]);
});

test('fields whose settings hide them keep their stored values', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    Artisan::shouldReceive('call')->andReturn(0);
    DB::table('business_settings')->whereIn('type', ['Setup_Shipping_section', 'Flash_deals', 'VAT_TAX_section', 'cash_payment'])->update(['value' => '0']);
    DB::table('products')->where('id', $this->product->id)->update(['cash_on_delivery' => 1]);
    $this->post(route('products.update', $this->product), productEditForm([
        'cash_on_delivery' => null, 'shipping_type' => 'nonsense', 'flat_shipping_cost' => '99', 'is_quantity_multiplied' => null, 'est_shipping_days' => '9',
        'flash_deal_id' => $this->flashDeal, 'flash_discount_type' => 'percent', 'tax_id' => [$this->service], 'tax' => ['5'], 'tax_type' => ['amount'],
    ]))->assertSessionHasNoErrors();
    expect(collect(DB::table('products')->where('id', $this->product->id)->first())->only(['cash_on_delivery', 'shipping_type', 'shipping_cost', 'is_quantity_multiplied', 'est_shipping_days', 'discount'])->all())
        ->toEqual(['cash_on_delivery' => 1, 'shipping_type' => 'flat_rate', 'shipping_cost' => 4.95, 'is_quantity_multiplied' => 1, 'est_shipping_days' => 3, 'discount' => 1.5])
        ->and(DB::table('product_taxes')->where('product_id', $this->product->id)->get(['tax_id', 'tax'])->map(fn ($row) => (array) $row)->all())->toEqual([['tax_id' => $this->vat, 'tax' => 21]]);
});

test('stored uploads round-trip whoever owns them; new references follow the library rules', function () {
    $staff = createStoreStaff('product_edit');
    actingAsStoreUser($staff);
    Artisan::shouldReceive('call')->andReturn(0);
    $foreign = productEditImage();
    $stored = productEditImage();
    $storedPdf = productEditImage(null, 'document');
    DB::table('products')->where('id', $this->product->id)->update(['thumbnail_img' => $stored->id, 'photos' => (string) $stored->id, 'old_photo' => (string) $stored->id, 'meta_img' => (string) $stored->id, 'pdf' => (string) $storedPdf->id]);
    $kept = ['thumbnail_img' => $stored->id, 'photos' => (string) $stored->id, 'old_photo' => (string) $stored->id, 'meta_img' => $stored->id, 'pdf' => $storedPdf->id];
    $before = productEditSnapshot();
    foreach ([
        ['thumbnail_img' => $foreign->id], ['photos' => $stored->id.','.$foreign->id], ['old_photo' => (string) $foreign->id], ['meta_img' => $foreign->id],
        ['pdf' => productEditImage(null, 'document')->id],
    ] as $overrides) {
        $this->post(route('products.update', $this->product), productEditForm([...$kept, ...$overrides]))->assertSessionHasErrors(array_key_first($overrides));
    }
    // The S row's foreign photo is retained only on its own variant.
    $form = productEditForm($kept);
    $form['variants'][1]['image'] = $this->photo->id;
    $this->post(route('products.update', $this->product), $form)->assertSessionHasErrors('variants.1.image');
    expect(productEditSnapshot())->toEqual($before);

    $this->post(route('products.update', $this->product), productEditForm($kept))->assertSessionHasNoErrors();
    expect(collect(DB::table('products')->where('id', $this->product->id)->first())->only(['thumbnail_img', 'photos', 'old_photo', 'meta_img', 'pdf'])->all())
        ->toEqual(['thumbnail_img' => $stored->id, 'photos' => (string) $stored->id, 'old_photo' => (string) $stored->id, 'meta_img' => (string) $stored->id, 'pdf' => (string) $storedPdf->id])
        ->and(DB::table('product_stocks')->where('id', $this->small)->value('image'))->toEqual($this->photo->id);

    actingAsStoreUser(createStoreStaff('product_edit', 'view_uploads'));
    $this->post(route('products.update', $this->product), productEditForm([...$kept, 'thumbnail_img' => $foreign->id, 'old_photo' => $stored->id.','.$foreign->id]))->assertSessionHasNoErrors();
    expect(DB::table('products')->where('id', $this->product->id)->value('old_photo'))->toBe($stored->id.','.$foreign->id);
});

test('the product\'s own store barcodes may be posted again; another product\'s may not', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    Artisan::shouldReceive('call')->andReturn(0);
    $before = productEditSnapshot();
    $this->post(route('products.update', $this->product), productEditForm(['product_barcodes' => ['8710000000001', '8710000000002']]))->assertSessionHasErrors('product_barcodes.1');
    $this->post(route('products.update', $this->product), productEditForm(['product_barcodes' => ['123', '123']]))->assertSessionHasErrors('product_barcodes.1');
    expect(productEditSnapshot())->toEqual($before);
    $this->post(route('products.update', $this->product), productEditForm(['product_barcodes' => []]))->assertSessionHasNoErrors();
    expect(DB::table('product_barcodes')->pluck('barcode')->all())->toBe(['8710000000002']);
});

test('the update validates every field and refuses rows that do not match the choices', function (array|Closure $overrides, string $field) {
    actingAsStoreUser(createStoreStaff('product_edit'));
    $before = productEditSnapshot();
    $this->post(route('products.update', $this->product), productEditForm($overrides instanceof Closure ? $overrides() : $overrides))->assertSessionHasErrors($field);
    expect(productEditSnapshot())->toEqual($before);
})->with([
    'missing language' => [['lang' => null], 'lang'],
    'unknown language' => [['lang' => 'nl'], 'lang'],
    'missing name' => [['name' => ''], 'name'],
    'name over 200' => [['name' => str_repeat('a', 201)], 'name'],
    'digital category' => [fn () => ['category_id' => test()->digitalRoot->id], 'category_id'],
    'unit over 20' => [['unit' => str_repeat('u', 21)], 'unit'],
    'minimum quantity zero' => [['min_qty' => '0'], 'min_qty'],
    'tags over 500' => [['tags' => str_repeat('t', 501)], 'tags'],
    'Otajer images not ids' => [['old_photo' => '1,a'], 'old_photo'],
    'video link not http' => [['video_link' => 'ftp://example.com/video'], 'video_link'],
    'flat discount not below the price' => [['discount' => '12.5'], 'discount'],
    'malformed date range' => [['date_range' => '2026-11-01 to 2026-11-30'], 'date_range'],
    'unknown stock visibility' => [['stock_visibility_state' => 'blink'], 'stock_visibility_state'],
    'inactive tax' => [fn () => ['tax_id' => [test()->oldTax], 'tax' => ['1'], 'tax_type' => ['amount']], 'tax_id.0'],
    'rows missing' => [['variants' => null], 'variants'],
    'renamed row' => [fn () => ['variants' => [['variant' => 'S', 'price' => '1', 'qty' => '1'], ['variant' => 'L', 'price' => '1', 'qty' => '1']]], 'variants.1.variant'],
    'rows in another order' => [fn () => ['variants' => array_reverse(productEditForm()['variants'])], 'variants.0.variant'],
    'value of another attribute' => [fn () => ['choice_options' => [['attribute_id' => test()->size, 'values' => ['Glass']]], 'variants' => [['variant' => 'Glass', 'price' => '1', 'qty' => '1']]], 'choice_options.0.values.0'],
    'attribute without a value' => [fn () => ['choice_options' => [['attribute_id' => test()->size, 'values' => []]], 'variants' => null], 'choice_options.0.values'],
]);

test('a failed update rolls every table back and clears no cache', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    $before = productEditSnapshot();
    Artisan::shouldReceive('call')->never();
    StoreProductBarcode::saving(fn () => throw new RuntimeException('Persistence failure'));
    $this->withoutExceptionHandling();
    try {
        expect(fn () => $this->post(route('products.update', $this->product), productEditForm([
            'name' => 'Renamed', 'choice_options' => [['attribute_id' => $this->size, 'values' => ['S', 'M']]],
            'variants' => [['variant' => 'S', 'price' => '1', 'qty' => '1'], ['variant' => 'M', 'price' => '1', 'qty' => '1']],
            'flash_deal_id' => $this->flashDeal, 'flash_discount_type' => 'amount',
        ])))->toThrow(RuntimeException::class);
        expect(productEditSnapshot())->toEqual($before);
    } finally {
        StoreProductBarcode::flushEventListeners();
    }
});

test('the WAF still refuses a script payload before anything is written, and the description is cleaned', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    Artisan::shouldReceive('call')->andReturn(0);
    $before = productEditSnapshot();
    $this->post(route('products.update', $this->product), productEditForm(['description' => '<p>x</p><script>alert(1)</script>']))->assertForbidden();
    $this->post(route('products.update', $this->product), productEditForm(['description' => '<img src="x" onerror="alert(1)">']))->assertForbidden();
    expect(productEditSnapshot())->toEqual($before);

    $this->withoutMiddleware(BlockMaliciousRequests::class);
    $this->post(route('products.update', $this->product), productEditForm(['lang' => 'ar', 'description' => '<p>Kept</p><p onclick="alert(1)">x</p><a href="javascript:alert(1)">y</a>']))
        ->assertSessionHasNoErrors();
    $stored = DB::table('product_translations')->where('product_id', $this->product->id)->where('lang', 'ar')->value('description');
    expect($stored)->toStartWith('<p>Kept</p>')->not->toContain('onclick')->not->toContain('javascript')
        ->and(clean_html($stored))->toBe($stored);
    // The edit prop is cleaned too.
    DB::table('product_translations')->where('product_id', $this->product->id)->where('lang', 'ar')->update(['description' => '<p>Old</p><script>alert(1)</script>']);
    $this->get(route('products.admin.edit', ['product' => $this->product->id, 'lang' => 'ar']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('description', '<p>Old</p>'));
});

test('every edit route refuses unauthorized callers and writes nothing', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    $before = productEditSnapshot();
    assertStoreCallerRefused($this->get(route('products.admin.edit', $this->product)), $caller);
    assertStoreCallerRefused($this->get(route('products.seller.edit', $this->product)), $caller);
    assertStoreCallerRefused($this->post(route('products.update', $this->product), productEditForm(['name' => 'Taken over'])), $caller);
    assertStoreCallerRefused($this->post(route('products.edit_variant_combinations', $this->product), ['choice_options' => [['attribute_id' => $this->size, 'values' => ['S']]]]), $caller);
    expect(productEditSnapshot())->toEqual($before);
})->with('refused store admin callers');

test('only product_edit opens and saves the edit form', function () {
    $before = productEditSnapshot();
    actingAsStoreUser(createStoreStaff('add_new_product', 'show_in_house_products', 'show_seller_products', 'edit_digital_product'));
    $this->get(route('products.admin.edit', $this->product))->assertForbidden();
    $this->get(route('products.seller.edit', $this->product))->assertForbidden();
    $this->post(route('products.update', $this->product), productEditForm(['name' => 'Taken over']))->assertForbidden();
    $this->postJson(route('products.edit_variant_combinations', $this->product), [])->assertForbidden();
    expect(productEditSnapshot())->toEqual($before);
});

test('WMS authentication opens no product edit form and writes nothing', function () {
    $this->actingAs(User::factory()->create(), 'web');
    $before = productEditSnapshot();
    $this->get(route('products.admin.edit', $this->product))->assertRedirect(route('shop.login'));
    $this->post(route('products.update', $this->product), productEditForm())->assertRedirect(route('shop.login'));
    $this->post(route('products.edit_variant_combinations', $this->product), [])->assertRedirect(route('shop.login'));
    expect(productEditSnapshot())->toEqual($before);
});

test('the edited product shows its new name on the storefront', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    Artisan::shouldReceive('call')->andReturn(0);
    // Without variants: the storefront's variant markup is not under test here.
    $this->post(route('products.update', $this->product), productEditForm(['name' => 'Renamed olive oil', 'description' => '<p><strong>Fresh</strong> oil</p>', 'choice_options' => [], 'variants' => null]))->assertSessionHasNoErrors();
    $this->get(route('product', 'olive-oil'))->assertOk()->assertSee('Renamed olive oil')->assertSee('<strong>Fresh</strong> oil', false);
});
