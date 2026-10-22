<?php

use App\Models\Product;
use App\Models\StoreUser;
use App\Services\ProductService;

/**
 * A store seller's own products and coupons, and nobody else's. The seller
 * area's product update, publish and feature actions and the app API's
 * product and coupon updates took any record by id, so any seller could
 * rename, reprice or unpublish another seller's products or the store's own,
 * and rewrite another seller's coupon (ProductPolicy, CouponPolicy).
 *
 * A seller's own product update is not driven end to end here: the store's
 * ProductService mass-assigns columns Product's #[Fillable] (this app's)
 * doesn't allow, now repaired by the explicit panel writer (SellerProductPersistenceTest);
 * the seller API retains the legacy service.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sellerProductForm(array $overrides = []): array
{
    return [
        'name' => 'Renamed', 'slug' => 'renamed', 'category_id' => 1, 'unit' => 'pc', 'min_qty' => 1,
        'unit_price' => 0.5, 'current_stock' => 0, 'lang' => 'en', 'tags' => [null],
        ...$overrides,
    ];
}

/**
 * A seller's product, with no image: the seller API's product page lists a
 * product's uploads by columns the `uploads` stand-in deliberately leaves
 * out (see CreateUploadsTableTest).
 */
function sellerProduct(StoreUser $seller): Product
{
    return createStoreProduct(['user_id' => $seller->id, 'added_by' => 'seller', 'seller_featured' => 1, 'thumbnail_img' => null]);
}

test("a seller cannot update, publish or feature another seller's product in the seller area", function () {
    $product = sellerProduct(createStoreUser('seller'));
    actingAsStoreUser(createStoreUser('seller'));

    $this->post("/temp/seller/products/update/{$product->id}", sellerProductForm())->assertForbidden();
    $this->post('/temp/seller/products/published', ['id' => $product->id, 'status' => 0])->assertOk()->assertSee('0');
    $this->post('/temp/seller/products/seller/featured', ['id' => $product->id, 'status' => 0])->assertOk()->assertSee('0');

    expect($product->fresh()->only(['name', 'unit_price', 'published', 'seller_featured']))->toEqual([
        'name' => $product->name,
        'unit_price' => 2.5,
        'published' => true,
        'seller_featured' => 1,
    ]);
});

test('a seller can publish and unpublish their own product in the seller area', function () {
    $seller = actingAsStoreUser(createStoreUser('seller'));
    $product = sellerProduct($seller);

    $this->post('/temp/seller/products/published', ['id' => $product->id, 'status' => 0])->assertOk()->assertSee('1');

    expect($product->fresh()->published)->toBeFalse();
});

test("a seller cannot change another seller's product through the app API", function () {
    $product = sellerProduct(createStoreUser('seller'));
    $this->withToken(storeApiToken(createStoreUser('seller')));

    $this->postJson("/temp/api/v2/seller/products/update/{$product->id}", sellerProductForm())
        ->assertOk()
        ->assertJsonPath('result', false);
    $this->getJson("/temp/api/v2/seller/products/edit/{$product->id}")->assertJsonPath('result', false);
    $this->getJson("/temp/api/v2/seller/product/duplicate/{$product->id}")->assertJsonPath('result', false);
    $this->getJson("/temp/api/v2/seller/product/delete/{$product->id}")->assertJsonPath('result', false);

    expect(Product::count())->toBe(1)
        ->and($product->fresh()->only(['name', 'unit_price']))->toEqual(['name' => $product->name, 'unit_price' => 2.5]);
});

test('a seller can open their own product for editing through the app API', function () {
    $seller = createStoreUser('seller');
    $product = sellerProduct($seller);

    $this->withToken(storeApiToken($seller))
        ->getJson("/temp/api/v2/seller/products/edit/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $product->id);
});

test("a seller can update their own coupon through the app API and not another seller's", function () {
    $seller = createStoreUser('seller');
    $coupon = sellerCoupon($seller);
    $form = [
        'type' => 'cart_base', 'code' => 'SAVE10', 'discount' => 10, 'discount_type' => 'amount',
        'min_buy' => 20, 'max_discount' => 10, 'date_range' => now()->format('m/d/Y').' - '.now()->addWeek()->format('m/d/Y'),
    ];

    $this->withToken(storeApiToken(createStoreUser('seller')))
        ->postJson("/temp/api/v2/seller/coupon/update/{$coupon->id}", $form)
        ->assertJsonPath('result', false);

    expect($coupon->fresh()->code)->toBe('SAVE5');

    // The sanctum guard keeps the user it resolved for the rest of the test.
    app('auth')->forgetGuards();

    $this->withToken(storeApiToken($seller))
        ->postJson("/temp/api/v2/seller/coupon/update/{$coupon->id}", $form)
        ->assertOk()
        ->assertJsonPath('result', true);

    expect($coupon->fresh()->only(['code', 'discount', 'details']))->toEqual([
        'code' => 'SAVE10',
        'discount' => 10,
        'details' => '{"min_buy":20,"max_discount":10}',
    ]);
});

test("a seller cannot open, update or delete another seller's digital product in the seller area", function () {
    $product = createStoreProduct(['user_id' => createStoreUser('seller')->id, 'added_by' => 'seller', 'digital' => 1, 'thumbnail_img' => null]);
    actingAsStoreUser(createStoreUser('seller'));

    $this->get("/temp/seller/digitalproducts/{$product->id}/edit")
        ->assertRedirect()
        ->assertSessionHas('flash_notification');
    $this->post("/temp/seller/digitalproducts/update/{$product->id}", sellerProductForm(['unit_price' => 0.5]))->assertRedirect();
    $this->post("/temp/seller/digitalproducts/destroy/{$product->id}")->assertRedirect();

    expect($product->fresh())->not->toBeNull()
        ->and($product->fresh()->only(['name', 'slug', 'unit_price']))->toEqual([
            'name' => $product->name,
            'slug' => $product->slug,
            'unit_price' => 2.5,
        ]);
});

test('a seller can open and delete their own digital product in the seller area', function () {
    $seller = actingAsStoreUser(createStoreUser('seller'));
    $product = createStoreProduct(['user_id' => $seller->id, 'added_by' => 'seller', 'digital' => 1, 'thumbnail_img' => null]);

    $this->get("/temp/seller/digitalproducts/{$product->id}/edit")
        ->assertOk()
        ->assertSee($product->name);
    $this->post("/temp/seller/digitalproducts/destroy/{$product->id}")->assertRedirect('/temp/seller/digitalproducts');

    expect(Product::find($product->id))->toBeNull();
});

test("a seller cannot download another seller's digital product file", function () {
    $product = createStoreProduct(['user_id' => createStoreUser('seller')->id, 'digital' => 1]);
    actingAsStoreUser(createStoreUser('seller'));

    $this->get('/temp/seller/digitalproducts/download/'.encrypt($product->id))->assertNotFound();
});

/**
 * Fields a seller's form must never be able to set: the service decides them
 * (ownership, approval, publication, featuring).
 *
 * @return array<string, mixed>
 */
function forgedProductFields(): array
{
    return [
        'user_id' => 1, 'approved' => 1, 'published' => 1, 'added_by' => 'admin', 'featured' => 1,
        'todays_deal' => 1, 'seller_featured' => 1, 'num_of_sale' => 999, 'rating' => 5, 'digital' => 1,
    ];
}

test('the service cuts a seller\'s input down to the fields a seller may set', function () {
    $input = [
        ...sellerProductForm(),
        ...forgedProductFields(),
        'choice_options_3' => ['S'], 'price_Red-M' => 5, 'sku_Red-M' => 'X', 'qty_Red-M' => 2, 'img_Red-M' => 3,
        '_token' => 'abc', 'tax_id' => [1], 'sku' => ['a'],
    ];

    $allowed = app(ProductService::class)->sellerAttributes($input);

    expect(array_keys($allowed))->toBe([
        'name', 'slug', 'category_id', 'unit', 'min_qty', 'unit_price', 'current_stock', 'lang', 'tags',
        'choice_options_3', 'price_Red-M', 'sku_Red-M', 'qty_Red-M', 'img_Red-M',
    ])->and($allowed['name'])->toBe('Renamed');
});

test('a seller product form refuses forged server-owned fields without touching products', function (string $method) {
    $seller = createStoreUser('seller');
    $product = sellerProduct($seller);
    actingAsStoreUser($seller);
    $before = Product::query()->get()->toJson();
    $url = $method === 'store' ? '/temp/seller/products/store' : "/temp/seller/products/update/{$product->id}";
    $this->post($url, sellerProductForm(forgedProductFields()))->assertSessionHasErrors(array_keys(forgedProductFields()));
    expect(Product::query()->get()->toJson())->toBe($before);
})->with(['store', 'update']);
