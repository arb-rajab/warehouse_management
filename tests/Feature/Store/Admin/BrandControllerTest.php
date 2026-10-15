<?php

use App\Models\Brand;
use App\Models\BrandTranslation;
use App\Models\Language;
use App\Models\Pallet;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    seedStoreCurrency();
    config(['app.default_language' => 'en']);
    Language::forceCreate(['code' => 'en', 'name' => 'English', 'rtl' => 0]);
    Language::forceCreate(['code' => 'ar', 'name' => 'Arabic', 'rtl' => 1]);
    $this->brand = Brand::forceCreate(['name' => 'Dates', 'slug' => 'dates', 'meta_title' => 'Old title', 'meta_description' => 'Old description']);
    BrandTranslation::create(['brand_id' => $this->brand->id, 'lang' => 'en', 'name' => 'Dates']);
    $this->payload = ['name' => 'Updated', 'logo' => null, 'meta_title' => 'SEO title', 'meta_description' => 'SEO description', 'slug' => 'NEW-Slug', 'lang' => 'en'];
});

function brandAdminSnapshot(): array
{
    return collect(['brands', 'brand_translations', 'products'])->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toArray()])->all();
}

foreach (['index', 'edit'] as $action) {
    test("brand $action renders the selected Inertia screen", function () use ($action) {
        actingAsStoreUser(createStoreStaff('view_all_brands', 'edit_brand'));
        $this->get(route('brands.'.$action, $action === 'edit' ? [$this->brand] : []))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Product/Brands/'.ucfirst($action)));
    });
    test("brand $action refuses unauthorized callers", function (string $caller) use ($action) {
        actingAsRefusedStoreCaller($caller);
        assertStoreCallerRefused($this->get(route('brands.'.$action, $action === 'edit' ? [$this->brand] : [])), $caller);
    })->with('refused store admin callers');
}

test('brand list exposes the full narrow row and keeps name ordering, base-name search and real pagination', function () {
    actingAsStoreUser(createStoreStaff('view_all_brands'));
    $this->get(route('brands.index'))->assertInertia(fn (Assert $page) => $page
        ->has('brands.data', 1)
        ->has('brands.data.0', fn (Assert $row) => $row->where('id', $this->brand->id)->where('name', 'Dates')->where('logo_url', null))
        ->where('filters', ['search' => null, 'per_page' => 20])->where('defaultLanguage', 'en'));
    foreach (range(1, 22) as $i) {
        Brand::forceCreate(['name' => sprintf('Noise %02d', $i), 'slug' => 'noise-'.$i]);
    }
    assertPagesWithoutOverlap(fn ($page) => $this->get(route('brands.index', ['page' => $page])), fn ($response) => array_column($response->inertiaProps('brands.data'), 'id'), 23, 20);
    $this->get(route('brands.index', ['per_page' => 10]))->assertInertia(fn (Assert $page) => $page->has('brands.data', 10)
        ->where('brands.data.0.id', $this->brand->id)->where('brands.data.1.name', 'Noise 01')->where('brands.data.9.name', 'Noise 09')->where('filters.per_page', 10));
    $this->get(route('brands.index', ['search' => 'Date']))->assertInertia(fn (Assert $page) => $page->has('brands.data', 1)->where('brands.data.0.id', $this->brand->id)->where('filters.search', 'Date'));
    foreach ([11, 0, 1000] as $size) {
        $this->getJson(route('brands.index', ['per_page' => $size]))->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }
    $this->getJson(route('brands.index', ['search' => ['bad']]))->assertUnprocessable()->assertJsonValidationErrors('search');
    $this->getJson(route('brands.index', ['search' => str_repeat('s', 256)]))->assertUnprocessable()->assertJsonValidationErrors('search');
});

test('brand list localizes names and resolves logos like the legacy uploaded asset helper', function () {
    actingAsStoreUser(createStoreStaff('view_all_brands'));
    $logo = Upload::factory()->create(['type' => 'image', 'external_link' => 'https://example.com/brand.png', 'file_name' => null]);
    $this->brand->forceFill(['logo' => $logo->id])->save();
    $missing = Brand::forceCreate(['name' => 'Missing logo', 'logo' => '999999']);
    BrandTranslation::create(['brand_id' => $this->brand->id, 'lang' => 'ar', 'name' => 'تمور']);
    $this->withSession(['locale' => 'ar'])->get(route('brands.index'))->assertInertia(fn (Assert $page) => $page
        ->where('brands.data.0', ['id' => $this->brand->id, 'name' => 'تمور', 'logo_url' => 'https://example.com/brand.png'])
        ->where('brands.data.1', ['id' => $missing->id, 'name' => 'Missing logo', 'logo_url' => static_asset('assets/img/placeholder.jpg')]));
    $this->get(route('brands.index', ['search' => 'تمور']))->assertInertia(fn (Assert $page) => $page->has('brands.data', 0));
});

test('brand edit exposes the translated name, SEO fields, slug and language selector', function () {
    actingAsStoreUser(createStoreStaff('edit_brand'));
    BrandTranslation::create(['brand_id' => $this->brand->id, 'lang' => 'ar', 'name' => 'تمور']);
    $this->get(route('brands.edit', [$this->brand, 'lang' => 'ar']))->assertInertia(fn (Assert $page) => $page
        ->where('name', 'تمور')->where('lang', 'ar')->where('languages', [['code' => 'en', 'name' => 'English'], ['code' => 'ar', 'name' => 'Arabic']])
        ->where('initialFiles', ['logo' => []])
        ->where('brand', ['id' => $this->brand->id, 'logo' => null, 'slug' => 'dates', 'meta_title' => 'Old title', 'meta_description' => 'Old description']));
    $this->get(route('brands.edit', $this->brand))->assertInertia(fn (Assert $page) => $page->where('name', 'Dates')->where('lang', 'en'));
});

test('brand creation writes the base row, default translation, logo and SEO fields and ignores the absent order level', function () {
    expect(Schema::hasColumn('brands', 'order_level'))->toBeFalse();
    $staff = createStoreStaff('add_brand');
    actingAsStoreUser($staff);
    $image = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image']);
    $this->post(route('brands.store'), [...$this->payload, 'logo' => $image->id, 'slug' => 'My New Slug', 'order_level' => 5, 'lang' => 'ar'])->assertRedirect(route('brands.index'));
    $new = Brand::query()->orderByDesc('id')->firstOrFail();
    expect($new->name)->toBe('Updated')->and($new->slug)->toBe('My-New-Slug')->and($new->logo)->toBe((string) $image->id)
        ->and($new->meta_title)->toBe('SEO title')->and($new->meta_description)->toBe('SEO description')->and($new->top)->toBe(0);
    $this->assertDatabaseHas('brand_translations', ['brand_id' => $new->id, 'lang' => 'en', 'name' => 'Updated']);
    $this->assertDatabaseMissing('brand_translations', ['brand_id' => $new->id, 'lang' => 'ar']);
});

test('brand creation keeps the existing generated slug shape and blank SEO defaults', function () {
    actingAsStoreUser(createStoreStaff('add_brand'));
    $this->post(route('brands.store'), ['name' => 'Fresh Fruit & Co'])->assertRedirect(route('brands.index'));
    $new = Brand::query()->orderByDesc('id')->firstOrFail();
    expect($new->slug)->toMatch('/^Fresh-Fruit--Co-[A-Za-z0-9]{5}$/')->and($new->logo)->toBeNull()
        ->and($new->meta_title)->toBeNull()->and($new->meta_description)->toBeNull();
});

test('brand default-language updates change the base name, lowercase the slug and return to the edit screen', function () {
    actingAsStoreUser(createStoreStaff('edit_brand'));
    $this->put(route('brands.update', $this->brand), $this->payload)->assertRedirect(route('brands.edit', [$this->brand, 'lang' => 'en']));
    $brand = $this->brand->fresh();
    expect($brand->name)->toBe('Updated')->and($brand->slug)->toBe('new-slug')->and($brand->meta_title)->toBe('SEO title')->and($brand->meta_description)->toBe('SEO description');
    $this->assertDatabaseHas('brand_translations', ['brand_id' => $this->brand->id, 'lang' => 'en', 'name' => 'Updated']);
    expect(BrandTranslation::query()->where('brand_id', $this->brand->id)->count())->toBe(1);
});

test('brand translation updates change only that translation plus the shared fields, and a blank slug is regenerated', function () {
    actingAsStoreUser(createStoreStaff('edit_brand'));
    $this->patch(route('brands.update', $this->brand), [...$this->payload, 'lang' => 'ar', 'name' => 'تمور', 'slug' => null, 'meta_title' => null])
        ->assertRedirect(route('brands.edit', [$this->brand, 'lang' => 'ar']));
    $brand = $this->brand->fresh();
    expect($brand->name)->toBe('Dates')->and($brand->slug)->toMatch('/^-[A-Za-z0-9]{5}$/')->and($brand->meta_title)->toBeNull()->and($brand->meta_description)->toBe('SEO description');
    $this->assertDatabaseHas('brand_translations', ['brand_id' => $this->brand->id, 'lang' => 'ar', 'name' => 'تمور']);
    $this->assertDatabaseHas('brand_translations', ['brand_id' => $this->brand->id, 'lang' => 'en', 'name' => 'Dates']);
});

foreach (['store', 'update'] as $action) {
    test("brand $action refuses invalid data before any table changes", function (string $field, mixed $value) use ($action) {
        actingAsStoreUser(createStoreStaff('add_brand', 'edit_brand'));
        $before = brandAdminSnapshot();
        $payload = $this->payload;
        data_set($payload, $field, $value);
        $response = $action === 'store' ? $this->postJson(route('brands.store'), $payload) : $this->putJson(route('brands.update', $this->brand), $payload);
        $response->assertUnprocessable()->assertJsonValidationErrors($field);
        expect(brandAdminSnapshot())->toEqual($before);
    })->with([
        ['name', ''], ['name', str_repeat('n', 51)], ['name', ['bad']], ['logo', 999999], ['logo', '1,2'], ['logo', ['bad']],
        ['meta_title', str_repeat('s', 256)], ['meta_description', str_repeat('s', 65536)], ['slug', str_repeat('s', 256)], ['slug', ['bad']],
    ]);
}

test('brand edit and update reject unknown and malformed languages without mutation', function () {
    actingAsStoreUser(createStoreStaff('edit_brand'));
    $before = brandAdminSnapshot();
    foreach (['xx', ['en']] as $lang) {
        $this->getJson(route('brands.edit', [$this->brand, 'lang' => $lang]))->assertUnprocessable()->assertJsonValidationErrors('lang');
        $this->putJson(route('brands.update', $this->brand), [...$this->payload, 'lang' => $lang])->assertUnprocessable()->assertJsonValidationErrors('lang');
    }
    $this->putJson(route('brands.update', $this->brand), [...$this->payload, 'lang' => null])->assertUnprocessable()->assertJsonValidationErrors('lang');
    expect(brandAdminSnapshot())->toEqual($before);
});

foreach (['store', 'update', 'destroy'] as $action) {
    test("brand $action refuses unauthorized callers without changes", function (string $caller) use ($action) {
        createStoreProduct(['brand_id' => $this->brand->id]);
        $before = brandAdminSnapshot();
        actingAsRefusedStoreCaller($caller);
        $response = match ($action) {
            'store' => $this->post(route('brands.store'), $this->payload),
            'update' => $this->put(route('brands.update', $this->brand), $this->payload),
            'destroy' => $this->delete(route('brands.destroy', $this->brand)),
        };
        assertStoreCallerRefused($response, $caller);
        expect(brandAdminSnapshot())->toEqual($before);
    })->with('refused store admin callers');
}

test('each brand permission authorizes only its own actions', function () {
    createStoreProduct(['brand_id' => $this->brand->id]);
    $before = brandAdminSnapshot();
    actingAsStoreUser(createStoreStaff('view_all_brands'));
    $this->get(route('brands.edit', $this->brand))->assertForbidden();
    $this->post(route('brands.store'), $this->payload)->assertForbidden();
    $this->put(route('brands.update', $this->brand), $this->payload)->assertForbidden();
    $this->delete(route('brands.destroy', $this->brand))->assertForbidden();
    actingAsStoreUser(createStoreStaff('add_brand'));
    $this->get(route('brands.index'))->assertForbidden();
    $this->put(route('brands.update', $this->brand), $this->payload)->assertForbidden();
    actingAsStoreUser(createStoreStaff('edit_brand'));
    $this->post(route('brands.store'), $this->payload)->assertForbidden();
    $this->delete(route('brands.destroy', $this->brand))->assertForbidden();
    actingAsStoreUser(createStoreStaff('delete_brand'));
    $this->put(route('brands.update', $this->brand), $this->payload)->assertForbidden();
    expect(brandAdminSnapshot())->toEqual($before);
});

foreach (['edit', 'update', 'destroy'] as $action) {
    test("brand $action returns 404 for a missing bound model", function () use ($action) {
        actingAsStoreUser(createStoreStaff('edit_brand', 'delete_brand'));
        match ($action) {
            'edit' => $this->get(route('brands.edit', 999999))->assertNotFound(),
            'update' => $this->put(route('brands.update', 999999), $this->payload)->assertNotFound(),
            'destroy' => $this->delete(route('brands.destroy', 999999))->assertNotFound(),
        };
    });
}

test('brand deletion keeps deleting that brand\'s products and translations, and nothing else, preserving the list query', function () {
    actingAsStoreUser(createStoreStaff('delete_brand'));
    $other = Brand::forceCreate(['name' => 'Other', 'slug' => 'other']);
    BrandTranslation::create(['brand_id' => $other->id, 'lang' => 'en', 'name' => 'Other']);
    BrandTranslation::create(['brand_id' => $this->brand->id, 'lang' => 'ar', 'name' => 'تمور']);
    $owned = createStoreProduct(['brand_id' => $this->brand->id]);
    $otherProduct = createStoreProduct(['brand_id' => $other->id]);
    $unbranded = createStoreProduct(['brand_id' => null]);
    $this->delete(route('brands.destroy', [$this->brand, 'page' => 2, 'per_page' => 10, 'search' => 'Dat']))
        ->assertRedirect(route('brands.index', ['page' => 2, 'per_page' => 10, 'search' => 'Dat']));
    $this->assertDatabaseMissing('brands', ['id' => $this->brand->id]);
    $this->assertDatabaseMissing('brand_translations', ['brand_id' => $this->brand->id]);
    $this->assertDatabaseMissing('products', ['id' => $owned->id]);
    $this->assertDatabaseHas('brands', ['id' => $other->id]);
    $this->assertDatabaseHas('brand_translations', ['brand_id' => $other->id, 'name' => 'Other']);
    $this->assertDatabaseHas('products', ['id' => $otherProduct->id, 'brand_id' => $other->id]);
    $this->assertDatabaseHas('products', ['id' => $unbranded->id]);
});

test('brand deletion changes nothing when a warehouse pallet still references one of its products', function () {
    actingAsStoreUser(createStoreStaff('delete_brand'));
    $stocked = createStoreProduct(['brand_id' => $this->brand->id]);
    createStoreProduct(['brand_id' => $this->brand->id]);
    Pallet::factory()->create(['product_id' => $stocked->id]);
    $before = brandAdminSnapshot();
    $this->withoutExceptionHandling();
    expect(fn () => $this->delete(route('brands.destroy', $this->brand)))->toThrow(QueryException::class);
    expect(brandAdminSnapshot())->toEqual($before);
});

foreach (['store', 'update', 'destroy'] as $action) {
    test("brand $action rolls back every table on persistence failure", function () use ($action) {
        actingAsStoreUser(createStoreStaff('add_brand', 'edit_brand', 'delete_brand'));
        BrandTranslation::create(['brand_id' => $this->brand->id, 'lang' => 'ar', 'name' => 'تمور']);
        createStoreProduct(['brand_id' => $this->brand->id]);
        $before = brandAdminSnapshot();
        if ($action === 'destroy') {
            Brand::deleting(fn () => throw new RuntimeException('Delete failure'));
        } else {
            BrandTranslation::saving(fn () => throw new RuntimeException('Translation failure'));
        }
        $this->withoutExceptionHandling();
        try {
            expect(fn () => match ($action) {
                'store' => $this->post(route('brands.store'), $this->payload),
                'update' => $this->put(route('brands.update', $this->brand), $this->payload),
                'destroy' => $this->delete(route('brands.destroy', $this->brand)),
            })->toThrow(RuntimeException::class);
            expect(brandAdminSnapshot())->toEqual($before);
        } finally {
            Brand::flushEventListeners();
            BrandTranslation::flushEventListeners();
        }
    });
}

test('brand logos permit own and library images while a retained foreign logo grants no replacement or preview access', function () {
    $staff = createStoreStaff('edit_brand', 'add_brand');
    actingAsStoreUser($staff);
    $own = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image']);
    $foreign = Upload::factory()->create(['user_id' => createStoreUser('customer')->id, 'type' => 'image']);
    $file = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'document']);
    foreach ([$foreign, $file] as $upload) {
        $before = brandAdminSnapshot();
        $this->putJson(route('brands.update', $this->brand), [...$this->payload, 'logo' => $upload->id])->assertUnprocessable()->assertJsonValidationErrors('logo');
        $this->postJson(route('brands.store'), [...$this->payload, 'logo' => $upload->id])->assertUnprocessable()->assertJsonValidationErrors('logo');
        expect(brandAdminSnapshot())->toEqual($before);
    }
    $this->put(route('brands.update', $this->brand), [...$this->payload, 'logo' => $own->id])->assertRedirect();
    expect($this->brand->fresh()->logo)->toBe((string) $own->id);
    $this->brand->forceFill(['logo' => $foreign->id])->save();
    $this->put(route('brands.update', $this->brand), [...$this->payload, 'logo' => $foreign->id])->assertRedirect();
    expect($this->brand->fresh()->logo)->toBe((string) $foreign->id);
    $this->get(route('brands.edit', $this->brand))->assertInertia(fn (Assert $page) => $page->where('initialFiles.logo', []));
    $this->put(route('brands.update', $this->brand), $this->payload)->assertRedirect();
    expect($this->brand->fresh()->logo)->toBeNull();
    actingAsStoreUser(createStoreStaff('add_brand', 'view_uploads'));
    $this->post(route('brands.store'), [...$this->payload, 'logo' => $foreign->id])->assertRedirect(route('brands.index'));
});

test('brand edit previews only a permitted logo through the canonical resource', function () {
    $staff = createStoreStaff('edit_brand');
    $image = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image', 'file_original_name' => 'Own brand logo', 'file_size' => 128, 'external_link' => 'https://example.com/brand.png']);
    $this->brand->forceFill(['logo' => $image->id])->save();
    actingAsStoreUser($staff);
    $expected = ['id' => $image->id, 'name' => 'Own brand logo', 'type' => 'image', 'size' => 128, 'url' => 'https://example.com/brand.png'];
    $this->get(route('brands.edit', $this->brand))->assertInertia(fn (Assert $page) => $page->where('initialFiles', ['logo' => [$expected]])->where('brand.logo', (string) $image->id));
    actingAsStoreUser(createStoreStaff('edit_brand', 'view_uploads'));
    $this->get(route('brands.edit', $this->brand))->assertInertia(fn (Assert $page) => $page->where('initialFiles.logo', [$expected]));
});

test('WMS authentication grants no brand action and no store writes', function () {
    $this->actingAs(User::factory()->create(), 'web');
    $before = brandAdminSnapshot();
    foreach ([['GET', 'index'], ['GET', 'edit'], ['POST', 'store'], ['PUT', 'update'], ['DELETE', 'destroy']] as [$method, $action]) {
        $this->call($method, route('brands.'.$action, in_array($action, ['edit', 'update', 'destroy']) ? [$this->brand] : []), $this->payload)->assertRedirect(route('shop.login'));
    }
    expect(brandAdminSnapshot())->toEqual($before);
});

test('brand migration retains public and mobile contracts and removes only the empty create and show routes', function () {
    expect(Route::has('brands.create'))->toBeFalse()->and(Route::has('brands.show'))->toBeFalse()
        ->and(route('brands.edit', 5, false))->toBe('/temp/admin/brands/edit/5')
        ->and(route('brands.destroy', 5, false))->toBe('/temp/admin/brands/destroy/5');
    $this->get('/temp/admin/brands/'.$this->brand->id)->assertMethodNotAllowed();
    $this->getJson('/temp/api/v2/brands')->assertOk()->assertJsonPath('success', true)->assertJsonPath('status', 200)
        ->assertJsonPath('data.0', ['id' => $this->brand->id, 'name' => 'Dates', 'logo' => uploaded_asset(null), 'links' => ['products' => route('api.products.brand', $this->brand->id)]]);
    $this->get('/temp/brands')->assertOk()->assertSee('Dates');
    expect(view()->exists('shop.backend.downloads.brand'))->toBeTrue()
        ->and(view()->exists('shop.backend.product.brands.index'))->toBeFalse()
        ->and(view()->exists('shop.backend.product.brands.edit'))->toBeFalse();
});
