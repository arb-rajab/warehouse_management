<?php

use App\Models\Attribute;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Language;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    seedStoreCurrency();
    config(['app.default_language' => 'en']);
    Language::forceCreate(['code' => 'en', 'name' => 'English', 'rtl' => 0]);
    Language::forceCreate(['code' => 'ar', 'name' => 'Arabic', 'rtl' => 1]);
    $this->category = Category::forceCreate(['name' => 'Food', 'parent_id' => 0, 'level' => 0, 'order_level' => 3, 'slug' => 'food', 'digital' => 0, 'commision_rate' => 2.5]);
    CategoryTranslation::create(['category_id' => $this->category->id, 'lang' => 'en', 'name' => 'Food']);
    $this->attribute = Attribute::forceCreate(['name' => 'Color']);
    $this->category->attributes()->attach($this->attribute);
    $this->payload = ['name' => 'Updated', 'parent_id' => 0, 'order_level' => 8, 'digital' => 1, 'banner' => null, 'icon' => null, 'cover_image' => null, 'meta_title' => 'SEO title', 'meta_description' => 'SEO description', 'slug' => 'NEW-Slug', 'commision_rate' => '9.25', 'filtering_attributes' => [$this->attribute->id], 'lang' => 'en'];
});

function categoryAdminSnapshot(): array
{
    return collect(['categories', 'category_translations', 'attribute_category', 'products'])->mapWithKeys(fn ($table) => [$table => DB::table($table)->get()->toArray()])->all();
}

foreach (['index', 'create', 'edit'] as $action) {
    test("category $action renders the selected Inertia screen", function () use ($action) {
        actingAsStoreUser(createStoreStaff('view_product_categories', 'add_product_category', 'edit_product_category'));
        $this->get(route('categories.'.$action, $action === 'edit' ? [$this->category] : []))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Product/Categories/'.ucfirst($action)));
    });
    test("category $action refuses unauthorized callers", function (string $caller) use ($action) {
        actingAsRefusedStoreCaller($caller);
        assertStoreCallerRefused($this->get(route('categories.'.$action, $action === 'edit' ? [$this->category] : [])), $caller);
    })->with('refused store admin callers');
}

test('category list exposes the full narrow row and preserves priority name search and real pagination', function () {
    actingAsStoreUser(createStoreStaff('view_product_categories'));
    $this->get(route('categories.index'))->assertInertia(fn (Assert $page) => $page
        ->has('categories.data.0', fn (Assert $row) => $row->where('id', $this->category->id)->where('name', 'Food')->where('parent', null)
            ->where('order_level', 3)->where('level', 0)->where('banner_url', null)->where('icon_url', null)->where('cover_image_url', null)
            ->where('featured', false)->where('commision_rate', '2.5'))
        ->where('filters', ['search' => null, 'per_page' => 20])->where('defaultLanguage', 'en'));
    foreach (range(1, 22) as $i) {
        Category::forceCreate(['name' => 'Noise '.$i, 'order_level' => $i, 'slug' => 'noise-'.$i]);
    }
    assertPagesWithoutOverlap(fn ($page) => $this->get(route('categories.index', ['page' => $page])), fn ($response) => array_column($response->inertiaProps('categories.data'), 'id'), 23, 20);
    $this->get(route('categories.index', ['per_page' => 10]))->assertInertia(fn (Assert $page) => $page->has('categories.data', 10)->where('categories.data.0.order_level', 22)->where('filters.per_page', 10));
    $this->get(route('categories.index', ['search' => 'Food']))->assertInertia(fn (Assert $page) => $page->has('categories.data', 1)->where('categories.data.0.id', $this->category->id));
    foreach ([11, 0, 1000] as $size) {
        $this->getJson(route('categories.index', ['per_page' => $size]))->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }
    $this->getJson(route('categories.index', ['search' => ['bad']]))->assertUnprocessable()->assertJsonValidationErrors('search');
});

test('category forms expose translated hierarchy attributes settings SEO fields and language selectors', function () {
    actingAsStoreUser(createStoreStaff('add_product_category', 'edit_product_category', 'view_product_categories'));
    DB::table('business_settings')->insert(['type' => 'category_wise_commission', 'value' => '1']);
    $child = Category::forceCreate(['name' => 'Fruit', 'parent_id' => $this->category->id, 'level' => 1]);
    $grandchild = Category::forceCreate(['name' => 'Apples', 'parent_id' => $child->id, 'level' => 2]);
    $other = Category::forceCreate(['name' => 'Other', 'parent_id' => 0]);
    CategoryTranslation::create(['category_id' => $this->category->id, 'lang' => 'ar', 'name' => 'طعام']);
    $this->withSession(['locale' => 'ar'])->get(route('categories.create'))->assertInertia(fn (Assert $page) => $page
        ->where('commissionEnabled', true)->where('parents.0', ['id' => $this->category->id, 'name' => 'طعام'])
        ->where('parents.1', ['id' => $child->id, 'name' => '-- Fruit'])->where('parents.2', ['id' => $grandchild->id, 'name' => '---- Apples'])
        ->where('attributes.0', ['id' => $this->attribute->id, 'name' => 'Color']));
    $this->get(route('categories.edit', [$this->category, 'lang' => 'ar']))->assertInertia(fn (Assert $page) => $page
        ->where('parents', [['id' => $other->id, 'name' => 'Other']])->where('name', 'طعام')->where('lang', 'ar')->has('languages', 2)
        ->where('initialFiles', ['banner' => [], 'icon' => [], 'cover_image' => []])
        ->has('category', fn (Assert $row) => $row->where('id', $this->category->id)->where('name', 'Food')->where('parent_id', 0)->where('order_level', 3)
            ->where('digital', false)->where('banner', null)->where('icon', null)->where('cover_image', null)->where('commision_rate', '2.5')
            ->where('slug', 'food')->where('meta_title', null)->where('meta_description', null)->where('filtering_attributes', [$this->attribute->id])));
});

test('category creation writes default translation parent level ordering type attributes images and SEO atomically', function () {
    $staff = createStoreStaff('add_product_category');
    actingAsStoreUser($staff);
    $image = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image']);
    $this->post(route('categories.store'), [...$this->payload, 'parent_id' => $this->category->id, 'banner' => $image->id, 'icon' => $image->id, 'cover_image' => $image->id, 'lang' => 'ar'])->assertRedirect(route('categories.index'));
    $new = Category::query()->orderByDesc('id')->firstOrFail();
    expect($new->parent_id)->toBe($this->category->id)->and($new->level)->toBe(1)->and($new->order_level)->toBe(8)
        ->and($new->digital)->toBe(1)->and($new->slug)->toBe('NEW-Slug')->and((float) $new->commision_rate)->toBe(9.25)
        ->and($new->banner)->toBe((string) $image->id)->and($new->icon)->toBe((string) $image->id)->and($new->cover_image)->toBe((string) $image->id)
        ->and($new->attributes->modelKeys())->toBe([$this->attribute->id]);
    $this->assertDatabaseHas('category_translations', ['category_id' => $new->id, 'lang' => 'en', 'name' => 'Updated']);
    $this->assertDatabaseMissing('category_translations', ['category_id' => $new->id, 'lang' => 'ar']);
    $this->assertDatabaseHas('categories', ['id' => $new->id, 'meta_title' => 'SEO title', 'meta_description' => 'SEO description']);
});

test('category translation updates change only the selected name plus existing shared fields', function () {
    actingAsStoreUser(createStoreStaff('edit_product_category'));
    Cache::put('featured_categories', 'stale');
    $this->patch(route('categories.update', $this->category), [...$this->payload, 'lang' => 'ar', 'name' => 'فواكه'])->assertRedirect(route('categories.edit', [$this->category, 'lang' => 'ar']));
    expect($this->category->fresh()->name)->toBe('Food')->and($this->category->fresh()->slug)->toBe('new-slug')->and(Cache::has('featured_categories'))->toBeFalse();
    $this->assertDatabaseHas('category_translations', ['category_id' => $this->category->id, 'lang' => 'ar', 'name' => 'فواكه']);
    $this->put(route('categories.update', $this->category), [...$this->payload, 'name' => 'Fruit', 'order_level' => null, 'commision_rate' => null])->assertRedirect();
    expect($this->category->fresh()->name)->toBe('Fruit')->and($this->category->fresh()->order_level)->toBe(8)->and((float) $this->category->fresh()->commision_rate)->toBe(9.25);
    $this->assertDatabaseHas('category_translations', ['category_id' => $this->category->id, 'lang' => 'en', 'name' => 'Fruit']);
});

test('category hierarchy moves retain the legacy single-step first-branch behavior without rewriting unrelated rows', function () {
    actingAsStoreUser(createStoreStaff('edit_product_category'));
    $target = Category::forceCreate(['name' => 'Target', 'level' => 3]);
    $first = Category::forceCreate(['name' => 'First', 'parent_id' => $this->category->id, 'level' => 1, 'order_level' => 9]);
    $deep = Category::forceCreate(['name' => 'Deep', 'parent_id' => $first->id, 'level' => 2]);
    $sibling = Category::forceCreate(['name' => 'Sibling', 'parent_id' => $this->category->id, 'level' => 1, 'order_level' => 1]);
    $this->put(route('categories.update', $this->category), [...$this->payload, 'parent_id' => $target->id])->assertRedirect();
    expect($this->category->fresh()->level)->toBe(4)->and($first->fresh()->level)->toBe(2)->and($deep->fresh()->level)->toBe(3)->and($sibling->fresh()->level)->toBe(1);
    $this->put(route('categories.update', $this->category), $this->payload)->assertRedirect();
    expect($this->category->fresh()->level)->toBe(0)->and($first->fresh()->level)->toBe(1)->and($deep->fresh()->level)->toBe(2)->and($sibling->fresh()->level)->toBe(1);
});

foreach (['store', 'update'] as $action) {
    test("category $action refuses invalid data before any table changes", function (string $field, mixed $value) use ($action) {
        actingAsStoreUser(createStoreStaff('add_product_category', 'edit_product_category'));
        $before = categoryAdminSnapshot();
        $payload = $this->payload;
        data_set($payload, $field, $value);
        $response = $action === 'store' ? $this->postJson(route('categories.store'), $payload) : $this->putJson(route('categories.update', $this->category), $payload);
        $response->assertUnprocessable()->assertJsonValidationErrors($field);
        expect(categoryAdminSnapshot())->toEqual($before);
    })->with([
        ['name', ''], ['name', str_repeat('n', 51)], ['parent_id', 999999], ['parent_id', -1], ['parent_id', ['bad']],
        ['order_level', '1.5'], ['order_level', 2147483648], ['digital', 'bad'], ['banner', 999999], ['icon', '1,2'], ['cover_image', ['bad']],
        ['commision_rate', -1], ['commision_rate', '1.234'], ['commision_rate', 1000000], ['meta_title', str_repeat('s', 256)],
        ['meta_description', str_repeat('s', 65536)], ['slug', str_repeat('s', 256)], ['filtering_attributes', 'bad'], ['filtering_attributes.0', 999999],
    ]);
}

test('category writes reject self descendant and cyclic parent selections with no data changes', function () {
    actingAsStoreUser(createStoreStaff('edit_product_category', 'add_product_category'));
    $child = Category::forceCreate(['name' => 'Child', 'parent_id' => $this->category->id, 'level' => 1]);
    $grandchild = Category::forceCreate(['name' => 'Grandchild', 'parent_id' => $child->id, 'level' => 2]);
    foreach ([$this->category->id, $child->id, $grandchild->id] as $id) {
        $before = categoryAdminSnapshot();
        $this->putJson(route('categories.update', $this->category), [...$this->payload, 'parent_id' => $id])->assertUnprocessable()->assertJsonValidationErrors('parent_id');
        expect(categoryAdminSnapshot())->toEqual($before);
    }
    $cycle = Category::forceCreate(['name' => 'Cycle']);
    $cycle->forceFill(['parent_id' => $cycle->id])->save();
    $before = categoryAdminSnapshot();
    $this->postJson(route('categories.store'), [...$this->payload, 'parent_id' => $cycle->id])->assertUnprocessable()->assertJsonValidationErrors('parent_id');
    expect(categoryAdminSnapshot())->toEqual($before);
});

test('category edit and update reject unknown and malformed languages without mutation', function () {
    actingAsStoreUser(createStoreStaff('edit_product_category'));
    $before = categoryAdminSnapshot();
    foreach (['xx', ['en']] as $lang) {
        $this->getJson(route('categories.edit', [$this->category, 'lang' => $lang]))->assertUnprocessable()->assertJsonValidationErrors('lang');
        $this->putJson(route('categories.update', $this->category), [...$this->payload, 'lang' => $lang])->assertUnprocessable()->assertJsonValidationErrors('lang');
    }
    expect(categoryAdminSnapshot())->toEqual($before);
});

foreach (['store', 'update', 'destroy', 'featured'] as $action) {
    test("category $action refuses unauthorized callers without changes", function (string $caller) use ($action) {
        $before = categoryAdminSnapshot();
        actingAsRefusedStoreCaller($caller);
        $response = match ($action) {
            'store' => $this->post(route('categories.store'), $this->payload),
            'update' => $this->put(route('categories.update', $this->category), $this->payload),
            'destroy' => $this->delete(route('categories.destroy', $this->category)),
            'featured' => $this->post(route('categories.featured'), ['id' => $this->category->id, 'status' => 1]),
        };
        assertStoreCallerRefused($response, $caller);
        expect(categoryAdminSnapshot())->toEqual($before);
    })->with('refused store admin callers');
}

foreach (['store', 'update', 'destroy'] as $action) {
    test("category $action rolls back all tables on persistence failure", function () use ($action) {
        actingAsStoreUser(createStoreStaff('add_product_category', 'edit_product_category', 'delete_product_category'));
        Category::forceCreate(['name' => 'Child', 'parent_id' => $this->category->id, 'level' => 1]);
        $target = Category::forceCreate(['name' => 'Target', 'parent_id' => 0, 'level' => 1]);
        createStoreProduct(['category_id' => $this->category->id]);
        $before = categoryAdminSnapshot();
        if ($action === 'destroy') {
            Category::deleting(fn () => throw new RuntimeException('Delete failure'));
        } else {
            CategoryTranslation::saving(fn () => throw new RuntimeException('Translation failure'));
        }
        $this->withoutExceptionHandling();
        try {
            expect(fn () => match ($action) {
                'store' => $this->post(route('categories.store'), $this->payload),
                'update' => $this->put(route('categories.update', $this->category), [...$this->payload, 'parent_id' => $target->id, 'name' => 'Changed']),
                'destroy' => $this->delete(route('categories.destroy', $this->category)),
            })->toThrow(RuntimeException::class);
            expect(categoryAdminSnapshot())->toEqual($before);
        } finally {
            Category::flushEventListeners();
            CategoryTranslation::flushEventListeners();
        }
    });
}

test('category deletion reparents children clears only direct products and removes only selected translations and pivots', function () {
    actingAsStoreUser(createStoreStaff('delete_product_category'));
    $ancestor = Category::forceCreate(['name' => 'Ancestor', 'level' => 0]);
    $this->category->forceFill(['parent_id' => $ancestor->id, 'level' => 1])->save();
    $first = Category::forceCreate(['name' => 'First', 'parent_id' => $this->category->id, 'level' => 2, 'order_level' => 10]);
    $second = Category::forceCreate(['name' => 'Second', 'parent_id' => $this->category->id, 'level' => 2, 'order_level' => 1]);
    $deep = Category::forceCreate(['name' => 'Deep', 'parent_id' => $first->id, 'level' => 3]);
    CategoryTranslation::create(['category_id' => $first->id, 'lang' => 'en', 'name' => 'First']);
    $direct = createStoreProduct(['category_id' => $this->category->id]);
    $childProduct = createStoreProduct(['category_id' => $first->id]);
    $noise = createStoreProduct(['category_id' => $ancestor->id]);
    Cache::put('featured_categories', 'stale');
    $this->delete(route('categories.destroy', [$this->category, 'page' => 2, 'per_page' => 10, 'search' => 'Food']))->assertRedirect(route('categories.index', ['page' => 2, 'per_page' => 10, 'search' => 'Food']));
    $this->assertDatabaseMissing('categories', ['id' => $this->category->id]);
    $this->assertDatabaseMissing('category_translations', ['category_id' => $this->category->id]);
    $this->assertDatabaseMissing('attribute_category', ['category_id' => $this->category->id]);
    $this->assertDatabaseHas('category_translations', ['category_id' => $first->id, 'name' => 'First']);
    expect($direct->fresh()->category_id)->toBeNull()->and($childProduct->fresh()->category_id)->toBe($first->id)->and($noise->fresh()->category_id)->toBe($ancestor->id)
        ->and($first->fresh()->parent_id)->toBe($ancestor->id)->and($second->fresh()->parent_id)->toBe($ancestor->id)
        ->and($first->fresh()->level)->toBe(1)->and($second->fresh()->level)->toBe(2)->and($deep->fresh()->parent_id)->toBe($first->id)->and($deep->fresh()->level)->toBe(2)
        ->and(Cache::has('featured_categories'))->toBeFalse();
});

test('featured writes preserve the post id status contract and query but reject malformed values without mutation', function () {
    actingAsStoreUser(createStoreStaff('edit_product_category'));
    Cache::put('featured_categories', 'stale');
    $this->post(route('categories.featured', ['page' => 2, 'per_page' => 10]), ['id' => $this->category->id, 'status' => 1])->assertRedirect(route('categories.index', ['page' => 2, 'per_page' => 10]));
    expect($this->category->fresh()->featured)->toBe(1)->and(Cache::has('featured_categories'))->toBeFalse();
    $before = categoryAdminSnapshot();
    foreach ([['id' => 999999, 'status' => 0], ['id' => $this->category->id, 'status' => 2], ['id' => [$this->category->id], 'status' => 0]] as $payload) {
        $this->postJson(route('categories.featured'), $payload)->assertUnprocessable();
        expect(categoryAdminSnapshot())->toEqual($before);
    }
});

test('category uploads permit own and library images while retained foreign references grant no replacement or preview access', function () {
    $staff = createStoreStaff('edit_product_category', 'add_product_category');
    actingAsStoreUser($staff);
    $own = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image']);
    $foreign = Upload::factory()->create(['user_id' => createStoreUser('customer')->id, 'type' => 'image']);
    $file = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'document']);
    foreach (['banner', 'icon', 'cover_image'] as $field) {
        foreach ([$foreign, $file] as $upload) {
            $before = categoryAdminSnapshot();
            $this->putJson(route('categories.update', $this->category), [...$this->payload, $field => $upload->id])->assertUnprocessable()->assertJsonValidationErrors($field);
            expect(categoryAdminSnapshot())->toEqual($before);
        }
        $this->put(route('categories.update', $this->category), [...$this->payload, $field => $own->id])->assertRedirect();
        $this->category->forceFill([$field => $foreign->id])->save();
        $this->put(route('categories.update', $this->category), [...$this->payload, $field => $foreign->id])->assertRedirect();
        $this->get(route('categories.edit', $this->category))->assertInertia(fn (Assert $page) => $page->where('initialFiles.'.$field, []));
    }
    actingAsStoreUser(createStoreStaff('add_product_category', 'view_uploads'));
    $this->post(route('categories.store'), [...$this->payload, 'banner' => $foreign->id, 'icon' => $foreign->id, 'cover_image' => $foreign->id])->assertRedirect();
});

foreach (['edit', 'update', 'destroy'] as $action) {
    test("category $action returns 404 for a missing bound model", function () use ($action) {
        actingAsStoreUser(createStoreStaff('edit_product_category', 'delete_product_category'));
        match ($action) {
            'edit' => $this->get(route('categories.edit', 999999))->assertNotFound(),
            'update' => $this->put(route('categories.update', 999999), $this->payload)->assertNotFound(),
            'destroy' => $this->delete(route('categories.destroy', 999999))->assertNotFound(),
        };
    });
}

test('WMS authentication grants no category action and no store writes', function () {
    $this->actingAs(User::factory()->create(), 'web');
    $before = categoryAdminSnapshot();
    foreach ([['GET', 'index'], ['GET', 'create'], ['GET', 'edit'], ['POST', 'store'], ['PUT', 'update'], ['DELETE', 'destroy'], ['POST', 'featured']] as [$method, $action]) {
        $this->call($method, route('categories.'.$action, in_array($action, ['edit', 'update', 'destroy']) ? [$this->category] : []), [...$this->payload, 'id' => $this->category->id, 'status' => 1])->assertRedirect(route('shop.login'));
    }
    expect(categoryAdminSnapshot())->toEqual($before);
});

test('category migration retains public seller and mobile contracts and removes only the unused show', function () {
    expect(Route::has('categories.show'))->toBeFalse();
    $child = Category::forceCreate(['name' => 'Child', 'parent_id' => $this->category->id, 'level' => 1]);
    $this->getJson('/temp/api/v2/categories')->assertOk()->assertJsonPath('success', true)->assertJsonPath('status', 200)
        ->assertJsonPath('data.0', ['id' => $this->category->id, 'name' => 'Food', 'banner' => uploaded_asset(null), 'icon' => uploaded_asset(null), 'number_of_children' => 1,
            'links' => ['products' => route('api.products.category', $this->category->id), 'sub_categories' => route('subCategories.index', $this->category->id)]]);
    expect(view()->exists('shop.categories.child_category'))->toBeTrue()->and(view()->exists('shop.backend.downloads.category'))->toBeTrue();
});

test('category read permission authorizes no forms or mutations', function () {
    actingAsStoreUser(createStoreStaff('view_product_categories'));
    $before = categoryAdminSnapshot();
    $this->get(route('categories.create'))->assertForbidden();
    $this->get(route('categories.edit', $this->category))->assertForbidden();
    $this->post(route('categories.store'), $this->payload)->assertForbidden();
    $this->put(route('categories.update', $this->category), $this->payload)->assertForbidden();
    $this->delete(route('categories.destroy', $this->category))->assertForbidden();
    $this->post(route('categories.featured'), ['id' => $this->category->id, 'status' => 1])->assertForbidden();
    expect(categoryAdminSnapshot())->toEqual($before);
});

test('category list localizes parent labels and retains root-name filtering', function () {
    actingAsStoreUser(createStoreStaff('view_product_categories'));
    $child = Category::forceCreate(['name' => 'Fruit', 'parent_id' => $this->category->id, 'level' => 1, 'order_level' => 4]);
    CategoryTranslation::create(['category_id' => $this->category->id, 'lang' => 'ar', 'name' => 'طعام']);
    CategoryTranslation::create(['category_id' => $child->id, 'lang' => 'ar', 'name' => 'فواكه']);
    $this->withSession(['locale' => 'ar'])->get(route('categories.index', ['search' => 'Fruit']))->assertInertia(fn (Assert $page) => $page
        ->has('categories.data', 1)->where('categories.data.0.name', 'فواكه')->where('categories.data.0.parent', ['id' => $this->category->id, 'name' => 'طعام']));
    $this->get(route('categories.index', ['search' => 'فواكه']))->assertInertia(fn (Assert $page) => $page->has('categories.data', 0));
});

test('category creation keeps blank ordering and commission defaults and the existing generated slug shape', function () {
    actingAsStoreUser(createStoreStaff('add_product_category'));
    $this->post(route('categories.store'), [...$this->payload, 'order_level' => null, 'commision_rate' => null, 'slug' => null])->assertRedirect();
    $new = Category::query()->orderByDesc('id')->firstOrFail();
    expect($new->order_level)->toBe(0)->and((float) $new->commision_rate)->toBe(0.0)->and($new->slug)->toMatch('/^Updated-[A-Za-z0-9]{5}$/');
});

test('category edit previews only permitted images through the canonical resource', function () {
    $staff = createStoreStaff('edit_product_category');
    $image = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image', 'file_original_name' => 'Own category image', 'file_size' => 128, 'external_link' => 'https://example.com/category.png']);
    $this->category->forceFill(['banner' => $image->id, 'icon' => $image->id, 'cover_image' => $image->id])->save();
    actingAsStoreUser($staff);
    $expected = ['id' => $image->id, 'name' => 'Own category image', 'type' => 'image', 'size' => 128, 'url' => 'https://example.com/category.png'];
    $this->get(route('categories.edit', $this->category))->assertInertia(fn (Assert $page) => $page->where('initialFiles', ['banner' => [$expected], 'icon' => [$expected], 'cover_image' => [$expected]]));
    actingAsStoreUser(createStoreStaff('edit_product_category', 'view_uploads'));
    $this->get(route('categories.edit', $this->category))->assertInertia(fn (Assert $page) => $page->where('initialFiles.banner', [$expected]));
});
