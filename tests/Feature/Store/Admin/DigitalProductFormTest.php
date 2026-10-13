<?php

use App\Http\Controllers\Store\Admin\DigitalProductController;
use App\Http\Middleware\BlockMaliciousRequests;
use App\Models\Category;
use App\Models\Language;
use App\Models\Product;
use App\Models\ProductTax;
use App\Models\ProductTranslation;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    seedStoreCurrency();
    config(['app.default_language' => 'en']);
    Language::forceCreate(['code' => 'en', 'name' => 'English', 'rtl' => 0]);
    Language::forceCreate(['code' => 'ar', 'name' => 'Arabic', 'rtl' => 1]);
    $this->root = Category::forceCreate(['name' => 'E-books', 'parent_id' => 0, 'level' => 0, 'slug' => 'e-books', 'digital' => 1]);
    $this->child = Category::forceCreate(['name' => 'Recipes', 'parent_id' => $this->root->id, 'level' => 1, 'slug' => 'recipes', 'digital' => 0]);
    $this->grandchild = Category::forceCreate(['name' => 'Baking', 'parent_id' => $this->child->id, 'level' => 2, 'slug' => 'baking', 'digital' => 0]);
    $this->physical = Category::forceCreate(['name' => 'Oils', 'parent_id' => 0, 'level' => 0, 'slug' => 'oils', 'digital' => 0]);
    DB::table('category_translations')->insert(['category_id' => $this->child->id, 'lang' => 'ar', 'name' => 'وصفات']);
    $this->vat = DB::table('taxes')->insertGetId(['name' => 'VAT', 'tax_status' => 1]);
    $this->service = DB::table('taxes')->insertGetId(['name' => 'Service', 'tax_status' => 1]);
    $this->inactiveTax = DB::table('taxes')->insertGetId(['name' => 'Old levy', 'tax_status' => 0]);
    $this->product = createStoreProduct([
        'name' => 'Recipe book', 'slug' => 'recipe-book', 'digital' => 1, 'added_by' => 'admin', 'category_id' => $this->child->id,
        'unit_price' => 12.5, 'purchase_price' => 8, 'discount' => 1, 'discount_type' => 'amount', 'tags' => 'cooking,recipes',
        'thumbnail_img' => null, 'description' => '<p>Base text</p>', 'meta_title' => 'Recipes', 'meta_description' => 'Family recipes',
    ]);
    ProductTranslation::create(['product_id' => $this->product->id, 'lang' => 'en', 'name' => 'Recipe book', 'description' => '<p>Base text</p>']);
    ProductTranslation::create(['product_id' => $this->product->id, 'lang' => 'ar', 'name' => 'كتاب الوصفات', 'description' => '<p>نص</p>']);
    ProductTax::forceCreate(['product_id' => $this->product->id, 'tax_id' => $this->vat, 'tax' => 21, 'tax_type' => 'percent']);
    ProductTax::forceCreate(['product_id' => $this->product->id, 'tax_id' => $this->inactiveTax, 'tax' => 3, 'tax_type' => 'amount']);
});

function digitalProductFormSnapshot(): array
{
    return collect(['products', 'product_translations', 'product_stocks', 'product_taxes'])
        ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toArray()])->all();
}

/** @return array<string, mixed> */
function digitalProductForm(array $overrides = []): array
{
    return [
        'name' => 'Bread guide',
        'category_id' => test()->root->id,
        'photos' => null,
        'thumbnail_img' => null,
        'tags' => ' bread , baking,bread,, ',
        'description' => '<p><strong>Bake</strong> it</p>',
        'unit_price' => '19.99',
        'purchase_price' => '10.00',
        'discount' => '2.50',
        'discount_type' => 'percent',
        'date_range' => '01-11-2026 08:00:00 to 30-11-2026 20:30:00',
        'tax_id' => [test()->vat, test()->service],
        'tax' => ['21', '1.50'],
        'tax_type' => ['percent', 'amount'],
        'meta_title' => 'Bread',
        'meta_description' => 'How to bake bread',
        'meta_img' => null,
        'file' => null,
        ...$overrides,
    ];
}

function utcTimestamp(string $dateTime): int
{
    return (new DateTimeImmutable($dateTime, new DateTimeZone('UTC')))->getTimestamp();
}

function digitalImage(?int $userId = null, string $type = 'image'): Upload
{
    return Upload::factory()->create(['user_id' => $userId ?? createStoreUser('customer')->id, 'type' => $type, 'file_original_name' => 'File '.Str::random(4), 'file_size' => 64, 'external_link' => 'https://example.com/'.Str::random(6).'.png']);
}

test('the create and edit forms keep their route names, URIs, methods and gates on the Inertia controller', function () {
    expect(route('digitalproducts.create', [], false))->toBe('/temp/admin/digitalproducts/create')
        ->and(route('digitalproducts.store', [], false))->toBe('/temp/admin/digitalproducts')
        ->and(route('digitalproducts.edit', 5, false))->toBe('/temp/admin/digitalproducts/edit/5')
        ->and(route('digitalproducts.update', 5, false))->toBe('/temp/admin/digitalproducts/5')
        ->and(Route::getRoutes()->getByName('digitalproducts.store')->methods())->toBe(['POST'])
        ->and(Route::getRoutes()->getByName('digitalproducts.update')->methods())->toBe(['PUT', 'PATCH']);
    foreach (['create', 'store', 'edit', 'update'] as $action) {
        expect(Route::getRoutes()->getByName('digitalproducts.'.$action)->getActionName())->toBe(DigitalProductController::class.'@'.$action);
    }
    $middleware = collect(DigitalProductController::middleware())->map(fn ($entry) => [$entry->middleware, $entry->only]);
    expect($middleware)->toContain(['permission:add_digital_product', ['create', 'store']], ['permission:edit_digital_product', ['edit', 'update']])
        ->and(class_exists('App\Http\Controllers\DigitalProductController'))->toBeFalse()
        ->and(view()->exists('shop.backend.product.digital_products.create'))->toBeFalse()
        ->and(view()->exists('shop.backend.product.digital_products.edit'))->toBeFalse();
});

test('the create form renders Inertia with the digital category tree and the active taxes', function () {
    actingAsStoreUser(createStoreStaff('add_digital_product'));
    app()->setLocale('ar');
    $this->get(route('digitalproducts.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Product/DigitalProducts/Create')
        ->where('categories', [
            ['id' => $this->root->id, 'name' => 'E-books'],
            ['id' => $this->child->id, 'name' => '-- Recipes'],
            ['id' => $this->grandchild->id, 'name' => '---- Baking'],
        ])
        ->where('taxes', [
            ['id' => $this->vat, 'name' => 'VAT', 'tax' => '0', 'tax_type' => 'amount'],
            ['id' => $this->service, 'name' => 'Service', 'tax' => '0', 'tax_type' => 'amount'],
        ]));
    $this->get(route('digitalproducts.create'), inertiaHeaders())->assertOk()->assertHeader('X-Inertia', 'true');
});

test('the edit form sends the full product, the language texts, the product taxes and the upload previews', function () {
    $staff = createStoreStaff('edit_digital_product');
    actingAsStoreUser($staff);
    $photoA = digitalImage($staff->id);
    $photoB = digitalImage($staff->id);
    $thumb = digitalImage($staff->id);
    $file = digitalImage($staff->id, 'document');
    $foreign = digitalImage();
    $this->product->forceFill([
        'photos' => $photoB->id.','.$photoA->id, 'thumbnail_img' => $thumb->id, 'meta_img' => (string) $foreign->id, 'file_name' => (string) $file->id,
        'discount_start_date' => utcTimestamp('2026-11-01 08:00:00'), 'discount_end_date' => utcTimestamp('2026-11-30 20:30:00'),
    ])->save();
    DB::table('product_translations')->where('product_id', $this->product->id)->where('lang', 'ar')
        ->update(['description' => '<p>نص<script>alert(1)</script></p>']);
    $stored = $this->product->fresh();
    $preview = fn (Upload $upload) => ['id' => $upload->id, 'name' => $upload->file_original_name, 'type' => $upload->type, 'size' => 64, 'url' => $upload->external_link];

    $this->get(route('digitalproducts.edit', ['product' => $this->product, 'lang' => 'ar']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Product/DigitalProducts/Edit')
        ->where('product', [
            'id' => $this->product->id, 'category_id' => $this->child->id, 'photos' => $photoB->id.','.$photoA->id,
            'thumbnail_img' => (string) $thumb->id, 'tags' => 'cooking,recipes', 'unit_price' => (string) $stored->getAttribute('unit_price'),
            'purchase_price' => (string) $stored->getAttribute('purchase_price'), 'discount' => (string) $stored->getAttribute('discount'), 'discount_type' => 'amount', 'date_range' => '01-11-2026 08:00:00 to 30-11-2026 20:30:00',
            'meta_title' => 'Recipes', 'meta_description' => 'Family recipes', 'meta_img' => (string) $foreign->id,
            'slug' => 'recipe-book', 'file' => (string) $file->id,
        ])
        ->where('name', 'كتاب الوصفات')
        ->where('description', '<p>نص</p>')
        ->where('lang', 'ar')
        ->where('languages', [['code' => 'en', 'name' => 'English'], ['code' => 'ar', 'name' => 'Arabic']])
        ->has('categories', 3)
        ->where('taxes', [
            ['id' => $this->vat, 'name' => 'VAT', 'tax' => (string) DB::table('product_taxes')->where('tax_id', $this->vat)->value('tax'), 'tax_type' => 'percent'],
            ['id' => $this->service, 'name' => 'Service', 'tax' => '0', 'tax_type' => 'amount'],
        ])
        ->where('initialFiles', [
            'photos' => [$preview($photoB), $preview($photoA)],
            'thumbnail_img' => [$preview($thumb)],
            'meta_img' => [],
            'file' => [$preview($file)],
        ]));

    $this->get(route('digitalproducts.edit', $this->product))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('lang', 'en')->where('name', 'Recipe book')->where('description', '<p>Base text</p>'));
    $this->get(route('digitalproducts.edit', ['product' => $this->product, 'lang' => 'xx']))->assertSessionHasErrors('lang');
});

test('creating a digital product writes the product, its stock, translation and taxes together and returns to the list', function () {
    $staff = createStoreStaff('add_digital_product');
    actingAsStoreUser($staff);
    $photo = digitalImage($staff->id);
    $file = digitalImage($staff->id, 'document');
    $before = Product::query()->max('id');

    $this->post(route('digitalproducts.store'), digitalProductForm(['photos' => (string) $photo->id, 'thumbnail_img' => $photo->id, 'meta_img' => $photo->id, 'file' => $file->id]))
        ->assertSessionHasNoErrors()->assertRedirect(route('digitalproducts.index'));

    $product = Product::query()->where('id', '>', $before)->sole();
    expect($product->only(['name', 'added_by', 'user_id', 'category_id', 'digital', 'photos', 'thumbnail_img', 'tags', 'description', 'discount_type', 'meta_title', 'meta_description', 'meta_img', 'file_name']))->toEqual([
        'name' => 'Bread guide', 'added_by' => 'admin', 'user_id' => $staff->id, 'category_id' => $this->root->id, 'digital' => 1,
        'photos' => (string) $photo->id, 'thumbnail_img' => $photo->id, 'tags' => 'bread,baking', 'description' => '<p><strong>Bake</strong> it</p>',
        'discount_type' => 'percent', 'meta_title' => 'Bread', 'meta_description' => 'How to bake bread', 'meta_img' => (string) $photo->id, 'file_name' => (string) $file->id,
    ])
        ->and((float) $product->getAttribute('unit_price'))->toBe(19.99)
        ->and((float) $product->getAttribute('purchase_price'))->toBe(10.0)
        ->and((float) $product->getAttribute('discount'))->toBe(2.5)
        ->and($product->getAttribute('discount_start_date'))->toBe(utcTimestamp('2026-11-01 08:00:00'))
        ->and($product->getAttribute('discount_end_date'))->toBe(utcTimestamp('2026-11-30 20:30:00'))
        ->and($product->slug)->toMatch('/^Bread-guide-[1-9]\d{4}$/')
        ->and(DB::table('product_stocks')->where('product_id', $product->id)->get(['variant', 'sku', 'price', 'qty'])->map(fn ($row) => (array) $row)->all())
        ->toEqual([['variant' => '', 'sku' => '', 'price' => 19.99, 'qty' => 0]])
        ->and(DB::table('product_translations')->where('product_id', $product->id)->get(['lang', 'name', 'description'])->map(fn ($row) => (array) $row)->all())
        ->toBe([['lang' => 'en', 'name' => 'Bread guide', 'description' => '<p><strong>Bake</strong> it</p>']])
        ->and(DB::table('product_taxes')->where('product_id', $product->id)->orderBy('id')->get(['tax_id', 'tax', 'tax_type'])->map(fn ($row) => (array) $row)->all())
        ->toEqual([['tax_id' => $this->vat, 'tax' => 21, 'tax_type' => 'percent'], ['tax_id' => $this->service, 'tax' => 1.5, 'tax_type' => 'amount']]);

    $this->post(route('digitalproducts.store'), digitalProductForm(['name' => 'Spiced tea notes!', 'date_range' => null, 'tags' => null, 'tax_id' => null, 'tax' => null, 'tax_type' => null]))
        ->assertSessionHasNoErrors()->assertRedirect(route('digitalproducts.index'));
    $plain = Product::query()->where('name', 'Spiced tea notes!')->sole();
    expect($plain->slug)->toMatch('/^Spiced-tea-notes-\d{5}$/')
        ->and($plain->getAttribute('discount_start_date'))->toBeNull()
        ->and($plain->getAttribute('discount_end_date'))->toBeNull()
        ->and($plain->getAttribute('tags'))->toBe('')
        ->and(ProductTax::query()->where('product_id', $plain->id)->exists())->toBeFalse();
});

test('updating in the default language rewrites the base texts, replaces stock and taxes and returns to the edit form', function () {
    actingAsStoreUser(createStoreStaff('edit_digital_product'));
    $addedBy = $this->product->getAttribute('added_by');
    $this->put(route('digitalproducts.update', $this->product), digitalProductForm(['lang' => 'en', 'slug' => 'Bread-Guide-2026', 'category_id' => $this->grandchild->id]))
        ->assertSessionHasNoErrors()->assertRedirect(route('digitalproducts.edit', ['product' => $this->product->id, 'lang' => 'en']));

    $product = $this->product->fresh();
    expect($product->name)->toBe('Bread guide')
        ->and($product->getAttribute('description'))->toBe('<p><strong>Bake</strong> it</p>')
        ->and($product->slug)->toBe('bread-guide-2026')
        ->and($product->getAttribute('category_id'))->toBe($this->grandchild->id)
        ->and($product->getAttribute('added_by'))->toBe($addedBy)
        ->and($product->getAttribute('tags'))->toBe('bread,baking')
        ->and($product->getAttribute('discount_start_date'))->toBe(utcTimestamp('2026-11-01 08:00:00'))
        ->and(DB::table('product_stocks')->where('product_id', $product->id)->get(['variant', 'sku', 'price', 'qty'])->map(fn ($row) => (array) $row)->all())
        ->toEqual([['variant' => '', 'sku' => '', 'price' => 19.99, 'qty' => 0]])
        ->and(DB::table('product_translations')->where('product_id', $product->id)->orderBy('lang')->pluck('name', 'lang')->all())
        ->toBe(['ar' => 'كتاب الوصفات', 'en' => 'Bread guide'])
        ->and(DB::table('product_taxes')->where('product_id', $product->id)->orderBy('id')->pluck('tax_id')->all())->toBe([$this->vat, $this->service]);

    $this->put(route('digitalproducts.update', $this->product), digitalProductForm(['lang' => 'en', 'slug' => null, 'date_range' => null]))->assertSessionHasNoErrors();
    expect($this->product->fresh()->slug)->toMatch('/^Bread-guide-\d{5}$/')
        ->and($this->product->fresh()->getAttribute('discount_start_date'))->toBeNull()
        ->and($this->product->fresh()->getAttribute('discount_end_date'))->toBeNull();
});

test('updating another language writes only its translation beside the shared fields', function () {
    actingAsStoreUser(createStoreStaff('edit_digital_product'));
    $this->patch(route('digitalproducts.update', $this->product), digitalProductForm(['lang' => 'ar', 'name' => 'دليل الخبز', 'description' => '<p>اخبز</p>', 'slug' => 'recipe-book']))
        ->assertSessionHasNoErrors()->assertRedirect(route('digitalproducts.edit', ['product' => $this->product->id, 'lang' => 'ar']));

    $product = $this->product->fresh();
    expect($product->name)->toBe('Recipe book')
        ->and($product->getAttribute('description'))->toBe('<p>Base text</p>')
        ->and($product->getAttribute('meta_title'))->toBe('Bread')
        ->and((float) $product->getAttribute('unit_price'))->toBe(19.99)
        ->and(DB::table('product_translations')->where('product_id', $product->id)->orderBy('lang')->get(['lang', 'name', 'description'])->map(fn ($row) => (array) $row)->all())
        ->toBe([['lang' => 'ar', 'name' => 'دليل الخبز', 'description' => '<p>اخبز</p>'], ['lang' => 'en', 'name' => 'Recipe book', 'description' => '<p>Base text</p>']]);

    $this->patch(route('digitalproducts.update', $this->product), digitalProductForm(['lang' => 'nl', 'slug' => 'recipe-book']))->assertSessionHasErrors('lang');
    expect(DB::table('product_translations')->where('product_id', $product->id)->count())->toBe(2);
});

test('an update without posted taxes keeps the product tax rows, as before', function () {
    actingAsStoreUser(createStoreStaff('edit_digital_product'));
    $taxes = DB::table('product_taxes')->orderBy('id')->get()->toArray();
    $this->put(route('digitalproducts.update', $this->product), digitalProductForm(['lang' => 'en', 'slug' => 'recipe-book', 'tax_id' => null, 'tax' => null, 'tax_type' => null]))->assertSessionHasNoErrors();
    expect(DB::table('product_taxes')->orderBy('id')->get()->toArray())->toEqual($taxes);
});

test('a failed write rolls back the product, stock, translation and taxes together', function (string $route) {
    actingAsStoreUser(createStoreStaff('add_digital_product', 'edit_digital_product'));
    $before = digitalProductFormSnapshot();
    ProductTranslation::saving(fn () => throw new RuntimeException('Translation failure'));
    $this->withoutExceptionHandling();
    try {
        $request = $route === 'store'
            ? fn () => $this->post(route('digitalproducts.store'), digitalProductForm())
            : fn () => $this->put(route('digitalproducts.update', $this->product), digitalProductForm(['lang' => 'en', 'slug' => 'renamed']));
        expect($request)->toThrow(RuntimeException::class);
        expect(digitalProductFormSnapshot())->toEqual($before);
    } finally {
        ProductTranslation::flushEventListeners();
    }
})->with(['store', 'update']);

test('the forms refuse a physical or missing product with a 404 and write nothing', function () {
    actingAsStoreUser(createStoreStaff('edit_digital_product'));
    $physical = createStoreProduct(['name' => 'Olive oil', 'digital' => 0, 'thumbnail_img' => null]);
    $before = digitalProductFormSnapshot();
    $this->get(route('digitalproducts.edit', $physical))->assertNotFound();
    $this->put(route('digitalproducts.update', $physical), digitalProductForm(['lang' => 'en']))->assertNotFound();
    $this->get(route('digitalproducts.edit', 999999))->assertNotFound();
    $this->put(route('digitalproducts.update', 999999), digitalProductForm(['lang' => 'en']))->assertNotFound();
    expect(digitalProductFormSnapshot())->toEqual($before);
});

test('every form route refuses unauthorized callers and writes nothing', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    $before = digitalProductFormSnapshot();
    assertStoreCallerRefused($this->get(route('digitalproducts.create')), $caller);
    assertStoreCallerRefused($this->post(route('digitalproducts.store'), digitalProductForm()), $caller);
    assertStoreCallerRefused($this->get(route('digitalproducts.edit', $this->product)), $caller);
    assertStoreCallerRefused($this->put(route('digitalproducts.update', $this->product), digitalProductForm(['lang' => 'en'])), $caller);
    expect(digitalProductFormSnapshot())->toEqual($before);
})->with('refused store admin callers');

test('add and edit permissions authorize only their own form', function () {
    $before = digitalProductFormSnapshot();
    actingAsStoreUser(createStoreStaff('add_digital_product', 'show_digital_products', 'product_edit'));
    $this->get(route('digitalproducts.edit', $this->product))->assertForbidden();
    $this->put(route('digitalproducts.update', $this->product), digitalProductForm(['lang' => 'en']))->assertForbidden();
    actingAsStoreUser(createStoreStaff('edit_digital_product', 'show_digital_products'));
    $this->get(route('digitalproducts.create'))->assertForbidden();
    $this->post(route('digitalproducts.store'), digitalProductForm())->assertForbidden();
    expect(digitalProductFormSnapshot())->toEqual($before);
});

test('WMS authentication opens no digital product form and writes nothing', function () {
    $this->actingAs(User::factory()->create(), 'web');
    $before = digitalProductFormSnapshot();
    $this->get(route('digitalproducts.create'))->assertRedirect(route('shop.login'));
    $this->post(route('digitalproducts.store'), digitalProductForm())->assertRedirect(route('shop.login'));
    $this->get(route('digitalproducts.edit', $this->product))->assertRedirect(route('shop.login'));
    $this->put(route('digitalproducts.update', $this->product), digitalProductForm(['lang' => 'en']))->assertRedirect(route('shop.login'));
    expect(digitalProductFormSnapshot())->toEqual($before);
});

test('the requests validate every field against the schema widths and the form options', function (array $overrides, string $field) {
    actingAsStoreUser(createStoreStaff('add_digital_product', 'edit_digital_product'));
    $before = digitalProductFormSnapshot();
    $this->post(route('digitalproducts.store'), digitalProductForm($overrides))->assertSessionHasErrors($field);
    $this->put(route('digitalproducts.update', $this->product), digitalProductForm(['lang' => 'en', ...$overrides]))->assertSessionHasErrors($field);
    expect(digitalProductFormSnapshot())->toEqual($before);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'name over 200' => [['name' => str_repeat('a', 201)], 'name'],
    'missing category' => [['category_id' => null], 'category_id'],
    'physical category' => [fn () => ['category_id' => test()->physical->id], 'category_id'],
    'unknown category' => [['category_id' => 999999], 'category_id'],
    'photos not ids' => [['photos' => '1,a'], 'photos'],
    'photos over 2000' => [['photos' => str_repeat('1', 2001)], 'photos'],
    'photo missing' => [['photos' => '999999'], 'photos'],
    'thumbnail not an id' => [['thumbnail_img' => 'x'], 'thumbnail_img'],
    'tags over 500' => [['tags' => str_repeat('t', 501)], 'tags'],
    'negative unit price' => [['unit_price' => '-1'], 'unit_price'],
    'unit price over the decimal' => [['unit_price' => '9999999999999999999'], 'unit_price'],
    'unit price with three decimals' => [['unit_price' => '1.234'], 'unit_price'],
    'missing purchase price' => [['purchase_price' => null], 'purchase_price'],
    'missing discount' => [['discount' => null], 'discount'],
    'unknown discount type' => [['discount_type' => 'free'], 'discount_type'],
    'malformed date range' => [['date_range' => '2026-11-01 to 2026-11-30'], 'date_range'],
    'impossible date' => [['date_range' => '31-02-2026 08:00:00 to 30-11-2026 20:30:00'], 'date_range'],
    'range ending first' => [['date_range' => '30-11-2026 08:00:00 to 01-11-2026 20:30:00'], 'date_range'],
    'inactive tax' => [fn () => ['tax_id' => [test()->inactiveTax], 'tax' => ['1'], 'tax_type' => ['amount']], 'tax_id.0'],
    'repeated tax' => [fn () => ['tax_id' => [test()->vat, test()->vat], 'tax' => ['1', '1'], 'tax_type' => ['amount', 'amount']], 'tax_id.1'],
    'tax amounts not matching' => [fn () => ['tax_id' => [test()->vat, test()->service], 'tax' => ['1'], 'tax_type' => ['amount', 'amount']], 'tax'],
    'negative tax' => [fn () => ['tax' => ['-1', '0']], 'tax.0'],
    'unknown tax type' => [fn () => ['tax_type' => ['percent', 'fixed']], 'tax_type.1'],
    'meta image not an id' => [['meta_img' => 'x'], 'meta_img'],
    'missing file' => [['file' => 999999], 'file'],
]);

test('the update request requires a known language', function () {
    actingAsStoreUser(createStoreStaff('edit_digital_product'));
    $before = digitalProductFormSnapshot();
    $this->put(route('digitalproducts.update', $this->product), digitalProductForm())->assertSessionHasErrors('lang');
    $this->put(route('digitalproducts.update', $this->product), digitalProductForm(['lang' => str_repeat('e', 11)]))->assertSessionHasErrors('lang');
    expect(digitalProductFormSnapshot())->toEqual($before);
});

test('the WAF still refuses a script payload in the description before anything is written', function () {
    actingAsStoreUser(createStoreStaff('add_digital_product', 'edit_digital_product'));
    $before = digitalProductFormSnapshot();
    $this->post(route('digitalproducts.store'), digitalProductForm(['description' => '<p>x</p><script>alert(1)</script>']))->assertForbidden();
    $this->put(route('digitalproducts.update', $this->product), digitalProductForm(['lang' => 'en', 'description' => '<img src="x" onerror="alert(1)">']))->assertForbidden();
    expect(digitalProductFormSnapshot())->toEqual($before);
});

test('description writes strip scripts, handlers and unsafe URLs', function (string $hostile, string $removed) {
    $this->withoutMiddleware(BlockMaliciousRequests::class);
    actingAsStoreUser(createStoreStaff('add_digital_product', 'edit_digital_product'));
    $this->post(route('digitalproducts.store'), digitalProductForm(['name' => 'Hostile guide', 'description' => '<p>Kept</p>'.$hostile]))->assertSessionHasNoErrors()->assertRedirect(route('digitalproducts.index'));
    $this->put(route('digitalproducts.update', $this->product), digitalProductForm(['lang' => 'ar', 'slug' => 'recipe-book', 'description' => '<p>Kept</p>'.$hostile]))->assertSessionHasNoErrors();
    $created = Product::query()->where('name', 'Hostile guide')->sole();
    foreach ([
        $created->getAttribute('description'),
        DB::table('product_translations')->where('product_id', $created->id)->value('description'),
        DB::table('product_translations')->where('product_id', $this->product->id)->where('lang', 'ar')->value('description'),
    ] as $stored) {
        expect($stored)->toStartWith('<p>Kept</p>')->not->toContain($removed)->and(clean_html($stored))->toBe($stored);
    }
})->with([
    'script element' => ['<script>alert(1)</script>', 'alert'],
    'image error handler' => ['<img src="x" onerror="alert(1)">', 'onerror'],
    'click handler' => ['<p onclick="alert(1)">Click</p>', 'onclick'],
    'javascript link' => ['<a href="javascript:alert(1)">Link</a>', 'javascript'],
    'svg script' => ['<svg><script>alert(1)</script></svg>', 'svg'],
    'iframe' => ['<iframe src="https://example.com"></iframe>', 'iframe'],
    'style url' => ['<p style="background-image: url(https://example.com/x.png)">Styled</p>', 'url('],
]);

test('the description is cleaned before it is validated and stored, and on the edit form', function () {
    $this->withoutMiddleware(BlockMaliciousRequests::class);
    actingAsStoreUser(createStoreStaff('add_digital_product', 'edit_digital_product'));
    $hostile = '<p onclick="alert(1)">Safe <img src="x" onerror="alert(2)"><a href="javascript:alert(3)">link</a></p><script>alert(4)</script><iframe src="https://example.com"></iframe>';
    $this->post(route('digitalproducts.store'), digitalProductForm(['name' => 'Hostile guide', 'description' => $hostile]))->assertSessionHasNoErrors();
    $product = Product::query()->where('name', 'Hostile guide')->sole();
    $stored = [$product->getAttribute('description'), DB::table('product_translations')->where('product_id', $product->id)->value('description')];
    foreach ($stored as $html) {
        expect($html)->toBe(clean_html($hostile))
            ->not->toContain('<script')->not->toContain('onclick')->not->toContain('onerror')->not->toContain('javascript:')->not->toContain('<iframe');
    }

    $this->put(route('digitalproducts.update', $this->product), digitalProductForm(['lang' => 'ar', 'slug' => 'recipe-book', 'description' => $hostile]))->assertSessionHasNoErrors();
    expect(DB::table('product_translations')->where('product_id', $this->product->id)->where('lang', 'ar')->value('description'))->toBe(clean_html($hostile));

    DB::table('product_translations')->where('product_id', $this->product->id)->where('lang', 'en')->update(['description' => $hostile]);
    $this->get(route('digitalproducts.edit', ['product' => $this->product, 'lang' => 'en']))
        ->assertInertia(fn (Assert $page) => $page->where('description', clean_html($hostile)))
        ->assertDontSee('<script>alert(4)</script>', false);
});

test('legacy editor markup round-trips through the cleaner unchanged', function () {
    actingAsStoreUser(createStoreStaff('edit_digital_product'));
    $legacy = '<p style="text-align: center;"><b>Bold</b> <i>italic</i> <u>under</u></p><ul><li>One</li><li>Two</li></ul><ol><li>First</li></ol><table class="table table-bordered"><tbody><tr><td>Cell</td></tr></tbody></table><p><a href="https://example.com" target="_blank">Shop</a></p>';
    expect(clean_html(clean_html($legacy)))->toBe(clean_html($legacy));
    $this->put(route('digitalproducts.update', $this->product), digitalProductForm(['lang' => 'en', 'slug' => 'recipe-book', 'description' => clean_html($legacy)]))->assertSessionHasNoErrors();
    expect($this->product->fresh()->getAttribute('description'))->toBe(clean_html($legacy))
        ->and(clean_html($legacy))->toContain('<b>Bold</b>', '<i>italic</i>', '<u>under</u>', '<li>One</li>', 'text-align: center', '<td>Cell</td>');
});

test('uploads follow the ownership and library rules, and stored foreign references round-trip without previews', function () {
    $staff = createStoreStaff('add_digital_product', 'edit_digital_product');
    actingAsStoreUser($staff);
    $own = digitalImage($staff->id);
    $ownFile = digitalImage($staff->id, 'document');
    $foreign = digitalImage();
    $foreignFile = digitalImage(null, 'document');
    $before = digitalProductFormSnapshot();

    foreach ([['photos' => $own->id.','.$foreign->id], ['thumbnail_img' => $foreign->id], ['meta_img' => $foreign->id], ['file' => $foreignFile->id], ['thumbnail_img' => $ownFile->id], ['photos' => (string) $ownFile->id], ['photos' => $own->id.','.$own->id]] as $overrides) {
        $this->post(route('digitalproducts.store'), digitalProductForm($overrides))->assertSessionHasErrors(array_key_first($overrides));
    }
    expect(digitalProductFormSnapshot())->toEqual($before);

    $this->post(route('digitalproducts.store'), digitalProductForm(['name' => 'Own files', 'photos' => (string) $own->id, 'thumbnail_img' => $own->id, 'file' => $ownFile->id]))->assertSessionHasNoErrors();
    expect(Product::query()->where('name', 'Own files')->sole()->getAttribute('file_name'))->toBe((string) $ownFile->id);

    $this->product->forceFill(['photos' => $foreign->id.','.$own->id, 'thumbnail_img' => $foreign->id, 'meta_img' => (string) $foreign->id, 'file_name' => (string) $foreignFile->id])->save();
    $this->get(route('digitalproducts.edit', $this->product))->assertInertia(fn (Assert $page) => $page
        ->where('product.photos', $foreign->id.','.$own->id)->where('product.file', (string) $foreignFile->id)
        ->where('initialFiles.photos', [['id' => $own->id, 'name' => $own->file_original_name, 'type' => 'image', 'size' => 64, 'url' => $own->external_link]])
        ->where('initialFiles.thumbnail_img', [])->where('initialFiles.meta_img', [])->where('initialFiles.file', []));
    $this->put(route('digitalproducts.update', $this->product), digitalProductForm([
        'lang' => 'en', 'slug' => 'recipe-book', 'photos' => $foreign->id.','.$own->id, 'thumbnail_img' => $foreign->id, 'meta_img' => $foreign->id, 'file' => $foreignFile->id,
    ]))->assertSessionHasNoErrors();
    expect($this->product->fresh()->only(['photos', 'thumbnail_img', 'meta_img', 'file_name']))
        ->toEqual(['photos' => $foreign->id.','.$own->id, 'thumbnail_img' => $foreign->id, 'meta_img' => (string) $foreign->id, 'file_name' => (string) $foreignFile->id]);
    $other = digitalImage();
    $this->put(route('digitalproducts.update', $this->product), digitalProductForm(['lang' => 'en', 'slug' => 'recipe-book', 'photos' => $foreign->id.','.$other->id]))->assertSessionHasErrors('photos');
    $this->put(route('digitalproducts.update', $this->product), digitalProductForm(['lang' => 'en', 'slug' => 'recipe-book', 'file' => digitalImage(null, 'document')->id]))->assertSessionHasErrors('file');

    actingAsStoreUser(createStoreStaff('add_digital_product', 'view_uploads'));
    $this->post(route('digitalproducts.store'), digitalProductForm(['name' => 'Library files', 'photos' => $foreign->id.','.$other->id, 'thumbnail_img' => $foreign->id, 'file' => $foreignFile->id]))->assertSessionHasNoErrors();
    expect(Product::query()->where('name', 'Library files')->sole()->getAttribute('photos'))->toBe($foreign->id.','.$other->id);
});

test('a created product keeps the storefront page, the encrypted list download and the shared product edit redirect', function () {
    $this->withoutMiddleware(BlockMaliciousRequests::class);
    $staff = createStoreStaff('add_digital_product', 'download_digital_product', 'product_edit');
    actingAsStoreUser($staff);
    $fileName = 'uploads/tests/'.Str::random(8).'.txt';
    File::ensureDirectoryExists(dirname(public_path($fileName)));
    File::put(public_path($fileName), 'the bread guide');
    $upload = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'document', 'file_name' => $fileName, 'external_link' => null]);
    try {
        $this->post(route('digitalproducts.store'), digitalProductForm(['file' => $upload->id, 'description' => '<p>Knead <script>alert(1)</script>well</p>']))->assertSessionHasNoErrors();
        $product = Product::query()->where('name', 'Bread guide')->sole();
        $response = $this->get(route('digitalproducts.download', encrypt($product->id)))->assertOk()->assertDownload(basename($fileName));
        expect($response->baseResponse->getFile()->getContent())->toBe('the bread guide');
    } finally {
        File::delete(public_path($fileName));
    }
    $this->get(route('product', $product->slug))->assertOk()->assertSee('Bread guide')->assertSee('Knead', false)->assertDontSee('alert(1)', false);
    $this->get(route('products.admin.edit', ['id' => $product->id, 'lang' => 'ar']))
        ->assertRedirect(route('digitalproducts.edit', ['product' => $product->id, 'lang' => 'ar']));
    $this->get(route('products.seller.edit', ['id' => $product->id]))
        ->assertRedirect(route('digitalproducts.edit', ['product' => $product->id]));
});
