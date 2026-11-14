<?php

use App\Models\Product;
use App\Models\ProductBarcode;
use Illuminate\Support\Facades\DB;

/**
 * The storefront product page (`/temp/product/{slug}`) prints every public
 * product field in English and Arabic, shows no price to a guest, and leaves
 * the internal columns (cost, margin, sync and feed ids, the digital file,
 * sales counters) out of the HTML.
 */
beforeEach(function () {
    seedStoreCurrency();
});

/** The label cell of a details row, as the page prints it. */
function productDetailsLabel(string $label): string
{
    return 'fw-400">'.$label.'</div>';
}

/**
 * @param  array<string, mixed>  $attributes
 */
function detailsProduct(array $attributes = []): Product
{
    $category = DB::table('categories')->insertGetId(['name' => 'Dairy', 'slug' => 'dairy', 'level' => 0]);
    DB::table('category_translations')->insert(['category_id' => $category, 'name' => 'الألبان', 'lang' => 'ar']);

    $product = createStoreProduct([
        'slug' => 'fresh-labneh',
        'name' => 'Fresh Labneh',
        'unit_price' => 2.5,
        'unit' => 'Box',
        'unit_equal' => 4,
        'category_id' => $category,
        'can_expire' => true,
        'serial' => 'SER-100',
        'barcode' => '6281000000042',
        'weight' => 0.5,
        'min_qty' => 2,
        'max_qty' => 50,
        'tags' => 'labneh, dairy',
        'tax' => 5,
        'shipping_type' => 'flat_rate',
        'shipping_cost' => 7.35,
        'is_quantity_multiplied' => 1,
        ...$attributes,
    ]);

    // The first barcode is stored with Arabic-Indic digits; the next one repeats it in ASCII.
    ProductBarcode::query()->create(['product_id' => $product->id, 'barcode' => '٦٢٨١٠٠٠٠٠٠٠١١']);
    DB::table('product_barcodes')->insert([
        ['product_id' => $product->id, 'barcode' => '6281000000011'],
        ['product_id' => $product->id, 'barcode' => '6281000000035'],
    ]);

    return $product;
}

function detailsVerifiedCustomer(): void
{
    $customer = createStoreUser('customer');
    $customer->forceFill(['admin_verified' => true])->save();
    actingAsStoreUser($customer->fresh());
}

test('the product page prints every public field in English', function () {
    $product = detailsProduct();
    $skuValue = 'SKU-'.$product->id;

    $response = $this->get('/temp/product/fresh-labneh')->assertOk();

    $response->assertSee('Can expire soon')
        ->assertSee('Material ID: '.$product->id)
        ->assertSee(productDetailsLabel('Category'), false)->assertSee('>Dairy</a>', false)
        ->assertSee('/temp/category/dairy', false)
        ->assertSee(productDetailsLabel('Serial'), false)->assertSee('SER-100')
        ->assertSee(productDetailsLabel('SKU'), false)->assertSee($skuValue)
        ->assertSee(productDetailsLabel('Barcodes'), false)
        ->assertDontSee('barcode.tec-it.com', false)
        ->assertSee('alt="6281000000011"', false)
        ->assertSee('alt="6281000000035"', false)
        ->assertSee('alt="6281000000042"', false)
        ->assertSee('src="data:image/svg+xml;base64,', false)
        ->assertSee(productDetailsLabel('Weight'), false)->assertSee('0.5 kg')
        ->assertSee(productDetailsLabel('Min Qty'), false)
        ->assertSee(productDetailsLabel('Max Qty'), false)
        ->assertSee(productDetailsLabel('Shipping'), false)->assertSee('Flat shipping rate')
        ->assertSee(productDetailsLabel('Tags'), false)
        ->assertSee('>labneh</a>', false)->assertSee('>dairy</a>', false)
        ->assertSee(productDetailsLabel('Tax'), false);
});

test('the product page prints every public field in Arabic', function () {
    $product = detailsProduct();

    $this->withSession(['locale' => 'ar'])->get('/temp/product/fresh-labneh')->assertOk()
        ->assertSee('lang="ar" dir="rtl"', false)
        ->assertSee('قابل لانتهاء الصلاحية قريبًا')
        ->assertSee('رقم المادة: '.$product->id)
        ->assertSee(productDetailsLabel('الفئة'), false)->assertSee('>الألبان</a>', false)
        ->assertSee(productDetailsLabel('الرقم التسلسلي'), false)
        ->assertSee(productDetailsLabel('رمز المنتج (SKU)'), false)
        ->assertSee(productDetailsLabel('الباركود'), false)
        ->assertDontSee(productDetailsLabel('Barcodes'), false)
        ->assertSee('0.5 كغ')
        ->assertSee(productDetailsLabel('الوزن'), false)
        ->assertSee(productDetailsLabel('الحد الأدنى للكمية'), false)
        ->assertSee(productDetailsLabel('الحد الأقصى للكمية'), false)
        ->assertSee(productDetailsLabel('الشحن'), false)->assertSee('رسوم شحن ثابتة')
        ->assertSee(productDetailsLabel('الوسوم'), false)
        ->assertSee(productDetailsLabel('الضريبة'), false);
});

test('a free-shipping product says so in both languages', function () {
    detailsProduct(['shipping_type' => 'free', 'shipping_cost' => 0]);

    $this->get('/temp/product/fresh-labneh')->assertOk()->assertSee('Free shipping')->assertDontSee('Flat shipping rate');
    $this->withSession(['locale' => 'ar'])->get('/temp/product/fresh-labneh')->assertOk()->assertSee('شحن مجاني');
});

test('the tax row also prints for a discounted product', function () {
    detailsProduct(['discount' => 10, 'discount_type' => 'percent']);

    $this->get('/temp/product/fresh-labneh')->assertOk()
        ->assertSee(productDetailsLabel('Tax'), false);
});

test('a guest sees no price or shipping amount, a verified customer sees the shipping amount', function () {
    detailsProduct();

    $this->get('/temp/product/fresh-labneh')->assertOk()
        ->assertSee('Flat shipping rate')
        ->assertDontSee(single_price(7.35))
        ->assertDontSee('(per unit)');

    detailsVerifiedCustomer();

    $this->get('/temp/product/fresh-labneh')->assertOk()
        ->assertSee('Flat shipping rate')
        ->assertSee(single_price(7.35))
        ->assertSee('(per unit)');
});

test('the product page leaves internal columns out for a guest and a verified customer', function () {
    $collection = DB::table('product_collections')->insertGetId(['name' => 'Margin-Gold-Secret', 'serial' => '9', 'amount' => 37]);
    detailsProduct([
        'purchase_price' => 913.37,
        'collection_id' => $collection,
        'api_unit_name' => 'API-UNIT-SECRET',
        'api_unitId' => 'API-ID-SECRET',
        'file_path' => 'secret/download-file.zip',
        'old_photo' => 'OLD-PHOTO-SECRET',
        'num_of_sale' => 4321987,
        'low_stock_quantity' => 7777777,
    ]);
    $secrets = ['913.37', 'Margin-Gold-Secret', 'API-UNIT-SECRET', 'API-ID-SECRET', 'secret/download-file.zip', 'OLD-PHOTO-SECRET', '4321987', '7777777'];

    $guest = $this->get('/temp/product/fresh-labneh')->assertOk();
    foreach ($secrets as $secret) {
        $guest->assertDontSee($secret);
    }

    detailsVerifiedCustomer();

    $customer = $this->get('/temp/product/fresh-labneh')->assertOk();
    foreach ($secrets as $secret) {
        $customer->assertDontSee($secret);
    }
});

test('a plain product prints no empty rows or badge, and other products data does not leak in', function () {
    detailsProduct(['slug' => 'noise-product', 'name' => 'Noise Product', 'serial' => 'NOISE-SERIAL', 'tags' => 'noise-tag', 'barcode' => '9999999999999']);
    createStoreProduct(['slug' => 'plain-product', 'name' => 'Plain Product', 'category_id' => null, 'tags' => null]);

    $this->get('/temp/product/plain-product')->assertOk()
        ->assertDontSee('Can expire soon')
        ->assertDontSee(productDetailsLabel('Category'), false)
        ->assertDontSee(productDetailsLabel('Serial'), false)
        ->assertDontSee(productDetailsLabel('Barcodes'), false)
        ->assertDontSee(productDetailsLabel('Weight'), false)
        ->assertDontSee(productDetailsLabel('Min Qty'), false)
        ->assertDontSee(productDetailsLabel('Tags'), false)
        ->assertDontSee('NOISE-SERIAL')
        ->assertDontSee('noise-tag')
        ->assertDontSee('9999999999999')
        ->assertSee('Free shipping');
});
