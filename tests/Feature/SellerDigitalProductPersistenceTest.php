<?php

use App\Models\Category;
use App\Models\Language;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductTax;
use App\Models\Upload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

function sellerDigitalInput(array $overrides = []): array
{
    return ['name' => 'Seller ebook', 'category_id' => test()->category->id,
        'tags' => ['[{"value":"ebook"},{"value":"guide"}]'], 'description' => '<p>Useful guide</p>',
        'unit_price' => '12.50', 'purchase_price' => '3.50', 'discount' => '1.50', 'discount_type' => 'amount',
        'photos' => null, 'thumbnail_img' => null, 'meta_title' => 'Ebook title',
        'meta_description' => 'Ebook summary', 'meta_img' => null, 'file' => test()->file->id, ...$overrides];
}

function sellerDigitalSnapshot(): array
{
    return collect(['products', 'product_stocks', 'product_translations', 'product_taxes'])
        ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
}

beforeEach(function () {
    seedStoreCurrency();
    config(['app.default_language' => 'en']);
    Language::forceCreate(['code' => 'en', 'name' => 'English', 'rtl' => 0]);
    Language::forceCreate(['code' => 'ar', 'name' => 'Arabic', 'rtl' => 1]);
    $this->seller = actingAsStoreUser(createStoreUser('seller'));
    $this->category = Category::forceCreate(['name' => 'Books', 'slug' => 'books', 'digital' => 1, 'parent_id' => 0]);
    $this->file = Upload::factory()->create(['user_id' => $this->seller->id, 'type' => 'document', 'extension' => 'pdf']);
    DB::table('business_settings')->insert(['type' => 'product_approve_by_admin', 'value' => '1']);
});

test('digital create characterizes assigned fields and server authority', function () {
    $image = Upload::factory()->create(['user_id' => $this->seller->id, 'type' => 'image']);
    $tax = DB::table('taxes')->insertGetId(['name' => 'VAT', 'tax_status' => 1]);
    $this->post(route('seller.digitalproducts.store'), sellerDigitalInput([
        'photos' => (string) $image->id, 'thumbnail_img' => $image->id, 'meta_img' => $image->id,
        'tax_id' => [$tax], 'tax' => ['21'], 'tax_type' => ['percent'],
    ]))->assertRedirect(route('seller.digitalproducts'))->assertSessionHasNoErrors();
    $product = Product::query()->sole();
    $fields = $product->getAttributes();
    expect($product->name)->toBe('Seller ebook')->and($product->user_id)->toBe($this->seller->id)
        ->and($fields['added_by'])->toBe('seller')->and((int) $fields['digital'])->toBe(1)
        ->and((int) $fields['approved'])->toBe(0)->and($product->published)->toBeTrue()
        ->and($fields['synced_at'])->toBeNull()->and((int) $fields['category_id'])->toBe($this->category->id)
        ->and($fields['tags'])->toBe('ebook,guide')->and($fields['description'])->toBe('<p>Useful guide</p>')
        ->and((float) $fields['unit_price'])->toBe(12.5)->and((float) $fields['purchase_price'])->toBe(3.5)
        ->and((float) $fields['discount'])->toBe(1.5)->and($fields['discount_type'])->toBe('amount')
        ->and($fields['meta_title'])->toBe('Ebook title')->and($fields['meta_description'])->toBe('Ebook summary')
        ->and((int) $fields['file_name'])->toBe($this->file->id)->and($fields['photos'])->toBe((string) $image->id)
        ->and((int) $fields['thumbnail_img'])->toBe($image->id)->and((int) $fields['meta_img'])->toBe($image->id)
        ->and((float) $product->stocks()->sole()->price)->toBe(12.5)->and((int) $product->stocks()->sole()->qty)->toBe(0)
        ->and($product->product_translations()->sole()->name)->toBe('Seller ebook')
        ->and((float) $product->taxes()->sole()->tax)->toBe(21.0);
});

test('digital update characterizes assigned fields without changing authority', function () {
    $product = createStoreProduct(['user_id' => $this->seller->id, 'added_by' => 'seller', 'digital' => 1, 'approved' => 0, 'published' => 0]);
    $this->post(route('seller.digitalproducts.update', $product), sellerDigitalInput(['lang' => 'en', 'slug' => 'new-ebook']))->assertRedirect()->assertSessionHasNoErrors();
    $product->refresh();
    expect($product->name)->toBe('Seller ebook')->and($product->slug)->toBe('new-ebook')
        ->and((float) $product->unit_price)->toBe(12.5)->and((float) $product->purchase_price)->toBe(3.5)
        ->and($product->user_id)->toBe($this->seller->id)->and($product->added_by)->toBe('seller')
        ->and((int) $product->approved)->toBe(0)->and($product->published)->toBeFalse()
        ->and($product->tags)->toBe('ebook,guide')->and((int) $product->file_name)->toBe($this->file->id)
        ->and($product->product_translations()->sole()->description)->toBe('<p>Useful guide</p>');
});

test('digital refuses characterized foreign upload overlong name missing file and unknown field', function (string $case) {
    $input = match ($case) {
        'foreign' => ['file' => Upload::factory()->create(['user_id' => createStoreUser('seller')->id])->id],
        'long name' => ['name' => str_repeat('é', 201)], 'missing file' => ['file' => null], 'unknown' => ['unexpected' => 'dropped'],
    };
    $this->post(route('seller.digitalproducts.store'), sellerDigitalInput($input))->assertSessionHasErrors(array_key_first($input));
    expect(Product::count())->toBe(0);
})->with(['foreign', 'long name', 'missing file', 'unknown']);

test('digital refuses another sellers id before validating input', function () {
    $product = createStoreProduct(['user_id' => createStoreUser('seller')->id]);
    $before = sellerDigitalSnapshot();
    $this->post(route('seller.digitalproducts.update', $product), sellerDigitalInput(['lang' => 'en', 'name' => []]))->assertRedirect()->assertSessionHasNoErrors();
    expect(flashedMessage())->toBe('This product is not yours.');
    expect(sellerDigitalSnapshot())->toBe($before);
});

test('digital forms refuse invalid input before changing any row', function (array $changes, string $field, string $action) {
    $product = createStoreProduct(['user_id' => $this->seller->id, 'added_by' => 'seller', 'digital' => 1]);
    $before = sellerDigitalSnapshot();
    $this->post($action === 'store' ? route('seller.digitalproducts.store') : route('seller.digitalproducts.update', $product),
        sellerDigitalInput([...($action === 'update' ? ['lang' => 'en', 'slug' => 'ebook'] : []), ...$changes]))->assertSessionHasErrors($field);
    expect(sellerDigitalSnapshot())->toBe($before);
})->with([
    'name required' => [['name' => null], 'name'], 'name array' => [['name' => ['bad']], 'name'], 'name overflow' => [['name' => str_repeat('é', 201)], 'name'],
    'category required' => [['category_id' => null], 'category_id'], 'category absent' => [['category_id' => 999999], 'category_id'], 'category array' => [['category_id' => [1]], 'category_id'],
    'price required' => [['unit_price' => null], 'unit_price'], 'price array' => [['unit_price' => [1]], 'unit_price'], 'price negative' => [['unit_price' => -1], 'unit_price'],
    'price overflow' => [['unit_price' => '1000000000000000000'], 'unit_price'], 'price precision' => [['unit_price' => '1.001'], 'unit_price'], 'price infinite' => [['unit_price' => '1e999'], 'unit_price'],
    'purchase required' => [['purchase_price' => null], 'purchase_price'], 'purchase negative' => [['purchase_price' => -1], 'purchase_price'], 'purchase overflow' => [['purchase_price' => '1000000000000000000'], 'purchase_price'],
    'discount required' => [['discount' => null], 'discount'], 'discount negative' => [['discount' => -1], 'discount'], 'discount exceeds price' => [['discount' => '12.51'], 'discount'],
    'percent exceeds hundred' => [['discount_type' => 'percent', 'discount' => '100.01'], 'discount'], 'discount type required' => [['discount_type' => null], 'discount_type'], 'discount type forged' => [['discount_type' => 'forged'], 'discount_type'],
    'file required' => [['file' => null], 'file'], 'file missing' => [['file' => 999999], 'file'], 'file array' => [['file' => [1]], 'file'],
    'tags malformed' => [['tags' => ['bad']], 'tags.0'], 'tags shape' => [['tags' => 'bad'], 'tags'], 'tags object' => [['tags' => ['{"value":"bad"}']], 'tags.0'],
    'tags nested unknown' => [['tags' => ['[{"value":"tag","unknown":1}]']], 'tags.0'], 'tags overflow' => [['tags' => [json_encode([['value' => str_repeat('é', 501)]])]], 'tags.0'],
    'gallery missing' => [['photos' => '999999'], 'photos'], 'gallery duplicates' => [['photos' => '1,1'], 'photos'], 'thumbnail missing' => [['thumbnail_img' => 999999], 'thumbnail_img'], 'meta image missing' => [['meta_img' => 999999], 'meta_img'],
    'description array' => [['description' => ['bad']], 'description'], 'meta title array' => [['meta_title' => ['bad']], 'meta_title'], 'meta description array' => [['meta_description' => ['bad']], 'meta_description'],
    'unknown empty' => [['unexpected' => null], 'unexpected'], 'owner' => [['user_id' => 1], 'user_id'], 'approval' => [['approved' => 1], 'approved'], 'publication' => [['published' => 0], 'published'], 'origin' => [['synced_at' => null], 'synced_at'], 'type' => [['digital' => 0], 'digital'],
])->with(['store', 'update']);

test('digital stock failure rolls back product translations taxes and stock', function (string $action) {
    $this->post(route('seller.digitalproducts.store'), sellerDigitalInput())->assertSessionHasNoErrors();
    $product = Product::query()->sole();
    $before = sellerDigitalSnapshot();
    ProductStock::saving(fn () => throw new RuntimeException('stock failure'));
    try {
        $this->post($action === 'store' ? route('seller.digitalproducts.store') : route('seller.digitalproducts.update', $product), sellerDigitalInput([...($action === 'update' ? ['lang' => 'en', 'slug' => 'changed'] : []), 'name' => 'Must roll back']))->assertServerError();
    } finally {
        ProductStock::flushEventListeners();
    }
    expect(sellerDigitalSnapshot())->toBe($before);
})->with(['store', 'update']);

test('digital create obeys the physical pending approval policy', function () {
    $this->post(route('seller.digitalproducts.store'), sellerDigitalInput())->assertSessionHasNoErrors();
    expect((int) Product::query()->sole()->approved)->toBe(0);
});

test('digital forms show required marks one legend and safe old input with errors', function (string $form) {
    createShopFor($this->seller, 0);
    $product = createStoreProduct(['user_id' => $this->seller->id, 'digital' => 1, 'added_by' => 'seller']);
    $url = $form === 'create' ? route('seller.digitalproducts.create') : route('seller.digitalproducts.edit', $product);
    $action = $form === 'create' ? route('seller.digitalproducts.store') : route('seller.digitalproducts.update', $product);
    $this->from($url)->post($action, sellerDigitalInput([...($form === 'edit' ? ['lang' => 'en'] : []), 'name' => '<b>Book</b>', 'unit_price' => []]));
    $response = $this->get($url)->assertOk()->assertSee('&lt;b&gt;Book&lt;/b&gt;', false);
    $xpath = htmlXPath($response);
    $node = formPostingTo($xpath, $action);
    expect($xpath->query('.//*[@data-required-legend]', $node)->length)->toBe(1)
        ->and($xpath->query('.//*[contains(@class,"invalid-feedback")]', $node)->length)->toBeGreaterThan(0);
    foreach (['name', 'category_id', 'file', 'unit_price', 'purchase_price', 'discount', 'discount_type'] as $field) {
        $control = $xpath->query('.//*[@name="'.$field.'"]', $node)->item(0);
        expect($control?->hasAttribute('required'))->toBeTrue($field);
        $label = $xpath->query('.//label[@for="'.$control->getAttribute('id').'"]', $node)->item(0);
        expect($label)->not->toBeNull($field);
        expect($xpath->query('.//*[@aria-hidden="true"]', $label)->length)->toBeGreaterThan(0);
    }
    foreach (['photos', 'thumbnail_img', 'meta_img', 'tags[]', 'description'] as $field) {
        expect($xpath->query('.//*[@name="'.$field.'"]', $node)->item(0)->hasAttribute('required'))->toBeFalse($field);
    }
})->with(['create', 'edit']);

test('digital upload references must have an allowed type and belong to the seller even when retained', function (string $field, string $case, string $action) {
    $upload = Upload::factory()->create(['user_id' => $case === 'foreign' ? createStoreUser('seller')->id : $this->seller->id, 'type' => $case === 'foreign' ? 'image' : 'forged']);
    $product = createStoreProduct(['user_id' => $this->seller->id, 'digital' => 1, 'file_name' => $upload->id, 'photos' => (string) $upload->id, 'thumbnail_img' => $upload->id, 'meta_img' => $upload->id]);
    $before = sellerDigitalSnapshot();
    $this->post($action === 'store' ? route('seller.digitalproducts.store') : route('seller.digitalproducts.update', $product), sellerDigitalInput([
        ...($action === 'update' ? ['lang' => 'en'] : []), $field => $field === 'photos' ? (string) $upload->id : $upload->id,
    ]))->assertSessionHasErrors($field);
    expect(sellerDigitalSnapshot())->toBe($before);
})->with(['photos', 'thumbnail_img', 'meta_img', 'file'])->with(['foreign', 'type'])->with(['store', 'update']);

test('digital field boundaries and all supported downloadable types round trip', function (string $type) {
    $this->file->forceFill(['type' => $type])->save();
    $input = sellerDigitalInput(['name' => str_repeat('œ', 200), 'tags' => [json_encode([['value' => str_repeat('é', 500)]])], 'unit_price' => '999999999999999999.99', 'purchase_price' => '999999999999999999.99', 'discount' => 100, 'discount_type' => 'percent']);
    $this->post(route('seller.digitalproducts.store'), $input)->assertSessionHasNoErrors();
    $first = Product::query()->sole();
    $this->post(route('seller.digitalproducts.store'), $input)->assertSessionHasNoErrors();
    $second = Product::query()->latest('id')->firstOrFail();
    expect($first->slug)->not->toBe($second->slug)->and(mb_strlen($second->slug))->toBeLessThanOrEqual(255)->and($second->slug)->not->toBeEmpty();
    $this->post(route('seller.digitalproducts.update', $second), [...$input, 'lang' => 'en', 'slug' => $second->slug])->assertSessionHasNoErrors();
    expect($second->fresh()->name)->toBe($input['name'])->and($second->fresh()->tags)->toBe(str_repeat('é', 500));
    if (DB::getDriverName() === 'mysql') {
        expect($second->fresh()->unit_price)->toBe('999999999999999999.99')->and($second->stocks()->sole()->price)->toBe('999999999999999999.99');
    }
})->with(['image', 'audio', 'video', 'document', 'archive']);

test('digital zero prices and complete discount are accepted and approval follows setting', function () {
    DB::table('business_settings')->where('type', 'product_approve_by_admin')->update(['value' => 0]);
    $this->post(route('seller.digitalproducts.store'), sellerDigitalInput(['name' => '茶', 'unit_price' => 0, 'purchase_price' => 0, 'discount' => 0]))->assertSessionHasNoErrors();
    $product = Product::query()->sole();
    expect((int) $product->approved)->toBe(1)->and($product->published)->toBeTrue()->and($product->slug)->not->toBeEmpty();
    $this->post(route('seller.digitalproducts.update', $product), sellerDigitalInput(['lang' => 'en', 'discount' => '12.50']))->assertSessionHasNoErrors();
});

test('digital translations and feed ownership are checked before related writes', function () {
    $product = createStoreProduct(['user_id' => $this->seller->id, 'added_by' => 'seller', 'digital' => 1, 'name' => 'Feed title', 'description' => 'Base description', 'published' => 0, 'approved' => 0]);
    DB::table('products')->where('id', $product->id)->update(['synced_at' => now()]);
    $before = sellerDigitalSnapshot();
    $this->post(route('seller.digitalproducts.update', $product), sellerDigitalInput(['lang' => 'en']))->assertSessionHasErrors('name');
    expect(sellerDigitalSnapshot())->toBe($before);
    $this->post(route('seller.digitalproducts.update', $product), sellerDigitalInput(['lang' => 'ar', 'name' => 'دليل', 'description' => '<p>Arabic guide</p>']))->assertSessionHasNoErrors();
    expect($product->fresh()->name)->toBe('Feed title')->and($product->fresh()->description)->toBe('Base description')
        ->and($product->fresh()->published)->toBeFalse()->and((int) $product->fresh()->approved)->toBe(0)
        ->and($product->product_translations()->where('lang', 'ar')->sole()->name)->toBe('دليل');
});

test('digital update refuses invalid language and slug without writes', function (array $changes, string $field) {
    $product = createStoreProduct(['user_id' => $this->seller->id, 'digital' => 1]);
    createStoreProduct(['slug' => 'taken']);
    $before = sellerDigitalSnapshot();
    $this->post(route('seller.digitalproducts.update', $product), sellerDigitalInput(['lang' => 'en', ...$changes]))->assertSessionHasErrors($field);
    expect(sellerDigitalSnapshot())->toBe($before);
})->with([
    'language required' => [['lang' => null], 'lang'], 'language unknown' => [['lang' => 'xx'], 'lang'],
    'language array' => [['lang' => ['en']], 'lang'], 'slug long' => [['slug' => str_repeat('a', 256)], 'slug'],
    'slug array' => [['slug' => ['bad']], 'slug'], 'slug duplicate' => [['slug' => 'TAKEN'], 'slug'], 'slug invalid' => [['slug' => 'bad/slug'], 'slug'],
]);

test('digital tax rows validate shape reference type and amount before all writes', function (string $case) {
    $tax = DB::table('taxes')->insertGetId(['name' => 'VAT', 'tax_status' => 1]);
    $changes = match ($case) {
        'missing tax' => ['tax_id' => [$tax], 'tax_type' => ['percent']],
        'unknown id' => ['tax_id' => [999999], 'tax' => [1], 'tax_type' => ['amount']],
        'duplicate' => ['tax_id' => [$tax, $tax], 'tax' => [1, 1], 'tax_type' => ['amount', 'amount']],
        'type' => ['tax_id' => [$tax], 'tax' => [1], 'tax_type' => ['bad']],
        'negative' => ['tax_id' => [$tax], 'tax' => [-1], 'tax_type' => ['amount']],
        'percent' => ['tax_id' => [$tax], 'tax' => ['100.01'], 'tax_type' => ['percent']],
        'precision' => ['tax_id' => [$tax], 'tax' => ['1.001'], 'tax_type' => ['amount']],
    };
    $this->post(route('seller.digitalproducts.store'), sellerDigitalInput($changes))->assertSessionHasErrors();
    expect(Product::count())->toBe(0);
})->with(['missing tax', 'unknown id', 'duplicate', 'type', 'negative', 'percent', 'precision']);

test('digital writes refuse query and file unknown input and non sellers', function () {
    $this->post(route('seller.digitalproducts.store').'?approved=1', sellerDigitalInput())->assertSessionHasErrors('approved');
    $this->post(route('seller.digitalproducts.store'), sellerDigitalInput(['unexpected_file' => UploadedFile::fake()->create('x.txt')]))->assertSessionHasErrors('unexpected_file');
    actingAsStoreUser(createStoreUser('customer'));
    $this->post(route('seller.digitalproducts.store'), sellerDigitalInput())->assertNotFound();
    expect(Product::count())->toBe(0);
});

test('digital accepts the descendant categories offered by its picker', function () {
    $child = Category::forceCreate(['name' => 'Guides', 'slug' => 'guides', 'digital' => 0, 'parent_id' => $this->category->id]);
    $this->post(route('seller.digitalproducts.store'), sellerDigitalInput(['category_id' => $child->id]))->assertSessionHasNoErrors();
    expect((int) Product::query()->sole()->category_id)->toBe($child->id);
});

test('digital list renders multiple owned products and excludes another seller', function () {
    createShopFor($this->seller, 0);
    foreach (['First ebook', 'Second ebook'] as $name) {
        createStoreProduct(['user_id' => $this->seller->id, 'name' => $name, 'digital' => 1, 'category_id' => $this->category->id]);
    }
    createStoreProduct(['user_id' => createStoreUser('seller')->id, 'digital' => 1, 'name' => 'Other ebook']);
    $this->get(route('seller.digitalproducts'))->assertOk()->assertSee('First ebook')->assertSee('Second ebook')->assertDontSee('Other ebook');
});

test('digital update missing ids are 404 and seller routes refuse guests and customers', function () {
    $this->post(route('seller.digitalproducts.update', 999999), sellerDigitalInput(['lang' => 'en']))->assertNotFound();
    $this->get(route('seller.digitalproducts.edit', 999999))->assertNotFound();
    $product = createStoreProduct(['user_id' => $this->seller->id, 'digital' => 1]);
    $before = sellerDigitalSnapshot();
    actingAsStoreUser(createStoreUser('customer'));
    $this->post(route('seller.digitalproducts.update', $product), sellerDigitalInput(['lang' => 'en']))->assertNotFound();
    $this->get(route('seller.digitalproducts.edit', $product))->assertNotFound();
    $this->get(route('seller.digitalproducts.create'))->assertNotFound();
    auth('store')->logout();
    $this->post(route('seller.digitalproducts.store'), sellerDigitalInput())->assertNotFound();
    $this->post(route('seller.digitalproducts.update', $product), sellerDigitalInput())->assertNotFound();
    expect(sellerDigitalSnapshot())->toBe($before);
});

test('digital tax failure rolls back an already updated product and stock', function () {
    $tax = DB::table('taxes')->insertGetId(['name' => 'VAT', 'tax_status' => 1]);
    $input = sellerDigitalInput(['tax_id' => [$tax], 'tax' => [21], 'tax_type' => ['percent']]);
    $this->post(route('seller.digitalproducts.store'), $input)->assertSessionHasNoErrors();
    $product = Product::query()->sole();
    $before = sellerDigitalSnapshot();
    ProductTax::saving(fn () => throw new RuntimeException('tax failure'));
    try {
        $this->post(route('seller.digitalproducts.update', $product), [...$input, 'lang' => 'en', 'name' => 'Changed', 'unit_price' => '40.50'])->assertServerError();
    } finally {
        ProductTax::flushEventListeners();
    }
    expect(sellerDigitalSnapshot())->toBe($before);
});
