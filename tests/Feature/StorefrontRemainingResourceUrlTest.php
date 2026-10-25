<?php

use App\Http\Resources\Store\DashboardProductResource;
use App\Http\Resources\Store\DigitalProductResource;
use App\Http\Resources\Store\ProductListResource;
use App\Http\Resources\Store\ReviewResource;
use App\Http\Resources\V2\DigitalProductDetailCollection;
use App\Models\Category;
use App\Models\CustomerProduct;
use App\Models\Product;
use App\Models\Review;
use App\Models\StoreUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    seedStoreCurrency();
});

dataset('final storefront slugs', ['null' => null, 'empty' => '', 'normal' => 'd15-final', 'zero' => '0']);

function finalResourceUrl(int $id, ?string $slug, bool $absolute = true): string
{
    return $slug === null || $slug === ''
        ? ($absolute ? config('app.url') : '').'/temp/product/~id-'.$id.'?catalog_id='.$id
        : route('product', $slug, $absolute);
}

function finalResourceProduct(?string $slug): Product
{
    return createStoreProduct([
        'name' => 'Final product', 'ar_name' => 'المنتج', 'slug' => $slug,
        'user_id' => createStoreUser('admin')->id, 'added_by' => 'admin',
        'thumbnail_img' => null, 'category_id' => null, 'brand_id' => null,
        'rating' => 4, 'tags' => 'final', 'photos' => '', 'description' => 'Description',
        'unit' => 'Box', 'unit_equal' => 1, 'approved' => 1,
    ])->fresh();
}

/** @param array<string, mixed> $payload */
function assertFinalResourcePayload(string $resource, array $payload, Product|CustomerProduct $product): void
{
    $row = isset($payload['data']) ? $payload['data'][0] : $payload;
    expect($row['id'])->toBe($resource === 'review' ? Review::sole()->id : $product->id);
    $row['id'] = 0;
    if (isset($row['seller_id'])) {
        expect($row['seller_id'])->toBe($product->user_id);
        $row['seller_id'] = 0;
    }
    if ($resource === 'review') {
        expect($row['product']['id'])->toBe($product->id);
        $row['product']['id'] = 0;
        expect($row['customer']['id'])->toBe(Review::sole()->user_id);
        $row['customer']['id'] = 0;
    }
    if (isset($row['download_token'])) {
        expect(decrypt($row['download_token']))->toBe($product->id);
        $row['download_token'] = '<encrypted product id>';
    }
    if (isset($payload['data'])) {
        $payload['data'] = [$row];
    } else {
        $payload = $row;
    }
    array_walk_recursive($payload, function (mixed &$value): void {
        if (is_string($value)) {
            $value = str_replace(config('app.url'), '<app-url>', $value);
        }
    });
    $expected = json_decode(file_get_contents(base_path('tests/Feature/Support/storefront-resource-payloads.json')), true, flags: JSON_THROW_ON_ERROR);
    expect($payload)->toEqual($expected[$resource]);
}

test('each Store resource emits the exact original absolute product URL or fallback', function (string $resource, ?string $slug) {
    $product = finalResourceProduct($slug)->load(['stocks', 'category', 'collection', 'thumbnailUpload', 'taxes', 'product_translations']);
    $url = finalResourceUrl($product->id, $slug);
    $row = match ($resource) {
        'dashboard' => (new DashboardProductResource($product))->resolve(),
        'digital' => (new DigitalProductResource($product))->resolve(),
        'list' => (new ProductListResource($product))->resolve(),
        'review' => (new ReviewResource(Review::forceCreate([
            'product_id' => $product->id, 'user_id' => createStoreUser('customer', 'd15@example.test')->id,
            'rating' => 4, 'comment' => 'Review', 'status' => 1,
        ])->load(['product.product_translations', 'user'])))->resolve(),
    };

    expect($resource === 'review' ? $row['product']['url'] : $row['public_url'])->toBe($url);
    if ($slug === 'd15-final') {
        assertFinalResourcePayload($resource, $row, $product);
    }
    if ($resource === 'review') {
        expect($row['product'])->toBe(['id' => $product->id, 'name' => 'Final product', 'owner' => 'admin', 'url' => $url]);
    }
})->with(['dashboard', 'digital', 'list', 'review'])->with('final storefront slugs');

test('the authenticated mobile product endpoint emits the original absolute URL or fallback', function (?string $slug) {
    $product = finalResourceProduct($slug);
    $response = $this->withToken(storeApiToken(createStoreUser()))->getJson('/temp/api/v2/products/'.$product->id)
        ->assertOk()->assertJsonPath('data.0.link', finalResourceUrl($product->id, $slug))
        ->assertJsonPath('data.0.name', 'Final product')->assertJsonPath('success', true)->assertJsonPath('status', 200);
    if ($slug === 'd15-final') {
        assertFinalResourcePayload('v2-product', $response->json(), $product);
    }
})->with('final storefront slugs');

test('the authenticated mobile classified endpoint preserves its historical ordinary product URL', function (?string $slug) {
    $category = Category::forceCreate(['name' => 'Classified category', 'slug' => null]);
    $product = CustomerProduct::forceCreate([
        'name' => 'Classified product', 'slug' => $slug, 'user_id' => createStoreUser()->id,
        'category_id' => $category->id, 'tags' => 'final', 'photos' => '',
        'description' => 'Description', 'unit_price' => 2.5,
    ]);
    StoreUser::query()->whereKey($product->user_id)->update(['phone' => '1234567890']);
    $response = $this->withToken(storeApiToken(createStoreUser()))->getJson('/temp/api/v2/classified/product-details/'.$product->id)
        ->assertOk()->assertJsonPath('data.0.link', finalResourceUrl($product->id, $slug))
        ->assertJsonPath('data.0.name', 'Classified product')->assertJsonPath('success', true)->assertJsonPath('status', 200);
    if ($slug === 'd15-final') {
        assertFinalResourcePayload('v2-classified', $response->json(), $product);
    }
})->with('final storefront slugs');

test('the unrouted digital detail collection emits the original absolute URL or fallback', function (?string $slug) {
    $product = finalResourceProduct($slug)->load(['user', 'stocks', 'taxes', 'product_translations']);
    $row = (new DigitalProductDetailCollection(collect([$product])))->resolve()['data']->first();

    expect($row['link'])->toBe(finalResourceUrl($product->id, $slug));
    if ($slug === 'd15-final') {
        assertFinalResourcePayload('v2-digital', $row, $product);
    }
})->with('final storefront slugs');

test('the authenticated auction endpoint emits the original absolute URL or fallback', function (?string $slug) {
    $product = finalResourceProduct($slug);
    // Supply the uninstalled addon's attributes and empty bids in memory only.
    Event::listen('eloquent.retrieved: '.Product::class, function (Product $record): void {
        $record->setAttribute('auction_end_date', 0)->setAttribute('starting_bid', 5)->setRelation('bids', collect());
    });
    $response = $this->withToken(storeApiToken(createStoreUser()))->getJson('/temp/api/v2/auction/products/'.$product->id)
        ->assertOk()->assertJsonPath('data.0.link', finalResourceUrl($product->id, $slug))
        ->assertJsonPath('data.0.name', 'Final product')->assertJsonPath('success', true)->assertJsonPath('status', 200);
    if ($slug === 'd15-final') {
        assertFinalResourcePayload('v2-auction', $response->json(), $product);
    }
})->with('final storefront slugs');

test('customer view rows receive a finished relative product URL and preserve the whole row', function (?string $slug) {
    actingAsStoreUser(createStoreStaff('view_all_customers'));
    $customer = createStoreUser();
    $product = finalResourceProduct($slug);
    DB::table('user_page_views')->insert(['user_id' => $customer->id, 'viewable_type' => Product::class, 'viewable_id' => $product->id, 'viewed_at' => '2026-10-25 12:00:00']);

    $this->get(route('customers.page-views', $customer))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('views.data', 1)->where('views.data.0', [
            'id' => $product->id, 'kind' => 'products', 'name' => 'Final product', 'slug' => $slug,
            'public_url' => finalResourceUrl($product->id, $slug, false),
            'view_count' => 1, 'last_viewed_at' => '2026-10-25 12:00:00',
        ]));
})->with('final storefront slugs');

test('customer category and deleted-product views keep their full unlinked row', function (string $kind) {
    $this->withoutVite();
    actingAsStoreUser(createStoreStaff('view_all_customers'));
    $customer = createStoreUser();
    $product = finalResourceProduct(null);
    $category = Category::forceCreate(['id' => $product->id, 'name' => 'Viewed category', 'slug' => null]);
    if ($kind === 'deleted product') {
        $product->delete();
    }
    DB::table('user_page_views')->insert(['user_id' => $customer->id, 'viewable_type' => $kind === 'category' ? Category::class : Product::class, 'viewable_id' => $product->id, 'viewed_at' => '2026-10-25 12:00:00']);
    $this->get(route('customers.page-views', $customer))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('views.data', 1)->where('views.data.0', [
            'id' => $product->id, 'kind' => $kind === 'category' ? 'categories' : 'products',
            'name' => $kind === 'category' ? 'Viewed category' : null,
            'slug' => null, 'public_url' => null, 'view_count' => 1, 'last_viewed_at' => '2026-10-25 12:00:00',
        ]));
})->with(['category', 'deleted product']);
