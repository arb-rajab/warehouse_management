<?php

use App\Http\Controllers\CustomerProductController;
use App\Http\Controllers\Store\Admin\ClassifiedProductController;
use App\Models\CustomerProduct;
use App\Models\CustomerProductTranslation;
use App\Models\Permission;
use App\Models\Upload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->product = CustomerProduct::forceCreate([
        'name' => 'Bicycle', 'slug' => 'classified-bicycle', 'added_by' => 'customer',
        'status' => 1, 'published' => 0, 'user_id' => createStoreUser('customer')->id,
    ]);
    CustomerProductTranslation::forceCreate(['customer_product_id' => $this->product->id, 'lang' => 'en', 'name' => 'Translated bicycle']);
});

test('the classified products screen renders under the strict Inertia policy', function () {
    actingAsStoreUser(createStoreStaff('view_classified_products'));
    $full = $this->get(route('classified_products'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Customers/ClassifiedProducts/Index'));
    $visit = $this->get(route('classified_products'), inertiaHeaders())->assertOk()->assertHeader('X-Inertia', 'true');
    expect(scriptSrcOf($full))->toBe("script-src 'self'")->and(scriptSrcOf($visit))->toBe("script-src 'self'");
});

test('classified products expose the complete translated legacy row without uploader queries', function () {
    actingAsStoreUser(createStoreStaff('view_classified_products'));
    $upload = Upload::factory()->create(['file_name' => 'uploads/bicycle.png', 'external_link' => null]);
    $this->product->forceFill(['thumbnail_img' => (string) $upload->id])->save();
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    $this->get(route('classified_products'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('filters.per_page', 20)
        ->has('products.data', 1)
        ->has('products.data.0', fn (Assert $row) => $row
            ->where('id', $this->product->id)->where('name', 'Translated bicycle')
            ->where('public_url', route('customer.product', 'classified-bicycle'))
            ->where('thumbnail_url', my_asset('uploads/bicycle.png'))->where('added_by', 'customer')
            ->where('status', true)->where('published', false)));
    expect(collect($queries)->filter(fn ($sql) => str_contains($sql, 'customer_product_translations'))->count())->toBe(1);
});

test('classified products keep base-name fallback nullable uploader and missing-image placeholder', function () {
    actingAsStoreUser(createStoreStaff('view_classified_products'));
    $this->product->customer_product_translations()->delete();
    $this->product->forceFill(['added_by' => null, 'status' => 0, 'published' => 1, 'thumbnail_img' => '999999'])->save();
    $this->get(route('classified_products'))->assertInertia(fn (Assert $page) => $page
        ->has('products.data.0', fn (Assert $row) => $row
            ->where('id', $this->product->id)->where('name', 'Bicycle')
            ->where('public_url', route('customer.product', 'classified-bicycle'))
            ->where('thumbnail_url', static_asset('assets/img/placeholder.jpg'))->where('added_by', null)
            ->where('status', false)->where('published', true)));
    CustomerProductTranslation::forceCreate(['customer_product_id' => $this->product->id, 'lang' => 'ar', 'name' => 'دراجة']);
    session(['locale' => 'ar']);
    $this->get(route('classified_products'))->assertInertia(fn (Assert $page) => $page->where('products.data.0.name', 'دراجة'));
});

test('classified products paginate newest first with a stable tie break and bounded image translation queries', function () {
    actingAsStoreUser(createStoreStaff('view_classified_products'));
    $upload = Upload::factory()->create(['file_name' => null, 'external_link' => 'https://example.com/classified.png']);
    backdate($this->product, now()->subDay());
    $ids = [];
    for ($i = 0; $i < 22; $i++) {
        $product = CustomerProduct::forceCreate(['name' => "Product $i", 'slug' => "classified-$i", 'thumbnail_img' => (string) $upload->id, 'created_at' => '2026-10-18 12:00:00']);
        backdate($product, now());
        $ids[] = $product->id;
    }
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });
    $response = $this->get(route('classified_products', ['per_page' => 10, 'page' => 2]))->assertInertia(fn (Assert $page) => $page
        ->has('products.data', 10)->where('products.meta.total', 23)->where('products.meta.from', 11)
        ->where('filters.per_page', 10)->where('products.data.0.thumbnail_url', 'https://example.com/classified.png'));
    expect(array_column($response->inertiaProps('products.data'), 'id'))->toBe(array_slice(array_reverse($ids), 10, 10));
    expect(collect($queries)->filter(fn ($sql) => str_contains($sql, 'customer_product_translations'))->count())->toBe(1)
        ->and(collect($queries)->filter(fn ($sql) => str_contains($sql, 'from "uploads"'))->count())->toBe(1);
    expect($response->inertiaProps('products.meta.links.0.url'))->toContain('per_page=10');
    $this->get(route('classified_products'))->assertInertia(fn (Assert $page) => $page->has('products.data', 20));
});

test('classified products reject unsupported per page values', function (mixed $value) {
    actingAsStoreUser(createStoreStaff('view_classified_products'));
    $this->getJson(route('classified_products', ['per_page' => $value]))->assertUnprocessable()->assertJsonValidationErrors('per_page');
})->with([11, 0, -1, 'bad', [[10]]]);

test('classified publishing changes only the admin flag both ways and keeps the query', function (bool $published) {
    Notification::fake();
    actingAsStoreUser(createStoreStaff('publish_classified_product'));
    $this->product->forceFill(['published' => (int) ! $published])->save();
    $before = $this->product->fresh()->getAttributes();
    $this->put(route('classified_products.published', ['classifiedProduct' => $this->product->id, 'per_page' => 50, 'page' => 2]), ['status' => $published])
        ->assertRedirect(route('classified_products', ['per_page' => 50, 'page' => 2]));
    $after = $this->product->fresh()->getAttributes();
    expect((bool) $after['published'])->toBe($published)
        ->and(array_diff_key($after, array_flip(['published', 'updated_at'])))->toBe(array_diff_key($before, array_flip(['published', 'updated_at'])));
    $this->assertDatabaseHas('customer_product_translations', ['customer_product_id' => $this->product->id, 'name' => 'Translated bicycle']);
    Notification::assertNothingSent();
})->with([true, false]);

test('classified publishing validates the boolean before writing', function (array $payload) {
    actingAsStoreUser(createStoreStaff('publish_classified_product'));
    $before = $this->product->fresh()->getAttributes();
    $this->putJson(route('classified_products.published', $this->product->id), $payload)->assertUnprocessable()->assertJsonValidationErrors('status');
    expect($this->product->fresh()->getAttributes())->toBe($before);
})->with([[[]], [['status' => null]], [['status' => 'yes']], [['status' => 2]], [['status' => []]]]);

test('classified deletion removes only its product and translations and retains images and other owners', function () {
    Notification::fake();
    Storage::fake('public');
    Storage::disk('public')->put('uploads/retained.png', 'retained image');
    $upload = Upload::factory()->create(['file_name' => 'uploads/retained.png']);
    $this->product->forceFill(['thumbnail_img' => (string) $upload->id, 'photos' => (string) $upload->id])->save();
    CustomerProductTranslation::forceCreate(['customer_product_id' => $this->product->id, 'lang' => 'ar', 'name' => 'دراجة']);
    $other = CustomerProduct::forceCreate(['name' => 'Other owner', 'slug' => 'other-owner', 'user_id' => createStoreUser('customer')->id]);
    CustomerProductTranslation::forceCreate(['customer_product_id' => $other->id, 'lang' => 'en', 'name' => 'Keep translation']);
    $owner = $this->product->user->getAttributes();
    actingAsStoreUser(createStoreStaff('delete_classified_product'));
    $this->delete(route('classified_products.destroy', ['classifiedProduct' => $this->product->id, 'page' => 2, 'per_page' => 10]))
        ->assertRedirect(route('classified_products', ['page' => 2, 'per_page' => 10]));
    $this->assertDatabaseMissing('customer_products', ['id' => $this->product->id]);
    $this->assertDatabaseMissing('customer_product_translations', ['customer_product_id' => $this->product->id]);
    $this->assertDatabaseHas('customer_products', ['id' => $other->id]);
    $this->assertDatabaseHas('customer_product_translations', ['customer_product_id' => $other->id, 'name' => 'Keep translation']);
    $this->assertDatabaseHas('uploads', ['id' => $upload->id]);
    expect($this->product->user->fresh()->getAttributes())->toBe($owner);
    Storage::disk('public')->assertExists('uploads/retained.png');
    Notification::assertNothingSent();
});

foreach (['published', 'destroy'] as $action) {
    test("classified $action returns 404 for a missing bound record", function () use ($action) {
        actingAsStoreUser(createStoreStaff('publish_classified_product', 'delete_classified_product'));
        $url = route("classified_products.$action", 999999);
        ($action === 'published' ? $this->put($url, ['status' => true]) : $this->delete($url))->assertNotFound();
    });
}

foreach (['index', 'published', 'destroy'] as $action) {
    test("classified $action refuses unauthorized callers without writes", function (string $caller) use ($action) {
        actingAsRefusedStoreCaller($caller);
        $before = DB::table('customer_products')->get()->toArray();
        $translations = DB::table('customer_product_translations')->get()->toArray();
        $response = match ($action) {
            'index' => $this->get(route('classified_products')),
            'published' => $this->put(route('classified_products.published', $this->product->id), ['status' => true]),
            'destroy' => $this->delete(route('classified_products.destroy', $this->product->id)),
        };
        assertStoreCallerRefused($response, $caller);
        expect(DB::table('customer_products')->get()->toArray())->toEqual($before)
            ->and(DB::table('customer_product_translations')->get()->toArray())->toEqual($translations);
    })->with('refused store admin callers');
}

test('classified routes keep their names and separate the retained customer compatibility handler', function () {
    expect(Route::getRoutes()->getByName('classified_products')->getActionName())->toBe(ClassifiedProductController::class.'@customer_product_index')
        ->and(Route::getRoutes()->getByName('classified_products.published')->methods())->toBe(['PUT'])
        ->and(Route::getRoutes()->getByName('classified_products.destroy')->methods())->toBe(['DELETE'])
        ->and(Route::getRoutes()->getByName('customer_products.published')->getActionName())->toBe(CustomerProductController::class.'@updatePublished');
    $customer = createStoreUser('customer');
    $customer->givePermissionTo(Permission::findOrCreate('publish_classified_product', 'store'));
    actingAsStoreUser($customer);
    $this->post(route('customer_products.published'), ['id' => $this->product->id, 'status' => 1])->assertOk()->assertContent('1');
    expect($this->product->fresh()->getAttribute('published'))->toBe(1);
});
