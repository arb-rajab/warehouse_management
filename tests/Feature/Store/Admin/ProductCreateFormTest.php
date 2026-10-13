<?php

use App\Http\Controllers\ProductController as LegacyProductController;
use App\Http\Controllers\Store\Admin\ProductController;
use App\Http\Middleware\BlockMaliciousRequests;
use App\Models\Category;
use App\Models\Language;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\StoreProductBarcode;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    seedStoreCurrency();
    config(['app.default_language' => 'en']);
    Language::forceCreate(['code' => 'en', 'name' => 'English', 'rtl' => 0]);
    Language::forceCreate(['code' => 'ar', 'name' => 'Arabic', 'rtl' => 1]);
    // The vendor's own account comes first and must not own the product.
    createStoreAdmin(raizerAdmin: true);
    $this->owner = createStoreAdmin();
    $this->root = Category::forceCreate(['name' => 'Pantry', 'parent_id' => 0, 'level' => 0, 'slug' => 'pantry', 'digital' => 0]);
    $this->child = Category::forceCreate(['name' => 'Oils', 'parent_id' => $this->root->id, 'level' => 1, 'slug' => 'oils', 'digital' => 0]);
    $this->digitalRoot = Category::forceCreate(['name' => 'E-books', 'parent_id' => 0, 'level' => 0, 'slug' => 'e-books', 'digital' => 1]);
    DB::table('category_translations')->insert(['category_id' => $this->child->id, 'lang' => 'ar', 'name' => 'زيوت']);
    $this->brand = DB::table('brands')->insertGetId(['name' => 'Olivia', 'slug' => 'olivia']);
    $this->otherBrand = DB::table('brands')->insertGetId(['name' => 'Zest', 'slug' => 'zest']);
    DB::table('brand_translations')->insert(['brand_id' => $this->brand, 'lang' => 'ar', 'name' => 'أوليفيا']);
    $this->red = DB::table('colors')->insertGetId(['name' => 'Red', 'code' => '#FF0000']);
    $this->blue = DB::table('colors')->insertGetId(['name' => 'Light Blue', 'code' => '#ADD8E6']);
    $this->size = DB::table('attributes')->insertGetId(['name' => 'Size']);
    $this->material = DB::table('attributes')->insertGetId(['name' => 'Bottle']);
    DB::table('attribute_translations')->insert(['attribute_id' => $this->size, 'lang' => 'ar', 'name' => 'المقاس']);
    foreach ([[$this->size, 'S'], [$this->size, 'M'], [$this->size, 'Extra Large'], [$this->material, '1.5 L'], [$this->material, 'Glass']] as [$attribute, $value]) {
        DB::table('attribute_values')->insert(['attribute_id' => $attribute, 'value' => $value]);
    }
    $this->flashDeal = DB::table('flash_deals')->insertGetId(['title' => 'Autumn deals', 'status' => 1, 'start_date' => productUtcTimestamp('2026-11-01 00:00:00'), 'end_date' => productUtcTimestamp('2026-11-07 23:59:59'), 'slug' => 'autumn']);
    $this->pastDeal = DB::table('flash_deals')->insertGetId(['title' => 'Summer deals', 'status' => 0, 'slug' => 'summer']);
    $this->vat = DB::table('taxes')->insertGetId(['name' => 'VAT', 'tax_status' => 1]);
    $this->service = DB::table('taxes')->insertGetId(['name' => 'Service', 'tax_status' => 1]);
    $this->oldTax = DB::table('taxes')->insertGetId(['name' => 'Old levy', 'tax_status' => 0]);
    foreach (['Setup_Shipping_section' => '1', 'shipping_type' => 'product_wise_shipping', 'Flash_deals' => '1', 'VAT_TAX_section' => '1', 'cash_payment' => '1'] as $type => $value) {
        DB::table('business_settings')->insert(['type' => $type, 'value' => $value]);
    }
    $this->existing = createStoreProduct(['name' => 'Olive oil', 'slug' => 'olive-oil', 'thumbnail_img' => null]);
    StoreProductBarcode::query()->create(['product_id' => $this->existing->id, 'barcode' => '8710000000001']);
});

/** @return array<string, list<mixed>> */
function productCreateSnapshot(): array
{
    return collect(['products', 'product_stocks', 'product_translations', 'product_taxes', 'product_variations', 'product_barcodes', 'wms_product_barcodes', 'flash_deal_products'])
        ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->orderBy('id')->get()->toArray()])->all();
}

/** @return array<string, mixed> */
function productCreateForm(array $overrides = []): array
{
    return [
        'name' => 'Extra virgin olive oil',
        'category_id' => test()->child->id,
        'brand_id' => test()->brand,
        'unit' => 'bottle',
        'weight' => '1.25',
        'max_qty' => '24',
        'min_qty' => '2',
        'tags' => ' oil , olive,oil,, ',
        'product_types' => ['Cold pressed', '', 'Organic'],
        'product_barcodes' => ['8712345678906', null],
        'photos' => null,
        'thumbnail_img' => null,
        'video_provider' => 'youtube',
        'video_link' => 'https://www.youtube.com/watch?v=abc123',
        'pdf' => null,
        'unit_price' => '12.50',
        'date_range' => '01-11-2026 08:00:00 to 30-11-2026 20:30:00',
        'discount' => '1.50',
        'discount_type' => 'amount',
        'current_stock' => '40',
        'sku' => 'EVOO-1',
        'external_link' => 'https://example.com/oil',
        'external_link_btn' => 'Buy elsewhere',
        'description' => '<p><strong>Fresh</strong> oil</p>',
        'meta_title' => null,
        'meta_description' => null,
        'meta_img' => null,
        'shipping_type' => 'flat_rate',
        'flat_shipping_cost' => '4.95',
        'is_quantity_multiplied' => '1',
        'est_shipping_days' => '3',
        'low_stock_quantity' => '5',
        'stock_visibility_state' => 'text',
        'cash_on_delivery' => '1',
        'featured' => '1',
        'flash_deal_id' => null,
        'flash_discount' => '0',
        'flash_discount_type' => null,
        'tax_id' => [test()->vat, test()->service],
        'tax' => ['21', '0.50'],
        'tax_type' => ['percent', 'amount'],
        ...$overrides,
    ];
}

/** The variant part of a form choosing the colours and the given attribute values. */
function productVariantForm(array $colors, array $choices, array $rows): array
{
    return [
        'colors_active' => $colors === [] ? null : '1',
        'colors' => $colors,
        'choice_options' => array_map(fn (array $choice) => ['attribute_id' => $choice[0], 'values' => $choice[1]], $choices),
        'variants' => $rows,
    ];
}

function productImage(?int $userId = null, string $type = 'image'): Upload
{
    return Upload::factory()->create(['user_id' => $userId ?? createStoreUser('customer')->id, 'type' => $type, 'file_original_name' => 'File '.Str::random(4), 'file_size' => 64, 'external_link' => 'https://example.com/'.Str::random(6).'.png']);
}

/**
 * The variant names, prices and quantities of the legacy Blade SKU table, in
 * table order.
 *
 * @return list<array{variant: string, price: string, sku: string, qty: string}>
 */
function legacySkuRows(string $html): array
{
    preg_match_all('/<tr class="variant">.*?<label[^>]*>(.*?)<\/label>.*?name="price_[^"]*" value="([^"]*)".*?name="sku_[^"]*" value="([^"]*)".*?name="qty_[^"]*" value="([^"]*)"/s', $html, $matches, PREG_SET_ORDER);

    return array_map(fn (array $row) => [
        'variant' => html_entity_decode(trim($row[1]), ENT_QUOTES),
        'price' => html_entity_decode($row[2], ENT_QUOTES),
        'sku' => $row[3],
        'qty' => $row[4],
    ], $matches);
}

test('the create form and its writes moved to the Inertia controller with their names, URIs and gate', function () {
    expect(route('products.create', [], false))->toBe('/temp/admin/products/create')
        ->and(route('products.store', [], false))->toBe('/temp/admin/products/store')
        ->and(route('products.variant_combinations', [], false))->toBe('/temp/admin/products/variant-combinations')
        ->and(Route::getRoutes()->getByName('products.store')->methods())->toBe(['POST'])
        ->and(Route::getRoutes()->getByName('products.variant_combinations')->methods())->toBe(['POST']);
    foreach (['create' => 'create', 'store' => 'store', 'variant_combinations' => 'variantCombinations'] as $name => $action) {
        expect(Route::getRoutes()->getByName('products.'.$name)->getActionName())->toBe(ProductController::class.'@'.$action);
    }
    expect(collect(ProductController::middleware())->map(fn ($entry) => [$entry->middleware, $entry->only]))
        ->toContain(['permission:add_new_product', ['create', 'store', 'variantCombinations']])
        ->and(method_exists(LegacyProductController::class, 'create'))->toBeFalse()
        ->and(method_exists(LegacyProductController::class, 'store'))->toBeFalse()
        ->and(view()->exists('shop.backend.product.products.create'))->toBeFalse()
        // The edit form, the seller panel and the seller SKU helpers keep these.
        ->and(view()->exists('shop.backend.product.products.edit'))->toBeTrue()
        ->and(view()->exists('shop.backend.product.products.choice_options_script'))->toBeTrue()
        ->and(view()->exists('shop.backend.product.products.sku_combinations'))->toBeTrue()
        ->and(view()->exists('shop.backend.product.products.sku_combinations_edit'))->toBeTrue();
});

test('the create form renders Inertia with every option the Blade form listed', function () {
    actingAsStoreUser(createStoreStaff('add_new_product'));
    $this->withSession(['locale' => 'ar'])->get(route('products.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Product/Products/Create')
        ->where('categories', [
            ['id' => $this->root->id, 'name' => 'Pantry'],
            ['id' => $this->child->id, 'name' => '-- زيوت'],
        ])
        ->where('brands', [['id' => $this->brand, 'name' => 'أوليفيا'], ['id' => $this->otherBrand, 'name' => 'Zest']])
        ->where('colors', [['id' => $this->blue, 'name' => 'Light Blue', 'code' => '#ADD8E6'], ['id' => $this->red, 'name' => 'Red', 'code' => '#FF0000']])
        ->where('attributes', [['id' => $this->size, 'name' => 'المقاس'], ['id' => $this->material, 'name' => 'Bottle']])
        ->where('flashDeals', [['id' => $this->flashDeal, 'name' => 'Autumn deals']])
        ->where('taxes', [
            ['id' => $this->vat, 'name' => 'VAT', 'tax' => '0', 'tax_type' => 'amount'],
            ['id' => $this->service, 'name' => 'Service', 'tax' => '0', 'tax_type' => 'amount'],
        ])
        ->where('sections', ['shipping' => true, 'productWiseShipping' => true, 'flashDeals' => true, 'vat' => true, 'cashOnDelivery' => true]));
    $this->get(route('products.create'), inertiaHeaders())->assertOk()->assertHeader('X-Inertia', 'true');
});

test('the create form leaves out the sections their business settings switch off', function () {
    actingAsStoreUser(createStoreStaff('add_new_product'));
    DB::table('business_settings')->whereIn('type', ['Flash_deals', 'VAT_TAX_section', 'cash_payment'])->update(['value' => '0']);
    DB::table('business_settings')->where('type', 'shipping_type')->update(['value' => 'flat_rate']);
    $this->get(route('products.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('flashDeals', [])->where('taxes', [])
        ->where('sections', ['shipping' => true, 'productWiseShipping' => false, 'flashDeals' => false, 'vat' => false, 'cashOnDelivery' => false]));
});

test('creating a product writes every table together, clears the caches after commit and returns to the in-house list', function () {
    $staff = createStoreStaff('add_new_product');
    actingAsStoreUser($staff);
    $photo = productImage($staff->id);
    $thumb = productImage($staff->id);
    $pdf = productImage($staff->id, 'document');
    $level = DB::transactionLevel();
    $committed = [];
    Artisan::shouldReceive('call')->with('view:clear')->once()->andReturnUsing(function () use (&$committed, $level) {
        $committed[] = DB::transactionLevel() === $level && Product::query()->where('name', 'Extra virgin olive oil')->exists();

        return 0;
    });
    Artisan::shouldReceive('call')->with('cache:clear')->once()->andReturn(0);

    $this->post(route('products.store'), productCreateForm(['photos' => $photo->id.','.$thumb->id, 'thumbnail_img' => $thumb->id, 'pdf' => $pdf->id, 'digital' => 1, 'added_by' => 'seller', 'user_id' => $staff->id, 'auction_product' => 1, 'wholesale_product' => 1, 'approved' => 0, 'num_of_sale' => 99]))
        ->assertSessionHasNoErrors()->assertRedirect(route('products.admin'));

    expect($committed)->toBe([true]);
    $product = Product::query()->where('name', 'Extra virgin olive oil')->sole();
    expect(collect(DB::table('products')->where('id', $product->id)->first())->only([
        'name', 'added_by', 'user_id', 'category_id', 'brand_id', 'unit', 'weight', 'max_qty', 'min_qty', 'tags', 'photos', 'thumbnail_img',
        'video_provider', 'video_link', 'pdf', 'unit_price', 'discount', 'discount_type', 'discount_start_date', 'discount_end_date', 'current_stock',
        'external_link', 'external_link_btn', 'description', 'meta_title', 'meta_description', 'meta_img', 'shipping_type', 'shipping_cost',
        'is_quantity_multiplied', 'est_shipping_days', 'low_stock_quantity', 'stock_visibility_state', 'cash_on_delivery', 'featured', 'todays_deal',
        'slug', 'colors', 'choice_options', 'attributes', 'published', 'approved', 'digital', 'auction_product', 'wholesale_product', 'variant_product', 'num_of_sale',
    ])->all())->toEqual([
        'name' => 'Extra virgin olive oil', 'added_by' => 'admin', 'user_id' => $this->owner->id, 'category_id' => $this->child->id, 'brand_id' => $this->brand,
        'unit' => 'bottle', 'weight' => 1.25, 'max_qty' => 24, 'min_qty' => 2, 'tags' => 'oil,olive', 'photos' => $photo->id.','.$thumb->id,
        'thumbnail_img' => (string) $thumb->id, 'video_provider' => 'youtube', 'video_link' => 'https://www.youtube.com/watch?v=abc123', 'pdf' => (string) $pdf->id,
        'unit_price' => 12.5, 'discount' => 1.5, 'discount_type' => 'amount', 'discount_start_date' => productUtcTimestamp('2026-11-01 08:00:00'),
        'discount_end_date' => productUtcTimestamp('2026-11-30 20:30:00'), 'current_stock' => 40, 'external_link' => 'https://example.com/oil',
        'external_link_btn' => 'Buy elsewhere', 'description' => '<p><strong>Fresh</strong> oil</p>', 'meta_title' => 'Extra virgin olive oil',
        'meta_description' => 'Fresh oil', 'meta_img' => (string) $thumb->id, 'shipping_type' => 'flat_rate', 'shipping_cost' => 4.95,
        'is_quantity_multiplied' => 1, 'est_shipping_days' => 3, 'low_stock_quantity' => 5, 'stock_visibility_state' => 'text', 'cash_on_delivery' => 1,
        'featured' => 1, 'todays_deal' => 0, 'slug' => 'extra-virgin-olive-oil', 'colors' => '[]', 'choice_options' => '[]', 'attributes' => '[]',
        'published' => 1, 'approved' => 1, 'digital' => 0, 'auction_product' => 0, 'wholesale_product' => 0, 'variant_product' => 0, 'num_of_sale' => 0,
    ]);
    expect(DB::table('product_stocks')->where('product_id', $product->id)->get(['variant', 'price', 'sku', 'qty', 'image'])->map(fn ($row) => (array) $row)->all())
        ->toEqual([['variant' => '', 'price' => 12.5, 'sku' => 'EVOO-1', 'qty' => 40, 'image' => null]])
        ->and(DB::table('product_translations')->where('product_id', $product->id)->get(['lang', 'name', 'unit', 'description'])->map(fn ($row) => (array) $row)->all())
        ->toBe([['lang' => 'en', 'name' => 'Extra virgin olive oil', 'unit' => 'bottle', 'description' => '<p><strong>Fresh</strong> oil</p>']])
        ->and(DB::table('product_taxes')->where('product_id', $product->id)->orderBy('id')->get(['tax_id', 'tax', 'tax_type'])->map(fn ($row) => (array) $row)->all())
        ->toEqual([['tax_id' => $this->vat, 'tax' => 21, 'tax_type' => 'percent'], ['tax_id' => $this->service, 'tax' => 0.5, 'tax_type' => 'amount']])
        ->and(DB::table('product_variations')->where('product_id', $product->id)->orderBy('id')->pluck('name')->all())->toBe(['Cold pressed', 'Organic'])
        ->and(DB::table('product_barcodes')->where('product_id', $product->id)->pluck('barcode')->all())->toBe(['8712345678906'])
        ->and(DB::table('wms_product_barcodes')->count())->toBe(0)
        ->and(DB::table('flash_deal_products')->count())->toBe(0);
});

test('a second product with the same name gets a numbered slug, and save and unpublish leaves it unpublished', function () {
    actingAsStoreUser(createStoreStaff('add_new_product'));
    Artisan::shouldReceive('call')->andReturn(0);
    $this->post(route('products.store'), productCreateForm(['product_barcodes' => []]))->assertSessionHasNoErrors();
    $this->post(route('products.store'), productCreateForm(['product_barcodes' => [], 'button' => 'unpublish', 'featured' => null]))->assertSessionHasNoErrors();
    expect(Product::query()->where('name', 'Extra virgin olive oil')->orderBy('id')->get(['slug', 'published', 'featured'])->map(fn ($row) => [$row->slug, $row->getAttribute('published'), $row->getAttribute('featured')])->all())
        ->toBe([['extra-virgin-olive-oil', true, 1], ['extra-virgin-olive-oil-2', false, 0]]);
});

test('a variant product writes one stock row per colour and value combination, with the variant JSON', function () {
    $staff = createStoreStaff('add_new_product');
    actingAsStoreUser($staff);
    Artisan::shouldReceive('call')->andReturn(0);
    $image = productImage($staff->id);
    $rows = [
        ['variant' => 'LightBlue-S', 'price' => '13.00', 'sku' => 'LB-S', 'qty' => '4', 'image' => $image->id],
        ['variant' => 'LightBlue-ExtraLarge', 'price' => '15.00', 'sku' => null, 'qty' => '0', 'image' => null],
        ['variant' => 'Red-S', 'price' => '13.50', 'sku' => 'R-S', 'qty' => '10', 'image' => null],
        ['variant' => 'Red-ExtraLarge', 'price' => '15.50', 'sku' => null, 'qty' => '2', 'image' => null],
    ];
    // ProductUtility names a colour by its name: "Light Blue" would keep its
    // space; the generated names are what the server sends back.
    $names = $this->postJson(route('products.variant_combinations'), ['colors_active' => true, 'colors' => ['#ADD8E6', '#FF0000'], 'choice_options' => [['attribute_id' => $this->size, 'values' => ['S', 'Extra Large']]], 'unit_price' => '12.50'])
        ->assertOk()->json('*.variant');
    $rows = array_map(fn (array $row, string $name) => [...$row, 'variant' => $name], $rows, $names);

    $this->post(route('products.store'), productCreateForm([...productVariantForm(['#ADD8E6', '#FF0000'], [[$this->size, ['S', 'Extra Large']]], $rows), 'current_stock' => '0', 'sku' => null]))
        ->assertSessionHasNoErrors()->assertRedirect(route('products.admin'));

    $product = Product::query()->where('name', 'Extra virgin olive oil')->sole();
    expect($names)->toBe(['Light Blue-S', 'Light Blue-ExtraLarge', 'Red-S', 'Red-ExtraLarge'])
        ->and(collect(DB::table('products')->where('id', $product->id)->first())->only(['variant_product', 'colors', 'choice_options', 'attributes'])->all())->toEqual([
            'variant_product' => 1,
            'colors' => '["#ADD8E6","#FF0000"]',
            'choice_options' => '[{"attribute_id":"'.$this->size.'","values":["S","Extra Large"]}]',
            'attributes' => '["'.$this->size.'"]',
        ])
        ->and(DB::table('product_stocks')->where('product_id', $product->id)->orderBy('id')->get(['variant', 'price', 'sku', 'qty', 'image'])->map(fn ($row) => (array) $row)->all())->toEqual([
            ['variant' => 'Light Blue-S', 'price' => 13, 'sku' => 'LB-S', 'qty' => 4, 'image' => $image->id],
            ['variant' => 'Light Blue-ExtraLarge', 'price' => 15, 'sku' => null, 'qty' => 0, 'image' => null],
            ['variant' => 'Red-S', 'price' => 13.5, 'sku' => 'R-S', 'qty' => 10, 'image' => null],
            ['variant' => 'Red-ExtraLarge', 'price' => 15.5, 'sku' => null, 'qty' => 2, 'image' => null],
        ]);
});

test('the variant rows match the legacy Blade SKU table in order, names and default price', function (array $colors, array $choices) {
    actingAsStoreUser(createStoreStaff('add_new_product'));
    $legacy = [
        'name' => 'Olive oil', 'unit_price' => '7.25', 'colors' => $colors,
        ...($colors === [] ? [] : ['colors_active' => '1']),
    ];
    foreach ($choices as [$attribute, $values]) {
        $id = $attribute === 'size' ? $this->size : $this->material;
        $legacy['choice_no'][] = (string) $id;
        $legacy['choice_options_'.$id] = $values;
    }
    $blade = legacySkuRows($this->post(route('products.sku_combination'), $legacy)->assertOk()->getContent());

    $json = $this->postJson(route('products.variant_combinations'), [
        'colors_active' => $colors !== [], 'colors' => $colors, 'unit_price' => '7.25',
        'choice_options' => array_map(fn (array $choice) => ['attribute_id' => $choice[0] === 'size' ? $this->size : $this->material, 'values' => $choice[1]], $choices),
    ])->assertOk()->json();

    expect($blade)->not->toBeEmpty()->and($json)->toBe(array_map(fn (array $row) => [...$row, 'qty' => '10'], $blade))
        ->and(array_column($json, 'sku'))->each->toBe('');
})->with([
    'one attribute' => [[], [['size', ['S', 'M']]]],
    'values with spaces and dots' => [[], [['size', ['Extra Large', 'S']], ['material', ['1.5 L', 'Glass']]]],
    'colours only' => [['#FF0000', '#ADD8E6'], []],
    'colours and two attributes' => [['#ADD8E6', '#FF0000'], [['material', ['Glass', '1.5 L']], ['size', ['M']]]],
]);

test('the variant endpoint answers no rows without a choice and refuses unknown values', function () {
    actingAsStoreUser(createStoreStaff('add_new_product'));
    $this->postJson(route('products.variant_combinations'), ['unit_price' => '1'])->assertOk()->assertExactJson([]);
    $this->postJson(route('products.variant_combinations'), ['colors_active' => false, 'colors' => ['#FF0000']])->assertOk()->assertExactJson([]);
    $this->postJson(route('products.variant_combinations'), ['choice_options' => [['attribute_id' => $this->size, 'values' => ['Glass']]]])->assertUnprocessable()->assertJsonValidationErrors('choice_options.0.values.0');
    $this->postJson(route('products.variant_combinations'), ['choice_options' => [['attribute_id' => $this->size, 'values' => []]]])->assertUnprocessable()->assertJsonValidationErrors('choice_options.0.values');
    $this->postJson(route('products.variant_combinations'), ['colors_active' => true, 'colors' => ['#123456']])->assertUnprocessable()->assertJsonValidationErrors('colors.0');
});

test('the store refuses variant rows that do not match the choices', function (Closure $variants, string $field) {
    actingAsStoreUser(createStoreStaff('add_new_product'));
    $before = productCreateSnapshot();
    $this->post(route('products.store'), productCreateForm($variants()))->assertSessionHasErrors($field);
    expect(productCreateSnapshot())->toEqual($before);
})->with([
    'rows missing' => [fn () => productVariantForm([], [[test()->size, ['S', 'M']]], []), 'variants'],
    'one row short' => [fn () => productVariantForm([], [[test()->size, ['S', 'M']]], [['variant' => 'S', 'price' => '1', 'qty' => '1']]), 'variants'],
    'renamed row' => [fn () => productVariantForm([], [[test()->size, ['S', 'M']]], [['variant' => 'S', 'price' => '1', 'qty' => '1'], ['variant' => 'L', 'price' => '1', 'qty' => '1']]), 'variants.1.variant'],
    'rows without a choice' => [fn () => productVariantForm([], [], [['variant' => 'S', 'price' => '1', 'qty' => '1']]), 'variants'],
    'negative variant price' => [fn () => productVariantForm([], [[test()->size, ['S']]], [['variant' => 'S', 'price' => '-1', 'qty' => '1']]), 'variants.0.price'],
    'variant quantity not an integer' => [fn () => productVariantForm([], [[test()->size, ['S']]], [['variant' => 'S', 'price' => '1', 'qty' => '1.5']]), 'variants.0.qty'],
    'variant SKU over 255' => [fn () => productVariantForm([], [[test()->size, ['S']]], [['variant' => 'S', 'price' => '1', 'qty' => '1', 'sku' => str_repeat('a', 256)]]), 'variants.0.sku'],
    'unknown variant image' => [fn () => productVariantForm([], [[test()->size, ['S']]], [['variant' => 'S', 'price' => '1', 'qty' => '1', 'image' => 999999]]), 'variants.0.image'],
    'value of another attribute' => [fn () => productVariantForm([], [[test()->size, ['Glass']]], [['variant' => 'Glass', 'price' => '1', 'qty' => '1']]), 'choice_options.0.values.0'],
    'repeated attribute' => [fn () => productVariantForm([], [[test()->size, ['S']], [test()->size, ['M']]], []), 'choice_options.1.attribute_id'],
    'unknown colour' => [fn () => productVariantForm(['#000001'], [], []), 'colors.0'],
]);

test('the store refuses more variants than the bound', function () {
    actingAsStoreUser(createStoreStaff('add_new_product'));
    $values = [];
    foreach (range(1, 23) as $i) {
        $values[] = 'V'.$i;
        DB::table('attribute_values')->insert(['attribute_id' => $this->material, 'value' => 'V'.$i]);
        DB::table('attribute_values')->insert(['attribute_id' => $this->size, 'value' => 'V'.$i]);
    }
    $this->postJson(route('products.variant_combinations'), ['choice_options' => [['attribute_id' => $this->size, 'values' => $values], ['attribute_id' => $this->material, 'values' => $values]]])
        ->assertUnprocessable()->assertJsonValidationErrors('choice_options')->assertJsonMissingValidationErrors('choice_options.0.values');
    // 22 x 22 = 484 rows stay within the bound, and two attributes may share values.
    $this->postJson(route('products.variant_combinations'), ['choice_options' => [['attribute_id' => $this->size, 'values' => array_slice($values, 0, 22)], ['attribute_id' => $this->material, 'values' => array_slice($values, 0, 22)]]])
        ->assertOk()->assertJsonCount(484);
    $this->postJson(route('products.variant_combinations'), ['choice_options' => [['attribute_id' => $this->size, 'values' => ['S', 'S']]]])
        ->assertUnprocessable()->assertJsonValidationErrors('choice_options.0.values');
});

test('a flash deal entry takes the flash discount and the deal period, as ProductFlashDealService writes it', function () {
    actingAsStoreUser(createStoreStaff('add_new_product'));
    Artisan::shouldReceive('call')->andReturn(0);
    $this->post(route('products.store'), productCreateForm(['flash_deal_id' => $this->flashDeal, 'flash_discount' => '10', 'flash_discount_type' => 'percent']))->assertSessionHasNoErrors();
    $product = Product::query()->where('name', 'Extra virgin olive oil')->sole();
    expect(DB::table('flash_deal_products')->get(['flash_deal_id', 'product_id'])->map(fn ($row) => (array) $row)->all())->toBe([['flash_deal_id' => $this->flashDeal, 'product_id' => $product->id]])
        ->and(collect(DB::table('products')->where('id', $product->id)->first())->only(['discount', 'discount_type', 'discount_start_date', 'discount_end_date'])->all())
        ->toEqual(['discount' => 10, 'discount_type' => 'percent', 'discount_start_date' => productUtcTimestamp('2026-11-01 00:00:00'), 'discount_end_date' => productUtcTimestamp('2026-11-07 23:59:59')]);
    $this->post(route('products.store'), productCreateForm(['name' => 'Inactive deal', 'flash_deal_id' => $this->pastDeal, 'flash_discount_type' => 'amount']))->assertSessionHasErrors('flash_deal_id');
    $this->post(route('products.store'), productCreateForm(['name' => 'No type', 'flash_deal_id' => $this->flashDeal]))->assertSessionHasErrors('flash_discount_type');
});

test('fields whose settings are off are not written, as the form did not post them', function () {
    actingAsStoreUser(createStoreStaff('add_new_product'));
    Artisan::shouldReceive('call')->andReturn(0);
    DB::table('business_settings')->whereIn('type', ['Setup_Shipping_section', 'Flash_deals', 'VAT_TAX_section', 'cash_payment'])->update(['value' => '0']);
    $this->post(route('products.store'), productCreateForm(['flash_deal_id' => $this->flashDeal, 'flash_discount_type' => 'percent', 'flash_discount' => '50', 'cash_on_delivery' => null, 'shipping_type' => 'nonsense']))
        ->assertSessionHasNoErrors();
    $product = Product::query()->where('name', 'Extra virgin olive oil')->sole();
    expect(collect(DB::table('products')->where('id', $product->id)->first())->only(['shipping_type', 'shipping_cost', 'is_quantity_multiplied', 'est_shipping_days', 'discount', 'cash_on_delivery'])->all())
        ->toEqual(['shipping_type' => 'flat_rate', 'shipping_cost' => 0, 'is_quantity_multiplied' => 0, 'est_shipping_days' => null, 'discount' => 1.5, 'cash_on_delivery' => 1])
        ->and(DB::table('product_taxes')->where('product_id', $product->id)->count())->toBe(0)
        ->and(DB::table('flash_deal_products')->count())->toBe(0);
});

test('the cash-on-delivery switch is honoured where it shows, and free shipping costs nothing', function () {
    actingAsStoreUser(createStoreStaff('add_new_product'));
    Artisan::shouldReceive('call')->andReturn(0);
    $this->post(route('products.store'), productCreateForm(['cash_on_delivery' => null, 'shipping_type' => 'free', 'flat_shipping_cost' => '9']))->assertSessionHasNoErrors();
    expect(collect(DB::table('products')->where('name', 'Extra virgin olive oil')->first())->only(['cash_on_delivery', 'shipping_type', 'shipping_cost'])->all())
        ->toEqual(['cash_on_delivery' => 0, 'shipping_type' => 'free', 'shipping_cost' => 0]);
});

test('a failed write rolls every table back and clears no cache', function (string $failing) {
    $model = ['translation' => ProductTranslation::class, 'store barcode' => StoreProductBarcode::class][$failing];
    actingAsStoreUser(createStoreStaff('add_new_product'));
    $before = productCreateSnapshot();
    Artisan::shouldReceive('call')->never();
    $model::saving(fn () => throw new RuntimeException('Persistence failure'));
    $this->withoutExceptionHandling();
    try {
        expect(fn () => $this->post(route('products.store'), productCreateForm([...productVariantForm([], [[test()->size, ['S']]], [['variant' => 'S', 'price' => '1', 'qty' => '1']]), 'flash_deal_id' => $this->flashDeal, 'flash_discount_type' => 'amount'])))
            ->toThrow(RuntimeException::class);
        expect(productCreateSnapshot())->toEqual($before);
    } finally {
        $model::flushEventListeners();
    }
})->with(['translation', 'store barcode']);

test('the store validates every field against the schema widths and the form options', function (array|Closure $overrides, string $field) {
    actingAsStoreUser(createStoreStaff('add_new_product'));
    $before = productCreateSnapshot();
    $this->post(route('products.store'), productCreateForm($overrides instanceof Closure ? $overrides() : $overrides))->assertSessionHasErrors($field);
    expect(productCreateSnapshot())->toEqual($before);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'name over 200' => [['name' => str_repeat('a', 201)], 'name'],
    'missing category' => [['category_id' => null], 'category_id'],
    'digital category' => [fn () => ['category_id' => test()->digitalRoot->id], 'category_id'],
    'unknown brand' => [['brand_id' => 999999], 'brand_id'],
    'missing unit' => [['unit' => ''], 'unit'],
    'unit over 20' => [['unit' => str_repeat('u', 21)], 'unit'],
    'negative weight' => [['weight' => '-1'], 'weight'],
    'weight over DECIMAL(8,2)' => [['weight' => '1000000'], 'weight'],
    'minimum quantity zero' => [['min_qty' => '0'], 'min_qty'],
    'maximum quantity over INT' => [['max_qty' => '2147483648'], 'max_qty'],
    'tags over 500' => [['tags' => str_repeat('t', 501)], 'tags'],
    'type over 255' => [['product_types' => [str_repeat('t', 256)]], 'product_types.0'],
    'too many types' => [['product_types' => array_fill(0, 51, 'x')], 'product_types'],
    'photos not ids' => [['photos' => '1,a'], 'photos'],
    'photos over 2000' => [['photos' => str_repeat('1,', 1000).'1'], 'photos'],
    'unknown thumbnail' => [['thumbnail_img' => 999999], 'thumbnail_img'],
    'unknown video provider' => [['video_provider' => 'tiktok'], 'video_provider'],
    'video link over 100' => [['video_link' => 'https://example.com/'.str_repeat('v', 90)], 'video_link'],
    'video link not http' => [['video_link' => 'ftp://example.com/video'], 'video_link'],
    'unknown PDF' => [['pdf' => 999999], 'pdf'],
    'missing unit price' => [['unit_price' => ''], 'unit_price'],
    'unit price over DECIMAL(20,2)' => [['unit_price' => '1000000000000000000'], 'unit_price'],
    'unit price with three decimals' => [['unit_price' => '1.005'], 'unit_price'],
    'flat discount not below the price' => [['discount' => '12.50'], 'discount'],
    'percentage of 100' => [['discount' => '100', 'discount_type' => 'percent'], 'discount'],
    'unknown discount type' => [['discount_type' => 'fixed'], 'discount_type'],
    'malformed date range' => [['date_range' => '2026-11-01 to 2026-11-30'], 'date_range'],
    'range ending before it starts' => [['date_range' => '30-11-2026 08:00:00 to 01-11-2026 08:00:00'], 'date_range'],
    'negative quantity' => [['current_stock' => '-1'], 'current_stock'],
    'SKU over 255' => [['sku' => str_repeat('s', 256)], 'sku'],
    'external link over 500' => [['external_link' => 'https://example.com/'.str_repeat('x', 490)], 'external_link'],
    'external link not http' => [['external_link' => 'ftp://example.com/oil'], 'external_link'],
    'button text over 255' => [['external_link_btn' => str_repeat('b', 256)], 'external_link_btn'],
    'unknown meta image' => [['meta_img' => 999999], 'meta_img'],
    'unknown shipping type' => [['shipping_type' => 'pigeon'], 'shipping_type'],
    'flat rate without a cost' => [['flat_shipping_cost' => null], 'flat_shipping_cost'],
    'shipping days zero' => [['est_shipping_days' => '0'], 'est_shipping_days'],
    'negative low stock' => [['low_stock_quantity' => '-1'], 'low_stock_quantity'],
    'unknown stock visibility' => [['stock_visibility_state' => 'blink'], 'stock_visibility_state'],
    'featured not boolean' => [['featured' => 'maybe'], 'featured'],
    'unknown flash deal' => [['flash_deal_id' => 999999, 'flash_discount_type' => 'amount'], 'flash_deal_id'],
    'inactive tax' => [fn () => ['tax_id' => [test()->oldTax], 'tax' => ['1'], 'tax_type' => ['amount']], 'tax_id.0'],
    'tax amounts not matching' => [fn () => ['tax_id' => [test()->vat, test()->service], 'tax' => ['1'], 'tax_type' => ['amount', 'amount']], 'tax'],
    'unknown tax type' => [['tax_type' => ['percent', 'fixed']], 'tax_type.1'],
    'unknown button' => [['button' => 'draft'], 'button'],
]);

test('store barcodes must be new and distinct, empty rows are skipped and the WMS barcodes are untouched', function () {
    actingAsStoreUser(createStoreStaff('add_new_product'));
    Artisan::shouldReceive('call')->andReturn(0);
    $before = productCreateSnapshot();
    $this->post(route('products.store'), productCreateForm(['product_barcodes' => ['8710000000001']]))->assertSessionHasErrors('product_barcodes.0');
    $this->post(route('products.store'), productCreateForm(['product_barcodes' => ['123', '123']]))->assertSessionHasErrors('product_barcodes.1');
    $this->post(route('products.store'), productCreateForm(['product_barcodes' => [str_repeat('9', 256)]]))->assertSessionHasErrors('product_barcodes.0');
    expect(productCreateSnapshot())->toEqual($before);
    $this->post(route('products.store'), productCreateForm(['product_barcodes' => ['', ' 4006381333931 ', null]]))->assertSessionHasNoErrors();
    expect(DB::table('product_barcodes')->orderBy('id')->pluck('barcode')->all())->toBe(['8710000000001', '4006381333931'])
        ->and(DB::table('wms_product_barcodes')->count())->toBe(0);
});

test('uploads follow the ownership, library and file type rules', function () {
    $staff = createStoreStaff('add_new_product');
    actingAsStoreUser($staff);
    Artisan::shouldReceive('call')->andReturn(0);
    $foreign = productImage();
    $own = productImage($staff->id);
    $ownPdf = productImage($staff->id, 'document');
    $before = productCreateSnapshot();
    foreach ([
        ['photos' => (string) $foreign->id], ['thumbnail_img' => $foreign->id], ['meta_img' => $foreign->id], ['pdf' => $ownPdf->id, 'thumbnail_img' => $ownPdf->id],
        ['pdf' => $own->id],
    ] as $overrides) {
        $this->post(route('products.store'), productCreateForm($overrides))->assertSessionHasErrors(array_key_last($overrides));
    }
    expect(productCreateSnapshot())->toEqual($before);
    $this->post(route('products.store'), productCreateForm(['name' => 'Own files', 'product_barcodes' => [], 'photos' => (string) $own->id, 'thumbnail_img' => $own->id, 'pdf' => $ownPdf->id]))->assertSessionHasNoErrors();

    actingAsStoreUser(createStoreStaff('add_new_product', 'view_uploads'));
    $this->post(route('products.store'), productCreateForm(['name' => 'Library files', 'product_barcodes' => [], 'photos' => (string) $foreign->id, 'thumbnail_img' => $foreign->id, 'meta_img' => $foreign->id]))->assertSessionHasNoErrors();
    expect(Product::query()->whereIn('name', ['Own files', 'Library files'])->count())->toBe(2);
});

test('the WAF still refuses a script payload in the description before anything is written', function () {
    actingAsStoreUser(createStoreStaff('add_new_product'));
    $before = productCreateSnapshot();
    $this->post(route('products.store'), productCreateForm(['description' => '<p>x</p><script>alert(1)</script>']))->assertForbidden();
    $this->post(route('products.store'), productCreateForm(['description' => '<img src="x" onerror="alert(1)">']))->assertForbidden();
    $this->post(route('products.store'), productCreateForm(['external_link' => 'javascript:alert(1)']))->assertForbidden();
    expect(productCreateSnapshot())->toEqual($before);
});

test('the description is cleaned before it is validated and stored', function (string $hostile, string $removed) {
    $this->withoutMiddleware(BlockMaliciousRequests::class);
    actingAsStoreUser(createStoreStaff('add_new_product'));
    Artisan::shouldReceive('call')->andReturn(0);
    $this->post(route('products.store'), productCreateForm(['description' => '<p>Kept</p>'.$hostile]))->assertSessionHasNoErrors();
    $product = Product::query()->where('name', 'Extra virgin olive oil')->sole();
    foreach ([$product->getAttribute('description'), DB::table('product_translations')->where('product_id', $product->id)->value('description')] as $stored) {
        expect($stored)->toStartWith('<p>Kept</p>')->not->toContain($removed)->and(clean_html($stored))->toBe($stored);
    }
    expect($product->getAttribute('meta_description'))->not->toContain($removed);
})->with([
    'script element' => ['<script>alert(1)</script>', 'alert'],
    'image error handler' => ['<img src="x" onerror="alert(1)">', 'onerror'],
    'click handler' => ['<p onclick="alert(1)">Click</p>', 'onclick'],
    'javascript link' => ['<a href="javascript:alert(1)">Link</a>', 'javascript'],
    'iframe' => ['<iframe src="https://example.com"></iframe>', 'iframe'],
]);

test('legacy editor markup is stored unchanged and renders unchanged on the storefront', function () {
    actingAsStoreUser(createStoreStaff('add_new_product'));
    Artisan::shouldReceive('call')->andReturn(0);
    $legacy = clean_html('<p style="text-align: center;"><b>Bold</b> <i>italic</i> <u>under</u></p><ul><li>One</li></ul><table class="table table-bordered"><tbody><tr><td>Cell</td></tr></tbody></table><p><a href="https://example.com" target="_blank">Shop</a></p>');
    $this->post(route('products.store'), productCreateForm(['description' => $legacy]))->assertSessionHasNoErrors();
    $product = Product::query()->where('name', 'Extra virgin olive oil')->sole();
    expect($product->getAttribute('description'))->toBe($legacy)->and(clean_html($legacy))->toBe($legacy)
        ->and($legacy)->toContain('<b>Bold</b>', '<li>One</li>', 'text-align: center', '<td>Cell</td>');
});

test('every create route refuses unauthorized callers and writes nothing', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    $before = productCreateSnapshot();
    assertStoreCallerRefused($this->get(route('products.create')), $caller);
    assertStoreCallerRefused($this->post(route('products.store'), productCreateForm()), $caller);
    assertStoreCallerRefused($this->post(route('products.variant_combinations'), ['choice_options' => [['attribute_id' => $this->size, 'values' => ['S']]]]), $caller);
    expect(productCreateSnapshot())->toEqual($before);
})->with('refused store admin callers');

test('only add_new_product opens the create form', function () {
    $before = productCreateSnapshot();
    actingAsStoreUser(createStoreStaff('product_edit', 'show_in_house_products', 'add_digital_product'));
    $this->get(route('products.create'))->assertForbidden();
    $this->post(route('products.store'), productCreateForm())->assertForbidden();
    $this->postJson(route('products.variant_combinations'), [])->assertForbidden();
    expect(productCreateSnapshot())->toEqual($before);
});

test('WMS authentication opens no product form and writes nothing', function () {
    $this->actingAs(User::factory()->create(), 'web');
    $before = productCreateSnapshot();
    $this->get(route('products.create'))->assertRedirect(route('shop.login'));
    $this->post(route('products.store'), productCreateForm())->assertRedirect(route('shop.login'));
    $this->post(route('products.variant_combinations'), [])->assertRedirect(route('shop.login'));
    expect(productCreateSnapshot())->toEqual($before);
});

test('the created product appears in the in-house list and on its storefront page', function () {
    actingAsStoreUser(createStoreStaff('add_new_product', 'show_in_house_products'));
    Artisan::shouldReceive('call')->andReturn(0);
    $this->post(route('products.store'), productCreateForm(['featured' => null, 'product_barcodes' => []]))->assertSessionHasNoErrors();
    $product = Product::query()->where('name', 'Extra virgin olive oil')->sole();
    $this->get(route('products.admin', ['active' => 1, 'for_sale' => 1]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('products.data.0.id', $product->id)->where('products.data.0.name', 'Extra virgin olive oil'));
    $this->get(route('product', $product->slug))->assertOk()->assertSee('Extra virgin olive oil')->assertSee('<strong>Fresh</strong> oil', false);
});

test('the seller panel product form and the retained SKU helpers still render their Blade', function () {
    $seller = createStoreUser('seller');
    actingAsStoreUser($seller);
    // The seller form lazy-loads each tax's product taxes, which the strict
    // test mode refuses; production does not run strict mode (inherited).
    Model::preventLazyLoading(false);
    $this->get(route('seller.products.create'))->assertOk()->assertViewIs('shop.seller.product.products.create');
    $html = $this->post(route('seller.products.sku_combination'), ['name' => 'Oil', 'unit_price' => '3', 'choice_no' => [(string) $this->size], 'choice_options_'.$this->size => ['S', 'M']])
        ->assertOk()->assertViewIs('shop.backend.product.products.sku_combinations')->getContent();
    expect(array_column(legacySkuRows($html), 'variant'))->toBe(['S', 'M']);
    $this->post(route('seller.products.sku_combination_edit'), ['id' => $this->existing->id, 'name' => 'Oil', 'unit_price' => '3', 'choice_no' => [(string) $this->size], 'choice_options_'.$this->size => ['S']])
        ->assertOk()->assertViewIs('shop.backend.product.products.sku_combinations_edit');

    actingAsStoreUser(createStoreStaff('product_edit'));
    DB::table('products')->where('id', $this->existing->id)->update(['colors' => '[]', 'attributes' => '[]', 'tags' => '']);
    $this->get(route('products.admin.edit', ['id' => $this->existing->id, 'lang' => 'en']))->assertOk()->assertViewIs('shop.backend.product.products.edit')
        ->assertSee('function appendProductAttributeChoice', false);
    $this->post(route('products.sku_combination_edit'), ['id' => $this->existing->id, 'name' => 'Oil', 'unit_price' => '3', 'choice_no' => [(string) $this->size], 'choice_options_'.$this->size => ['S']])
        ->assertOk()->assertViewIs('shop.backend.product.products.sku_combinations_edit');
});

function productUtcTimestamp(string $dateTime): int
{
    return (new DateTimeImmutable($dateTime, new DateTimeZone('UTC')))->getTimestamp();
}
