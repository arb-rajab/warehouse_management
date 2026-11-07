<?php

use App\Models\Category;
use App\Models\Language;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\StoreProductBarcode;
use App\Services\ProductBarcodeLabels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * The fields the store back office product form saves (barcodes, weight,
 * minimum and maximum quantity, shipping and tags) reach the storefront
 * product page (`/temp/product/{slug}`). These tests go the whole way: the
 * form is posted to the back office create and edit endpoints, and the page is
 * read back, so a break in the writer, in the storefront query or in the
 * template shows up here whichever of the three it is.
 *
 * What the form shows depends on two business settings: the shipping fields
 * exist only while the "Shipping menu" feature (Setup_Shipping_section) is on,
 * and the free/flat-rate choice only while the shipping method is "Product
 * wise shipping cost". Without them nothing is posted, and the page can only
 * say "Free shipping".
 */
beforeEach(function () {
    seedStoreCurrency();
    config(['app.default_language' => 'en']);
    Language::forceCreate(['code' => 'en', 'name' => 'English', 'rtl' => 0]);
    Language::forceCreate(['code' => 'ar', 'name' => 'Arabic', 'rtl' => 1]);
    $this->category = Category::forceCreate(['name' => 'Pantry', 'parent_id' => 0, 'level' => 0, 'slug' => 'pantry', 'digital' => 0]);
    storeSetting('Setup_Shipping_section', 1);
    storeSetting('shipping_type', 'product_wise_shipping');
    // The back office clears the compiled views and the cache after a save; a
    // test must not empty the directory other parallel workers are reading.
    Artisan::shouldReceive('call')->andReturn(0);
    actingAsStoreUser(createStoreAdmin());
});

/**
 * The create form of a product with every field this file looks at filled in.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function backOfficeFieldsForm(array $overrides = []): array
{
    return [
        'name' => 'Extra virgin olive oil',
        'category_id' => test()->category->id,
        'unit' => 'bottle',
        'weight' => '1.25',
        'max_qty' => '24',
        'min_qty' => '3',
        'tags' => ' oil , olive,oil,, ',
        'product_barcodes' => ['8712345678906', null, '8712345678913'],
        'unit_price' => '12.50',
        'discount' => '0',
        'discount_type' => 'amount',
        'current_stock' => '40',
        'stock_visibility_state' => 'quantity',
        'shipping_type' => 'flat_rate',
        'flat_shipping_cost' => '4.95',
        'is_quantity_multiplied' => '1',
        'est_shipping_days' => '3',
        ...$overrides,
    ];
}

/** Saves the form on the back office create endpoint and returns the stored product. */
function backOfficeFieldsCreate(array $overrides = []): Product
{
    $form = backOfficeFieldsForm($overrides);
    test()->post(route('products.store'), $form)->assertSessionHasNoErrors();

    return Product::query()->where('name', $form['name'])->sole();
}

/** The storefront page of a product, read by a verified customer or by a guest. */
function backOfficeFieldsPage(Product $product, bool $guest = false): TestResponse
{
    if ($guest) {
        app('auth')->forgetGuards();
    } else {
        $customer = createStoreUser('customer');
        $customer->forceFill(['admin_verified' => true])->save();
        actingAsStoreUser($customer->fresh());
    }

    return test()->get('/temp/product/'.$product->slug)->assertOk();
}

/** The value cells beside a label in the page's product information rows. */
function backOfficeFieldsCells(TestResponse $page, string $label): DOMNodeList
{
    return htmlXPath($page)->query(
        "//div[contains(@class, 'text-secondary')][normalize-space() = \"".$label.'"]'
        ."/ancestor::div[contains(@class, 'col-sm-2')][1]/following-sibling::div[contains(@class, 'col-sm-10')][1]"
    );
}

/**
 * The visible "Estimate Shipping Time:" label, which the template prints only
 * with est_shipping_days. The bare words are also in an HTML comment that is
 * always rendered, so asserting on them alone proves nothing.
 */
function backOfficeFieldsEstimateLabel(): string
{
    return __('storefront.checkout.estimate_shipping_time').':';
}

/** The text beside a label, or null when the row is not printed. */
function backOfficeFieldsRow(TestResponse $page, string $label): ?string
{
    $cells = backOfficeFieldsCells($page, $label);

    return $cells->length === 0 ? null : trim((string) preg_replace('/\s+/', ' ', $cells->item(0)->textContent));
}

/**
 * The barcodes beside the label, in page order: each barcode image's alt
 * (its value) or a value Code 128 cannot draw, printed as text.
 *
 * @return list<string>
 */
function backOfficeFieldsBarcodes(TestResponse $page, string $label): array
{
    $cells = backOfficeFieldsCells($page, $label);
    if ($cells->length === 0) {
        return [];
    }
    $nodes = (new DOMXPath($cells->item(0)->ownerDocument))->query('.//img | .//span', $cells->item(0));

    return array_map(
        fn (DOMElement $node): string => $node->tagName === 'img' ? $node->getAttribute('alt') : trim($node->textContent),
        iterator_to_array($nodes),
    );
}

/** @return list<string> */
function backOfficeFieldsTags(TestResponse $page, string $label): array
{
    $cells = backOfficeFieldsCells($page, $label);
    if ($cells->length === 0) {
        return [];
    }
    $links = (new DOMXPath($cells->item(0)->ownerDocument))->query('.//a', $cells->item(0));

    return array_map(fn (DOMNode $link): string => trim($link->textContent), iterator_to_array($links));
}

test('a product saved on the back office form shows its barcodes, weight, quantities, shipping and tags to a verified customer', function () {
    $product = backOfficeFieldsCreate();
    // The warehouse's own barcode of the same product is listed beside the store ones.
    ProductBarcode::query()->create(['product_id' => $product->id, 'barcode' => '5412345678901']);

    $page = backOfficeFieldsPage($product);

    expect(backOfficeFieldsBarcodes($page, __('storefront.product.barcodes')))->toBe(['5412345678901', '8712345678906', '8712345678913'])
        ->and(backOfficeFieldsRow($page, __('storefront.product.weight')))->toBe('1.25 kg')
        ->and(backOfficeFieldsRow($page, __('storefront.product.min_qty')))->toBe('3')
        ->and(backOfficeFieldsRow($page, __('storefront.product.max_qty')))->toBe('24')
        ->and(backOfficeFieldsRow($page, __('storefront.checkout.shipping')))
        ->toContain(__('storefront.checkout.flat_shipping_rate'))
        ->toContain(strip_tags(single_price(4.95)))
        ->toContain('('.__('storefront.product.shipping_per_unit').')')
        ->and(backOfficeFieldsTags($page, __('storefront.product.tags')))->toBe(['oil', 'olive']);
    $page->assertSee(backOfficeFieldsEstimateLabel())
        ->assertSee('3 '.__('storefront.common.days'));
});

test('a guest sees the same fields, without the flat shipping amount', function () {
    $product = backOfficeFieldsCreate();

    $page = backOfficeFieldsPage($product, guest: true);

    expect(backOfficeFieldsBarcodes($page, __('storefront.product.barcodes')))->toBe(['8712345678906', '8712345678913'])
        ->and(backOfficeFieldsRow($page, __('storefront.product.weight')))->toBe('1.25 kg')
        ->and(backOfficeFieldsRow($page, __('storefront.product.min_qty')))->toBe('3')
        ->and(backOfficeFieldsRow($page, __('storefront.product.max_qty')))->toBe('24')
        ->and(backOfficeFieldsRow($page, __('storefront.checkout.shipping')))->toBe(__('storefront.checkout.flat_shipping_rate'))
        ->and(backOfficeFieldsTags($page, __('storefront.product.tags')))->toBe(['oil', 'olive']);
    $page->assertDontSee(strip_tags(single_price(4.95)));
});

test('the fields are printed in Arabic too', function () {
    $product = backOfficeFieldsCreate();
    $page = $this->withSession(['locale' => 'ar'])->get('/temp/product/'.$product->slug)->assertOk();

    expect(backOfficeFieldsBarcodes($page, 'الباركود'))->toBe(['8712345678906', '8712345678913'])
        ->and(backOfficeFieldsRow($page, 'الوزن'))->toBe('1.25 كغ')
        ->and(backOfficeFieldsRow($page, 'الحد الأدنى للكمية'))->toBe('3')
        ->and(backOfficeFieldsRow($page, 'الحد الأقصى للكمية'))->toBe('24')
        ->and(backOfficeFieldsRow($page, 'الشحن'))->toContain('رسوم شحن ثابتة')
        ->and(backOfficeFieldsTags($page, 'الوسوم'))->toBe(['oil', 'olive']);
});

test('editing the product on the back office form changes what the page shows', function () {
    $product = backOfficeFieldsCreate();
    $page = backOfficeFieldsPage($product);
    expect(backOfficeFieldsBarcodes($page, __('storefront.product.barcodes')))->toContain('8712345678906');

    // Reading the page signed in a customer; the edit is the admin's.
    actingAsStoreUser(createStoreAdmin());
    $this->post(route('products.update', $product), [
        ...backOfficeFieldsForm([
            'weight' => '2',
            'min_qty' => '5',
            'max_qty' => '10',
            'tags' => 'fresh, local',
            'product_barcodes' => ['9780201379624'],
            'shipping_type' => 'free',
            'flat_shipping_cost' => null,
            'est_shipping_days' => '7',
        ]),
        'lang' => 'en',
        'old_photo' => null,
    ])->assertSessionHasNoErrors();

    $page = backOfficeFieldsPage($product->refresh());

    expect(backOfficeFieldsBarcodes($page, __('storefront.product.barcodes')))->toBe(['9780201379624'])
        ->and(backOfficeFieldsRow($page, __('storefront.product.weight')))->toBe('2 kg')
        ->and(backOfficeFieldsRow($page, __('storefront.product.min_qty')))->toBe('5')
        ->and(backOfficeFieldsRow($page, __('storefront.product.max_qty')))->toBe('10')
        ->and(backOfficeFieldsRow($page, __('storefront.checkout.shipping')))->toBe(__('storefront.product.free_shipping'))
        ->and(backOfficeFieldsTags($page, __('storefront.product.tags')))->toBe(['fresh', 'local']);
    $page->assertSee('7 '.__('storefront.common.days'))
        ->assertDontSee('8712345678906')
        ->assertDontSee(__('storefront.checkout.flat_shipping_rate'));
});

test('the barcodes are one merged set drawn as the WMS barcode label, inline, with Arabic digits converted', function () {
    $product = backOfficeFieldsCreate(['product_barcodes' => ['٥٤١٢٣٤٥٦٧٨٩٠١', '8712345678906', 'متجر']]);
    // The WMS barcode equals the first store one once converted: shown once.
    ProductBarcode::query()->create(['product_id' => $product->id, 'barcode' => '5412345678901']);
    ProductBarcode::query()->create(['product_id' => $product->id, 'barcode' => 'WMS-7']);
    DB::table('products')->where('id', $product->id)->update(['barcode' => 'LEGACY-<9>']);

    $page = backOfficeFieldsPage($product);
    $images = htmlXPath($page)->query("//img[starts-with(@src, 'data:image/svg+xml;base64,')]");
    $labels = new ProductBarcodeLabels;
    $fresh = Product::query()->findOrFail($product->id);

    expect(backOfficeFieldsBarcodes($page, __('storefront.product.barcodes')))->toBe(['5412345678901', 'WMS-7', '8712345678906', 'متجر', 'LEGACY-<9>'])
        ->and($images->length)->toBe(4)
        ->and(array_map(fn (DOMElement $img): string => $img->getAttribute('src'), iterator_to_array($images)))->toBe([
            $labels->dataUri($fresh, '5412345678901'),
            $labels->dataUri($fresh, 'WMS-7'),
            $labels->dataUri($fresh, '8712345678906'),
            $labels->dataUri($fresh, 'LEGACY-<9>'),
        ])
        ->and(base64_decode(substr($images->item(0)->getAttribute('src'), 26)))->toContain('>5412345678901</text>')->toContain('>Extra virgin olive oil</text>');
    // The value printed as text is escaped, and no external barcode service is asked.
    $page->assertSee('متجر')->assertDontSee('tec-it', false)->assertDontSee('LEGACY-<9>', false);
});

test('the barcode images add no queries, however many barcodes a product has', function () {
    $product = backOfficeFieldsCreate(['product_barcodes' => ['8712345678906']]);
    app('auth')->forgetGuards();
    $count = function () use ($product): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/temp/product/'.$product->slug)->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };
    $this->get('/temp/product/'.$product->slug)->assertOk();
    $one = $count();
    StoreProductBarcode::query()->create(['product_id' => $product->id, 'barcode' => '8712345678913']);
    ProductBarcode::query()->create(['product_id' => $product->id, 'barcode' => '5412345678901']);
    ProductBarcode::query()->create(['product_id' => $product->id, 'barcode' => '5412345678918']);

    expect($count())->toBe($one);
});

test('a product with the form defaults prints only the rows that carry a value', function () {
    // Another product with every field set must not leak into this page.
    backOfficeFieldsCreate(['name' => 'Noise oil', 'tags' => 'noise-tag', 'product_barcodes' => ['4006381333931']]);
    $product = backOfficeFieldsCreate([
        'name' => 'Plain oil',
        'weight' => '0.00',
        'min_qty' => '1',
        'max_qty' => '1000',
        'tags' => null,
        'product_barcodes' => [],
        'shipping_type' => 'free',
        'flat_shipping_cost' => null,
        'est_shipping_days' => null,
    ]);

    $page = backOfficeFieldsPage($product, guest: true);

    expect(backOfficeFieldsRow($page, __('storefront.product.barcodes')))->toBeNull()
        ->and(backOfficeFieldsRow($page, __('storefront.product.weight')))->toBeNull()
        ->and(backOfficeFieldsRow($page, __('storefront.product.min_qty')))->toBeNull()
        ->and(backOfficeFieldsRow($page, __('storefront.product.max_qty')))->toBe('1000')
        ->and(backOfficeFieldsRow($page, __('storefront.checkout.shipping')))->toBe(__('storefront.product.free_shipping'))
        ->and(backOfficeFieldsRow($page, __('storefront.product.tags')))->toBeNull();
    $page->assertDontSee('noise-tag')->assertDontSee('4006381333931')
        ->assertDontSee(backOfficeFieldsEstimateLabel());
});

test('with the shipping menu off the form posts no shipping fields and the page says free shipping', function () {
    storeSetting('Setup_Shipping_section', 0);

    $product = backOfficeFieldsCreate();

    // The shipping inputs are dropped by the request, so the columns keep their defaults.
    expect($product->est_shipping_days)->toBeNull()
        ->and((float) $product->shipping_cost)->toBe(0.0);
    $page = backOfficeFieldsPage($product, guest: true);
    expect(backOfficeFieldsRow($page, __('storefront.checkout.shipping')))->toBe(__('storefront.product.free_shipping'))
        ->and(backOfficeFieldsRow($page, __('storefront.product.weight')))->toBe('1.25 kg');
    $page->assertDontSee(backOfficeFieldsEstimateLabel());
});

test('an unpublished product has no page, whatever its fields hold', function () {
    $product = backOfficeFieldsCreate(['button' => 'unpublish']);

    expect($product->published)->toBeFalse();
    $this->get('/temp/product/'.$product->slug)->assertNotFound();
    expect(DB::table('products')->where('id', $product->id)->value('published'))->toBe(0);
});
