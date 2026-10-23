<?php

use App\Models\Category;
use App\Models\Language;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Upload;
use App\Services\Store\InHouseProductWriter;
use App\Services\Store\ProductVariantCombinations;
use App\Services\Store\SellerProductWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** The physical Blade form, including values the legacy service silently drops. */
function sellerPhysicalInput(array $overrides = []): array
{
    return [
        'name' => 'Seller olive oil', 'category_id' => test()->category->id,
        'brand_id' => test()->brand, 'unit' => 'bottle', 'weight' => '1.25', 'min_qty' => '2',
        'tags' => ['[{"value":"olive"},{"value":"oil"}]'], 'barcode' => '8712345678906',
        'photos' => null, 'thumbnail_img' => null,
        'video_provider' => 'youtube', 'video_link' => 'https://www.youtube.com/watch?v=abc',
        'unit_price' => '12.50', 'discount' => '1.50', 'discount_type' => 'amount',
        'date_range' => '01-11-2026 08:00:00 to 30-11-2026 20:30:00',
        'current_stock' => '40', 'sku' => 'OIL-1', 'description' => '<p>Fresh oil</p>',
        'external_link' => 'https://example.com/oil', 'external_link_btn' => 'Buy oil',
        'shipping_type' => 'flat_rate', 'flat_shipping_cost' => '4.95', 'est_shipping_days' => '3',
        'low_stock_quantity' => '5', 'stock_visibility_state' => 'text', 'cash_on_delivery' => '1',
        'meta_title' => 'Olive oil title', 'meta_description' => 'Olive oil summary',
        'meta_img' => null, 'pdf' => null,
        ...$overrides,
    ];
}

beforeEach(function () {
    seedStoreCurrency();
    config(['app.default_language' => 'en']);
    Language::forceCreate(['code' => 'en', 'name' => 'English', 'rtl' => 0]);
    Language::forceCreate(['code' => 'ar', 'name' => 'Arabic', 'rtl' => 1]);
    $this->seller = actingAsStoreUser(createStoreUser('seller'));
    $this->category = Category::forceCreate(['name' => 'Oil', 'slug' => 'oil', 'digital' => 0, 'parent_id' => 0]);
    $this->brand = DB::table('brands')->insertGetId(['name' => 'Olivia', 'slug' => 'olivia']);
    DB::table('business_settings')->insert(['type' => 'product_approve_by_admin', 'value' => '1']);
});

test('physical create now persists the characterized lost columns, seller owner and pending approval', function () {
    $this->post(route('seller.products.store'), sellerPhysicalInput())->assertRedirect(route('seller.products'))->assertSessionHasNoErrors();
    $product = Product::query()->sole();
    $fields = $product->getAttributes();
    expect($product->name)->toBe('Seller olive oil')->and($product->user_id)->toBe($this->seller->id)
        ->and($fields['added_by'])->toBe('seller')->and((int) $fields['approved'])->toBe(0)
        ->and($fields['synced_at'])->toBeNull()->and((int) $fields['category_id'])->toBe($this->category->id)
        ->and((int) $fields['brand_id'])->toBe($this->brand)->and((float) $fields['unit_price'])->toBe(12.5)
        ->and($fields['unit'])->toBe('bottle')->and((float) $fields['weight'])->toBe(1.25)
        ->and((int) $fields['min_qty'])->toBe(2)->and($fields['tags'])->toBe('olive,oil')
        ->and($fields['slug'])->toBe('seller-olive-oil')->and($fields['description'])->toBe('<p>Fresh oil</p>')
        ->and((float) $fields['discount'])->toBe(1.5)->and($fields['discount_type'])->toBe('amount')
        ->and((float) $fields['shipping_cost'])->toBe(4.95)->and((int) $fields['current_stock'])->toBe(40)
        ->and($fields['video_provider'])->toBe('youtube')->and($fields['video_link'])->toBe(sellerPhysicalInput()['video_link'])
        ->and($fields['external_link'])->toBe('https://example.com/oil')->and($fields['external_link_btn'])->toBe('Buy oil')
        ->and($fields['meta_title'])->toBe('Olive oil title')->and($fields['meta_description'])->toBe('Olive oil summary')
        ->and((int) $fields['est_shipping_days'])->toBe(3)->and((int) $fields['low_stock_quantity'])->toBe(5)
        ->and($fields['stock_visibility_state'])->toBe('text')->and((int) $fields['cash_on_delivery'])->toBe(1)
        ->and($fields['barcode'])->toBe('8712345678906')->and($fields['choice_options'])->toBe('[]')
        ->and($fields['colors'])->toBe('[]')->and($fields['attributes'])->toBe('[]')
        ->and((int) $fields['discount_start_date'])->toBe(strtotime('01-11-2026 08:00:00'))
        ->and((int) $fields['discount_end_date'])->toBe(strtotime('30-11-2026 20:30:00'))
        ->and((float) $product->stocks()->sole()->price)->toBe(12.5)
        ->and((int) $product->stocks()->sole()->qty)->toBe(40)
        ->and($product->product_translations()->sole()->unit)->toBe('bottle');
});

test('physical update now persists characterized lost fields and keeps approval publication and ownership', function () {
    $product = createStoreProduct(['user_id' => $this->seller->id, 'added_by' => 'seller', 'approved' => 0, 'published' => 0, 'thumbnail_img' => null]);
    $this->post(route('seller.products.update', $product), sellerPhysicalInput(['lang' => 'en', 'slug' => 'new-oil']))->assertRedirect()->assertSessionHasNoErrors();
    $product->refresh();
    expect($product->name)->toBe('Seller olive oil')->and((float) $product->getAttribute('unit_price'))->toBe(12.5)
        ->and($product->user_id)->toBe($this->seller->id)->and((int) $product->getAttribute('approved'))->toBe(0)
        ->and($product->published)->toBeFalse()->and($product->slug)->toBe('new-oil')
        ->and($product->getAttribute('tags'))->toBe('olive,oil')
        ->and((float) $product->stocks()->sole()->price)->toBe(12.5)
        ->and($product->product_translations()->sole()->unit)->toBe('bottle');
});

/** @return array<string, string> */
function sellerPhysicalSnapshot(): array
{
    return collect(['products', 'product_stocks', 'product_translations', 'product_taxes'])->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
}

test('physical forms refuse invalid and unknown input before changing any row', function (array $overrides, string $field, string $action) {
    $product = createStoreProduct(['user_id' => $this->seller->id, 'added_by' => 'seller', 'thumbnail_img' => null]);
    $before = sellerPhysicalSnapshot();
    $input = sellerPhysicalInput([...($action === 'update' ? ['lang' => 'en'] : []), ...$overrides]);
    $this->post($action === 'store' ? route('seller.products.store') : route('seller.products.update', $product), $input)->assertSessionHasErrors($field);
    expect(sellerPhysicalSnapshot())->toBe($before);
})->with([
    'name array' => [['name' => ['name']], 'name'], 'name overflow' => [['name' => str_repeat('é', 201)], 'name'],
    'category missing' => [['category_id' => 999999], 'category_id'], 'category array' => [['category_id' => [1]], 'category_id'],
    'brand missing' => [['brand_id' => 999999], 'brand_id'], 'brand array' => [['brand_id' => [1]], 'brand_id'],
    'unit array' => [['unit' => ['pc']], 'unit'], 'unit long' => [['unit' => str_repeat('é', 21)], 'unit'],
    'weight overflow' => [['weight' => '1000000'], 'weight'], 'weight negative' => [['weight' => -1], 'weight'],
    'quantity fractional' => [['min_qty' => 1.5], 'min_qty'], 'quantity zero' => [['min_qty' => 0], 'min_qty'],
    'quantity overflow' => [['min_qty' => '2147483648'], 'min_qty'], 'price array' => [['unit_price' => [1]], 'unit_price'],
    'price overflow' => [['unit_price' => '1000000000000000000'], 'unit_price'], 'price negative' => [['unit_price' => -1], 'unit_price'],
    'price precision' => [['unit_price' => '999999999999999999.999'], 'unit_price'], 'price infinite' => [['unit_price' => '1e999'], 'unit_price'],
    'discount array' => [['discount' => [1]], 'discount'], 'discount negative' => [['discount' => -1], 'discount'],
    'discount too large' => [['discount' => '12.50'], 'discount'], 'percentage too large' => [['discount_type' => 'percent', 'discount' => 100], 'discount'],
    'discount type' => [['discount_type' => ['amount']], 'discount_type'], 'discount type forged' => [['discount_type' => 'forged'], 'discount_type'],
    'date array' => [['date_range' => ['bad']], 'date_range'], 'date impossible' => [['date_range' => '30-02-2026 08:00:00 to 30-11-2026 20:30:00'], 'date_range'],
    'date timestamp overflow' => [['date_range' => '01-11-2040 08:00:00 to 30-11-2040 20:30:00'], 'date_range'],
    'date reversed' => [['date_range' => '30-11-2026 08:00:00 to 01-11-2026 20:30:00'], 'date_range'],
    'tags string' => [['tags' => 'bad'], 'tags'], 'tags malformed' => [['tags' => ['bad']], 'tags.0'],
    'tags nested array' => [['tags' => [[['value' => 'bad']]]], 'tags.0'], 'tags unknown property' => [['tags' => ['[{"value":"tag","forged":1}]']], 'tags.0'],
    'tags overflow' => [['tags' => ['[{"value":"'.str_repeat('é', 501).'"}]']], 'tags.0'],
    'stock negative' => [['current_stock' => -1], 'current_stock'], 'stock overflow' => [['current_stock' => '2147483648'], 'current_stock'],
    'sku array' => [['sku' => ['bad']], 'sku'], 'sku overflow' => [['sku' => str_repeat('é', 256)], 'sku'],
    'description array' => [['description' => ['bad']], 'description'], 'meta title array' => [['meta_title' => ['bad']], 'meta_title'],
    'meta description array' => [['meta_description' => ['bad']], 'meta_description'], 'video long' => [['video_link' => 'https://example.com/'.str_repeat('a', 90)], 'video_link'],
    'video scheme' => [['video_link' => 'ftp://example.com/file'], 'video_link'], 'video provider' => [['video_provider' => 'forged'], 'video_provider'],
    'link array' => [['external_link' => ['bad']], 'external_link'], 'link long' => [['external_link' => 'https://example.com/'.str_repeat('a', 490)], 'external_link'],
    'shipping cost negative' => [['flat_shipping_cost' => -1], 'flat_shipping_cost'], 'shipping type' => [['shipping_type' => ['free']], 'shipping_type'],
    'days overflow' => [['est_shipping_days' => '2147483648'], 'est_shipping_days'], 'stock state' => [['stock_visibility_state' => 'forged'], 'stock_visibility_state'],
    'owner forged' => [['user_id' => 1], 'user_id'], 'approval forged' => [['approved' => 1], 'approved'],
    'added by forged' => [['added_by' => 'admin'], 'added_by'], 'publication forged' => [['published' => 1], 'published'],
    'origin forged' => [['synced_at' => '2026-10-22'], 'synced_at'], 'unexpected null' => [['unexpected' => null], 'unexpected'],
    'unexpected array' => [['unexpected' => []], 'unexpected'], 'photo list array' => [['photos' => [1]], 'photos'],
    'unknown variant' => [['price_forged' => 1], 'price_forged'], 'choice array nested' => [['choice_no' => [[1]]], 'choice_no.0'],
])->with(['store', 'update']);

test('seller image and document references must exist with the right type and owner', function (string $field, string $kind, string $action) {
    $owner = $kind === 'own' ? $this->seller : createStoreUser('seller');
    $upload = Upload::factory()->create(['user_id' => $owner->id, 'type' => $field === 'pdf' ? 'document' : 'image']);
    $product = createStoreProduct(['user_id' => $this->seller->id, 'added_by' => 'seller']);
    $before = sellerPhysicalSnapshot();
    $input = sellerPhysicalInput([...($action === 'update' ? ['lang' => 'en'] : []), $field => $field === 'photos' ? (string) $upload->id : $upload->id]);
    $response = $this->post($action === 'store' ? route('seller.products.store') : route('seller.products.update', $product), $input);
    if ($kind === 'own') {
        $response->assertSessionHasNoErrors();
        $written = $action === 'store' ? Product::query()->latest('id')->first() : $product->fresh();
        expect((string) $written->getAttribute($field))->toBe((string) $upload->id);
    } else {
        $response->assertSessionHasErrors($field);
        expect(sellerPhysicalSnapshot())->toBe($before);
    }
})->with(['photos', 'thumbnail_img', 'meta_img', 'pdf'])->with(['own', 'other'])->with(['store', 'update']);

test('every physical endpoint refuses the second sellers product id', function (string $endpoint) {
    $other = createStoreUser('seller');
    $product = createStoreProduct(['user_id' => $other->id, 'added_by' => 'seller', 'thumbnail_img' => null]);
    $before = sellerPhysicalSnapshot();
    match ($endpoint) {
        'edit' => $this->get(route('seller.products.edit', $product))->assertRedirect(),
        'update' => $this->post(route('seller.products.update', $product), ['name' => []])->assertForbidden(),
        'duplicate', 'destroy' => $this->post(route('seller.products.'.$endpoint, $product))->assertRedirect(),
        'published', 'featured' => $this->post(route('seller.products.'.$endpoint), ['id' => $product->id, 'status' => 0])->assertOk()->assertSee('0'),
        'sku_combination_edit' => $this->post(route('seller.products.sku_combination_edit'), ['id' => $product->id, 'name' => 'Preview', 'unit_price' => '12.50'])->assertSessionHasErrors('id'),
    };
    expect(sellerPhysicalSnapshot())->toBe($before);
})->with(['edit', 'update', 'duplicate', 'destroy', 'published', 'featured', 'sku_combination_edit']);

/** @return array<string, mixed> */
function sellerPhysicalVariants(array $overrides = []): array
{
    $size = DB::table('attributes')->insertGetId(['name' => 'Size']);
    DB::table('attribute_values')->insert([['attribute_id' => $size, 'value' => 'M'], ['attribute_id' => $size, 'value' => 'L']]);

    return ['choice_no' => [$size], 'choice_attributes' => [$size], 'choice_options_'.$size => ['M', 'L'],
        'price_M' => '17.50', 'qty_M' => 12, 'sku_M' => 'OIL-M', 'price_L' => '22.50', 'qty_L' => 7, 'sku_L' => 'OIL-L', ...$overrides];
}

test('variants persist their explicit columns and replacement rolls back on stock failure', function () {
    $input = sellerPhysicalInput(sellerPhysicalVariants());
    $this->post(route('seller.products.store'), $input)->assertSessionHasNoErrors();
    $product = Product::query()->sole();
    expect($product->stocks()->orderBy('variant')->pluck('sku')->all())->toBe(['OIL-L', 'OIL-M'])
        ->and((int) $product->getAttribute('variant_product'))->toBe(1);
    $before = sellerPhysicalSnapshot();
    ProductStock::updating(fn () => throw new RuntimeException('stock failure'));
    try {
        $this->post(route('seller.products.update', $product), [...$input, 'lang' => 'en', 'name' => 'Must roll back', 'qty_M' => 91])->assertServerError();
    } finally {
        ProductStock::flushEventListeners();
    }
    expect(sellerPhysicalSnapshot())->toBe($before);
});

test('nondefault language updates translation without changing base text or authority', function () {
    $product = createStoreProduct(['user_id' => $this->seller->id, 'added_by' => 'seller', 'description' => 'Original description']);
    $this->post(route('seller.products.update', $product), sellerPhysicalInput(['lang' => 'ar', 'name' => 'زيت', 'unit' => 'علبة']))->assertSessionHasNoErrors();
    expect($product->fresh()->name)->toBe($product->name)->and($product->fresh()->description)->toBe('Original description')
        ->and($product->product_translations()->where('lang', 'ar')->sole()->name)->toBe('زيت');
});

test('both physical forms render required marks and safely retain scalar old input', function (string $form) {
    createShopFor($this->seller, 0);
    $product = createStoreProduct(['user_id' => $this->seller->id, 'added_by' => 'seller', 'thumbnail_img' => null]);
    $url = $form === 'create' ? route('seller.products.create') : route('seller.products.edit', $product);
    $action = $form === 'create' ? route('seller.products.store') : route('seller.products.update', $product);
    $input = sellerPhysicalInput([...($form === 'edit' ? ['lang' => 'en'] : []), 'name' => '<b>My product</b>', 'unit' => ['invalid']]);
    $this->from($url)->post($action, $input)->assertSessionHasErrors('unit');
    $response = $this->get($url)->assertOk()->assertSee('&lt;b&gt;My product&lt;/b&gt;', false);
    $xpath = htmlXPath($response);
    $node = formPostingTo($xpath, $action);
    expect($xpath->query('.//*[@data-required-legend]', $node)->length)->toBe(1);
    foreach (['name', 'category_id', 'unit', 'min_qty', 'unit_price', 'discount', 'discount_type', 'current_stock', 'stock_visibility_state'] as $field) {
        $control = $xpath->query('.//*[@name="'.$field.'"]', $node)->item(0);
        expect($control?->hasAttribute('required'))->toBeTrue();
        $label = $xpath->query('.//label[@for="'.$control->getAttribute('id').'"]', $node)->item(0);
        expect($xpath->query('.//*[@aria-hidden="true"]', $label)->length)->toBeGreaterThan(0);
    }
})->with(['create', 'edit']);

test('a preexisting stock choice mismatch is refused by both physical writers without data loss', function (string $writer) {
    $product = createStoreProduct(['user_id' => $this->seller->id, 'added_by' => 'seller', 'colors' => '[]', 'choice_options' => '[{"attribute_id":"1","values":["M"]},{"attribute_id":"2","values":["Cotton"]}]']);
    $product->stocks()->delete();
    $product->stocks()->create(['variant' => 'Cotton-M', 'price' => '17.50', 'qty' => 47, 'sku' => 'KEEP']);
    $before = sellerPhysicalSnapshot();
    $data = [...sellerPhysicalInput(), 'tags' => 'olive,oil', 'lang' => 'en', 'choice_options' => [['attribute_id' => 1, 'values' => ['M']], ['attribute_id' => 2, 'values' => ['Cotton']]], 'variants' => [['variant' => 'M-Cotton', 'price' => '12.50', 'qty' => 10]]];
    expect(fn () => DB::transaction(fn () => $writer === 'seller'
        ? app(SellerProductWriter::class)->update($this->seller, $product, $data, ['M-Cotton'])
        : app(InHouseProductWriter::class)->update($product, $data, ['M-Cotton'], [], false, false)))
        ->toThrow(ValidationException::class);
    expect(sellerPhysicalSnapshot())->toBe($before);
})->with(['seller', 'admin']);

test('nested choices variants colours and prices are validated before any write', function (string $case) {
    $variants = sellerPhysicalVariants();
    $id = $variants['choice_no'][0];
    [$changes, $field] = match ($case) {
        'value' => [['choice_options_'.$id => ['forged']], 'choice_options_'.$id.'.0'],
        'nested value' => [['choice_options_'.$id => [['M']]], 'choice_options_'.$id.'.0'],
        'duplicate value' => [['choice_options_'.$id => ['M', 'M']], 'choice_options_'.$id.'.0'],
        'unknown options' => [['choice_options_999999' => ['M']], 'choice_options_999999'],
        'attribute' => [['choice_attributes' => [999999]], 'choice_attributes.0'],
        'colour' => [['colors_active' => 1, 'colors' => ['#UNKNOWN']], 'colors.0'],
        'nested colour' => [['colors_active' => 1, 'colors' => [['#FF0000']]], 'colors.0'],
        'variant price' => [['price_M' => ['17.50']], 'price_M'],
        'variant quantity' => [['qty_M' => -1], 'qty_M'],
        'variant image' => [['img_M' => Upload::factory()->create(['user_id' => createStoreUser('seller')->id, 'type' => 'image'])->id], 'img_M'],
    };
    $before = sellerPhysicalSnapshot();
    $this->post(route('seller.products.store'), sellerPhysicalInput([...$variants, ...$changes]))->assertSessionHasErrors($field);
    expect(sellerPhysicalSnapshot())->toBe($before);
})->with(['value', 'nested value', 'duplicate value', 'unknown options', 'attribute', 'colour', 'nested colour', 'variant price', 'variant quantity', 'variant image']);

test('scalar column boundaries are accepted and forbidden query and file fields are rejected', function () {
    $input = sellerPhysicalInput(['name' => str_repeat('é', 200), 'unit' => str_repeat('é', 20), 'unit_price' => '999999999999999999.99', 'discount' => 0]);
    $this->post(route('seller.products.store').'?approved=1', $input)->assertSessionHasErrors('approved');
    $this->post(route('seller.products.store'), [...$input, 'unexpected_file' => UploadedFile::fake()->create('x.txt')])->assertSessionHasErrors('unexpected_file');
    expect(Product::count())->toBe(0);
    $this->post(route('seller.products.store'), $input)->assertSessionHasNoErrors();
    expect(mb_strlen(Product::query()->sole()->name))->toBe(200)->and(Product::query()->sole()->getAttribute('unit'))->toBe(str_repeat('é', 20));
});

test('cache clearing waits for commit and is discarded with a rolled back outer transaction', function () {
    Artisan::spy();
    $before = sellerPhysicalSnapshot();
    DB::beginTransaction();
    try {
        $this->post(route('seller.products.store'), sellerPhysicalInput())->assertSessionHasNoErrors();
        Artisan::shouldNotHaveReceived('call');
    } finally {
        DB::rollBack();
    }
    expect(sellerPhysicalSnapshot())->toBe($before);
    Artisan::shouldNotHaveReceived('call');
    $this->post(route('seller.products.store'), sellerPhysicalInput())->assertSessionHasNoErrors();
    Artisan::shouldHaveReceived('call')->with('view:clear')->once();
    Artisan::shouldHaveReceived('call')->with('cache:clear')->once();
});

test('the attribute helper returns escaped options as JSON instead of an HTML page', function () {
    $id = DB::table('attributes')->insertGetId(['name' => 'Size']);
    DB::table('attribute_values')->insert(['attribute_id' => $id, 'value' => '<b>M</b>']);
    $response = $this->post(route('seller.products.add-more-choice-option'), ['attribute_id' => $id])
        ->assertOk()->assertHeader('Content-Type', 'application/json');
    expect($response->json())->toBe('<option value="&lt;b&gt;M&lt;/b&gt;">&lt;b&gt;M&lt;/b&gt;</option>');
});

test('the physical product list renders multiple owned products with strict loading enabled', function () {
    createShopFor($this->seller, 0);
    createStoreProduct(['user_id' => $this->seller->id, 'name' => 'First owned', 'category_id' => $this->category->id]);
    createStoreProduct(['user_id' => $this->seller->id, 'name' => 'Second owned', 'category_id' => $this->category->id]);
    createStoreProduct(['user_id' => createStoreUser('seller')->id, 'name' => 'Foreign product']);
    $this->get(route('seller.products'))->assertOk()->assertSee('First owned')->assertSee('Second owned')->assertDontSee('Foreign product');
});

test('an empty optional weight saves its nonnullable zero default on both drivers', function () {
    $this->post(route('seller.products.store'), sellerPhysicalInput(['weight' => null]))->assertRedirect(route('seller.products'))->assertSessionHasNoErrors();
    expect((float) Product::query()->sole()->getAttribute('weight'))->toBe(0.0);
});

test('valid Unicode titles generate distinct nonempty slugs that round trip through edit', function (string $title) {
    createShopFor($this->seller, 0);
    $slugs = [];
    for ($number = 0; $number < 3; $number++) {
        $this->post(route('seller.products.store'), sellerPhysicalInput(['name' => $title]))->assertRedirect(route('seller.products'))->assertSessionHasNoErrors();
        $product = Product::query()->latest('id')->firstOrFail();
        $slugs[] = $product->slug;
        expect($product->slug)->not->toBeEmpty()->and(mb_strlen($product->slug))->toBeLessThanOrEqual(255);
        $this->get(route('seller.products'))->assertOk();
        $this->post(route('seller.products.update', $product), sellerPhysicalInput(['name' => $title, 'lang' => 'en', 'slug' => $product->slug]))->assertRedirect()->assertSessionHasNoErrors();
    }
    expect(array_unique($slugs))->toBe($slugs);
})->with(['Chinese' => '茶', 'punctuation' => '!!!', 'expanded transliteration' => str_repeat('œ', 200)]);

test('a seller explicitly reorders legacy choices and retains the unmatched stock id and values', function () {
    $size = DB::table('attributes')->insertGetId(['name' => 'Size']);
    $fabric = DB::table('attributes')->insertGetId(['name' => 'Fabric']);
    DB::table('attribute_values')->insert([['attribute_id' => $size, 'value' => 'M'], ['attribute_id' => $fabric, 'value' => 'Cotton']]);
    $product = createStoreProduct(['user_id' => $this->seller->id, 'added_by' => 'seller', 'colors' => '[]', 'choice_options' => json_encode([['attribute_id' => $size, 'values' => ['M']], ['attribute_id' => $fabric, 'values' => ['Cotton']]])]);
    $product->stocks()->delete();
    $stock = $product->stocks()->create(['variant' => 'Cotton-M', 'price' => '17.50', 'sku' => 'KEEP', 'qty' => 47]);
    $this->post(route('seller.products.update', $product), sellerPhysicalInput([
        'lang' => 'en', 'choice_no' => [$fabric, $size], 'choice_attributes' => [$size, $fabric],
        'choice_options_'.$fabric => ['Cotton'], 'choice_options_'.$size => ['M'],
        'price_Cotton-M' => '17.50', 'qty_Cotton-M' => 47, 'sku_Cotton-M' => 'KEEP',
    ]))->assertRedirect()->assertSessionHasNoErrors();
    $saved = $product->stocks()->sole();
    expect($saved->id)->toBe($stock->id)->and((float) $saved->price)->toBe(17.5)
        ->and((int) $saved->qty)->toBe(47)->and($saved->sku)->toBe('KEEP')
        ->and(app(ProductVariantCombinations::class)->unmatchedStocks($product->fresh()))->toBe([]);
});

test('SKU edit preview tolerates obsolete validated variant fields while choices are explicitly reordered', function () {
    $size = DB::table('attributes')->insertGetId(['name' => 'Size']);
    $fabric = DB::table('attributes')->insertGetId(['name' => 'Fabric']);
    DB::table('attribute_values')->insert([['attribute_id' => $size, 'value' => 'M'], ['attribute_id' => $fabric, 'value' => 'Cotton']]);
    $product = createStoreProduct(['user_id' => $this->seller->id, 'added_by' => 'seller']);
    $product->stocks()->delete();
    $product->stocks()->create(['variant' => 'Cotton-M', 'price' => '17.50', 'qty' => 47, 'sku' => 'KEEP']);
    $before = sellerPhysicalSnapshot();
    $this->post(route('seller.products.sku_combination_edit'), [
        'id' => $product->id, 'name' => $product->name, 'unit_price' => '12.50',
        'choice_no' => [$fabric, $size], 'choice_options_'.$fabric => ['Cotton'], 'choice_options_'.$size => ['M'],
        'price_M-Cotton' => '12.50', 'qty_M-Cotton' => 10, 'sku_M-Cotton' => 'OLD',
    ])->assertOk()->assertSee('Cotton-M')->assertSee('value="17.5"', false)->assertSee('value="47"', false);
    expect(sellerPhysicalSnapshot())->toBe($before);
});

test('panel copies of boundary-length slugs remain editable and store-origin', function (string $panel) {
    $product = createStoreProduct(['user_id' => $this->seller->id, 'added_by' => 'seller', 'slug' => str_repeat('a', 255)]);
    if ($panel === 'seller') {
        $this->post(route('seller.products.duplicate', $product))->assertRedirect();
    } else {
        actingAsStoreUser(createStoreStaff('product_duplicate'));
        $this->post(route('products.duplicate', $product))->assertRedirect();
    }
    $copy = Product::query()->latest('id')->firstOrFail();
    expect($copy->id)->not->toBe($product->id)->and(mb_strlen($copy->slug))->toBeLessThanOrEqual(255)
        ->and($copy->getAttribute('synced_at'))->toBeNull()->and($copy->user_id)->toBe($this->seller->id);
    actingAsStoreUser($this->seller);
    $this->post(route('seller.products.update', $copy), sellerPhysicalInput(['lang' => 'en', 'slug' => $copy->slug]))->assertRedirect()->assertSessionHasNoErrors();
})->with(['seller', 'admin']);

test('existing attribute values containing field metacharacters have safe editable variant keys', function (string $value) {
    $id = DB::table('attributes')->insertGetId(['name' => 'Pattern']);
    DB::table('attribute_values')->insert(['attribute_id' => $id, 'value' => $value]);
    $choices = ['choice_no' => [$id], 'choice_attributes' => [$id], 'choice_options_'.$id => [$value]];
    $response = $this->post(route('seller.products.sku_combination'), ['unit_price' => '12.50', 'choice_no' => [$id], 'choice_options_'.$id => [$value]])->assertOk();
    $xpath = htmlXPath($response);
    $price = $xpath->query('//input[starts-with(@name,"price_")]')->item(0)->getAttribute('name');
    $qty = $xpath->query('//input[starts-with(@name,"qty_")]')->item(0)->getAttribute('name');
    $this->post(route('seller.products.store'), sellerPhysicalInput([...$choices, $price => '17.50', $qty => 7]))->assertRedirect()->assertSessionHasNoErrors();
    expect(Product::query()->sole()->stocks()->sole()->variant)->toBe(str_replace(' ', '', $value));
})->with(['asterisk' => 'M*', 'brackets' => '[M]', 'backslash' => 'M\\Q']);

test('the seller retains distinct numeric-looking choice values and their exact stock rows', function () {
    createShopFor($this->seller, 0);
    $id = DB::table('attributes')->insertGetId(['name' => 'Size']);
    DB::table('attribute_values')->insert([['attribute_id' => $id, 'value' => '01'], ['attribute_id' => $id, 'value' => '1']]);
    $product = createStoreProduct(['user_id' => $this->seller->id, 'added_by' => 'seller', 'colors' => '[]', 'choice_options' => json_encode([['attribute_id' => $id, 'values' => ['01', '1']]])]);
    $product->stocks()->delete();
    $first = $product->stocks()->create(['variant' => '01', 'price' => '7.50', 'qty' => 1]);
    $second = $product->stocks()->create(['variant' => '1', 'price' => '9.50', 'qty' => 2]);
    $choices = ['choice_no' => [$id], 'choice_options_'.$id => ['01', '1']];
    $this->post(route('seller.products.sku_combination_edit'), ['id' => $product->id, 'unit_price' => '12.50', ...$choices])->assertOk()
        ->assertSee('name="price_01" value="7.5"', false)->assertSee('name="price_1" value="9.5"', false);
    $this->post(route('seller.products.update', $product), sellerPhysicalInput(['lang' => 'en', ...$choices, 'price_01' => '7.50', 'qty_01' => 1, 'price_1' => '9.50', 'qty_1' => 2]))->assertRedirect()->assertSessionHasNoErrors();
    expect($product->stocks()->orderBy('id')->pluck('id')->all())->toBe([$first->id, $second->id]);
});

test('edit preserves its original cash switch and shipping and tax visibility independently of create', function () {
    createShopFor($this->seller, 0);
    foreach (['Setup_Shipping_section' => '0', 'shipping_type' => 'product_wise_shipping', 'VAT_TAX_section' => '0', 'cash_payment' => '1'] as $key => $value) {
        DB::table('business_settings')->insert(['type' => $key, 'value' => $value]);
    }
    $tax = DB::table('taxes')->insertGetId(['name' => 'VAT', 'tax_status' => 1]);
    $product = createStoreProduct(['user_id' => $this->seller->id, 'added_by' => 'seller', 'cash_on_delivery' => 1]);
    $create = htmlXPath($this->get(route('seller.products.create'))->assertOk());
    expect($create->query('//input[@name="flat_shipping_cost"]')->length)->toBe(0)
        ->and($create->query('//input[@name="tax[]"]')->length)->toBe(0);
    $edit = htmlXPath($this->get(route('seller.products.edit', $product))->assertOk());
    expect($edit->query('//input[@name="flat_shipping_cost"]')->length)->toBe(1)
        ->and($edit->query('//input[@name="tax[]"]')->length)->toBe(1)
        ->and($edit->query('//input[@name="cash_on_delivery" and @type="checkbox" and @checked]')->length)->toBe(1);
    $input = sellerPhysicalInput(['lang' => 'en', 'cash_on_delivery' => 0, 'tax_id' => [$tax], 'tax' => ['21'], 'tax_type' => ['percent']]);
    $this->post(route('seller.products.update', $product), $input)->assertRedirect()->assertSessionHasNoErrors();
    expect((int) $product->fresh()->getAttribute('cash_on_delivery'))->toBe(0);
    $this->from(route('seller.products.edit', $product))->post(route('seller.products.update', $product), [...$input, 'unit' => []])->assertSessionHasErrors('unit');
    $edit = htmlXPath($this->get(route('seller.products.edit', $product))->assertOk());
    expect($edit->query('//input[@name="cash_on_delivery" and @type="checkbox" and @checked]')->length)->toBe(0);
});
