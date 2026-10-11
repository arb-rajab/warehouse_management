<?php

use App\Models\Category;
use App\Models\CustomerProduct;
use App\Models\Upload;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function administrationClassifiedProduct(array $attributes = []): CustomerProduct
{
    return CustomerProduct::forceCreate([
        'name' => 'Customer advert', 'slug' => 'advert-'.uniqid(), 'added_by' => 'customer',
        'user_id' => createStoreUser()->id, 'status' => 1, 'published' => 0,
        'description' => '<p>Public description</p>', ...$attributes,
    ]);
}

test('classified administration can be rendered', function () {
    actingAsStoreUser(createStoreStaff('view_classified_products'));
    $this->get(route('classified_products'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Customers/ClassifiedProducts/Index'));
});

test('classified props are narrow and translated, with batched upload previews', function () {
    actingAsStoreUser(createStoreStaff('view_classified_products'));
    $upload = Upload::factory()->create(['external_link' => 'https://example.com/photo.webp']);
    $product = administrationClassifiedProduct(['slug' => 'classified-advert', 'thumbnail_img' => $upload->id]);
    $product->customer_product_translations()->create(['lang' => 'ar', 'name' => 'إعلان عميل']);
    administrationClassifiedProduct(['name' => 'Noise']);
    $this->withSession(['locale' => 'ar'])->get(route('classified_products'))->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('products.data', 2)->has('products.data.1', fn (Assert $record) => $record
            ->where('id', $product->id)->where('name', 'إعلان عميل')->where('public_url', route('customer.product', 'classified-advert'))
            ->where('added_by', 'customer')->where('status', true)->where('published', false)->where('thumbnail_url', 'https://example.com/photo.webp'))
    );
});

test('classified lists paginate beyond one page and preserve query parameters', function () {
    actingAsStoreUser(createStoreStaff('view_classified_products'));
    for ($i = 0; $i < 21; $i++) {
        administrationClassifiedProduct();
    }
    $this->get(route('classified_products'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('products.data', 20)->where('products.meta.total', 21)->where('filters.per_page', 20));
    $this->get(route('classified_products', ['per_page' => 10]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('products.data', 10)->where('products.meta.per_page', 10));
    $this->getJson(route('classified_products', ['per_page' => 999]))->assertUnprocessable()->assertJsonValidationErrors('per_page');
});

test('classified list refuses every unauthorized caller', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route('classified_products')), $caller);
})->with('refused store admin callers');

test('publish changes only the administrative publication flag and retains query', function () {
    actingAsStoreUser(createStoreStaff('publish_classified_product'));
    $product = administrationClassifiedProduct();
    $owner = $product->user_id;
    $this->post(route('classified_products.published', ['product' => $product, 'page' => 2]), ['published' => 1])
        ->assertRedirect(route('classified_products', ['page' => 2]));
    expect($product->fresh()->published)->toBe(1)->and($product->fresh()->status)->toBe(1)->and($product->fresh()->user_id)->toBe($owner);
    $this->post(route('classified_products.published', $product), ['published' => 0])->assertRedirect();
    expect($product->fresh()->published)->toBe(0);
});

test('publication validates its boolean without modifying the product', function (array $data) {
    actingAsStoreUser(createStoreStaff('publish_classified_product'));
    $product = administrationClassifiedProduct();
    $this->postJson(route('classified_products.published', $product), $data)->assertUnprocessable()->assertJsonValidationErrors('published');
    expect($product->fresh()->published)->toBe(0);
})->with([[[]], [['published' => 'yes']], [['published' => 2]], [['published' => []]]]);

test('publication refuses unauthorized writes', function (string $caller) {
    $product = administrationClassifiedProduct();
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post(route('classified_products.published', $product), ['published' => 1]), $caller);
    expect($product->fresh()->published)->toBe(0);
})->with('refused store admin callers');

test('a read-only classified permission cannot publish or delete', function () {
    $product = administrationClassifiedProduct();
    actingAsStoreUser(createStoreStaff('view_classified_products'));
    $this->post(route('classified_products.published', $product), ['published' => 1])->assertForbidden();
    $this->delete(route('classified_products.destroy', $product))->assertForbidden();
    expect($product->fresh()->published)->toBe(0);
});

test('deletion removes only the chosen product and its translations', function () {
    actingAsStoreUser(createStoreStaff('delete_classified_product'));
    $product = administrationClassifiedProduct();
    $product->customer_product_translations()->create(['name' => 'Translated', 'lang' => 'ar']);
    $noise = administrationClassifiedProduct();
    $noise->customer_product_translations()->create(['name' => 'Other', 'lang' => 'ar']);
    $this->delete(route('classified_products.destroy', ['product' => $product, 'per_page' => 10]))->assertRedirect(route('classified_products', ['per_page' => 10]));
    $this->assertDatabaseMissing('customer_products', ['id' => $product->id]);
    $this->assertDatabaseMissing('customer_product_translations', ['customer_product_id' => $product->id]);
    $this->assertDatabaseHas('customer_products', ['id' => $noise->id]);
    $this->assertDatabaseHas('customer_product_translations', ['customer_product_id' => $noise->id]);
});

test('deletion refuses unauthorized writes', function (string $caller) {
    $product = administrationClassifiedProduct();
    $product->customer_product_translations()->create(['name' => 'Translated', 'lang' => 'ar']);
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->delete(route('classified_products.destroy', $product)), $caller);
    $this->assertDatabaseHas('customer_products', ['id' => $product->id]);
    $this->assertDatabaseHas('customer_product_translations', ['customer_product_id' => $product->id]);
})->with('refused store admin callers');

test('classified bound writes return 404 for absent products', function (string $action) {
    actingAsStoreUser(createStoreStaff('publish_classified_product', 'delete_classified_product'));
    if ($action === 'publish') {
        $this->post(route('classified_products.published', 999999), ['published' => 1])->assertNotFound();
    } else {
        $this->delete(route('classified_products.destroy', 999999))->assertNotFound();
    }
})->with(['publish', 'destroy']);

test('the public classified view retains Blade and safe HTML rendering', function () {
    seedStoreCurrency();
    DB::table('business_settings')->insert(['type' => 'classified_product', 'value' => '1']);
    $category = Category::forceCreate(['name' => 'Classified category', 'slug' => 'classified-category']);
    $product = administrationClassifiedProduct(['category_id' => $category->id, 'published' => 1, 'description' => '<p>Public description</p><script>alert("classified-xss")</script>']);
    $this->get(route('customer.product', $product->slug))->assertOk()->assertViewIs('shop.frontend.customer_product_details')
        ->assertSee('Public description')->assertDontSee('classified-xss');
});
