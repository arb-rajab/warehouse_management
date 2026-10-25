<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\FlashDeal;
use App\Models\FlashDealProduct;
use App\Models\Product;
use App\Models\Review;
use App\Models\Wishlist;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->withoutVite();
    seedStoreCurrency();
});

/** @param array<string, mixed> $attributes */
function remainingBladeProduct(?string $slug, array $attributes = []): Product
{
    return createStoreProduct(['name' => 'D15 remaining product', 'serial' => 'd15-'.Str::uuid(), 'slug' => $slug, 'category_id' => null, 'brand_id' => null, 'thumbnail_img' => null, ...$attributes])->fresh();
}

function remainingBladeUrl(Product|Brand $record, ?string $slug): string
{
    $path = $record instanceof Brand ? '/temp/brand/' : '/temp/product/';

    return $slug === null || $slug === ''
        ? config('app.url').$path.'~id-'.$record->id.'?catalog_id='.$record->id
        : route($record instanceof Brand ? 'products.brand' : 'product', $slug);
}

function assertRemainingBladeLink(string $html, string $url): void
{
    $links = htmlXPath($html)->query('//a[@href]');
    $addresses = [];
    foreach ($links as $link) {
        $addresses[] = $link->getAttribute('href');
    }
    expect($addresses)->toContain($url);
}

dataset('remaining Blade slugs', ['null' => null, 'blank' => '', 'normal' => 'd15-normal', 'zero' => '0']);

test('remaining storefront pages retain exact product links', function (string $area, ?string $slug) {
    $customer = createStoreUser('customer');
    actingAsStoreUser($customer);
    $product = remainingBladeProduct($slug, ['digital' => $area === 'digital history' ? 1 : 0]);
    if ($area === 'dashboard') {
        Wishlist::forceCreate(['user_id' => $customer->id, 'product_id' => $product->id]);
        $path = route('dashboard');
    } elseif ($area === 'digital history') {
        $line = orderStoreProduct($customer, $product);
        $line->forceFill(['product_serial' => $product->serial])->save();
        $path = route('digital_purchase_history.index');
    } else {
        $deal = FlashDeal::forceCreate(['title' => 'D15 flash', 'slug' => 'd15-flash', 'status' => 1, 'end_date' => now()->addDay()->timestamp, 'text_color' => 'dark']);
        FlashDealProduct::forceCreate(['flash_deal_id' => $deal->id, 'product_id' => $product->id]);
        $path = route('flash-deal-details', $deal->slug);
    }
    $before = $product->getRawOriginal();
    $response = $this->get($path)->assertOk();
    assertRemainingBladeLink($response->getContent(), remainingBladeUrl($product, $slug));
    expect($product->fresh()->getRawOriginal())->toBe($before);
})->with(['dashboard', 'digital history', 'flash deal'])->with('remaining Blade slugs');

test('remaining storefront fragments retain exact product links', function (string $area, ?string $slug) {
    $product = remainingBladeProduct($slug, ['featured' => 1]);
    if ($area === 'cart') {
        $displayed = remainingBladeProduct('cart-displayed');
        $view = 'shop.frontend.partials.addedToCart';
        $data = ['product' => $displayed, 'offer_message' => 'D15 cart'];
    } else {
        $owner = createStoreUser('admin');
        $coupon = Coupon::forceCreate(['user_id' => $owner->id, 'type' => 'product_base', 'code' => 'D15', 'discount' => 1, 'discount_type' => 'amount', 'start_date' => now()->timestamp, 'end_date' => now()->addDay()->timestamp, 'details' => json_encode([['product_id' => $product->id]])]);
        $view = 'shop.frontend.partials.coupon_box';
        $data = ['coupon' => $coupon, 'key' => 0];
    }
    Route::middleware('web')->get('/temp/__d15/fragment', fn () => view($view, $data));
    $response = $this->get('/temp/__d15/fragment')->assertOk();
    assertRemainingBladeLink($response->getContent(), remainingBladeUrl($product, $slug));
})->with(['cart', 'coupon'])->with('remaining Blade slugs');

test('the retained digital detail view keeps brand top and related addresses', function (?string $slug) {
    $category = Category::forceCreate(['name' => 'D15 digital', 'slug' => 'digital', 'parent_id' => 0]);
    $brand = Brand::forceCreate(['name' => 'D15 brand', 'slug' => $slug]);
    $displayed = remainingBladeProduct($slug, ['category_id' => $category->id, 'brand_id' => $brand->id, 'digital' => 1]);
    $related = remainingBladeProduct($slug, ['category_id' => $category->id, 'user_id' => $displayed->user_id, 'digital' => 1]);
    Route::middleware('web')->get('/temp/__d15/digital', fn () => view('shop.frontend.digital_product_details', [
        'detailedProduct' => $displayed->load(['stocks', 'reviews', 'brand', 'user']),
        'product_queries' => collect(), 'total_query' => 0, 'reviews' => collect(), 'review_status' => 0, 'variations' => null,
    ]));
    $response = $this->get('/temp/__d15/digital')->assertOk();
    assertRemainingBladeLink($response->getContent(), remainingBladeUrl($related, $slug));
    assertRemainingBladeLink($response->getContent(), remainingBladeUrl($brand, $slug));
    if ($slug !== null && $slug !== '') {
        assertRemainingBladeLink($response->getContent(), route('shop.visit', $slug));
    }
    expect(htmlXPath($response)->evaluate('string(//textarea[@name="message"])'))->toBe(remainingBladeUrl($displayed, $slug));
})->with('remaining Blade slugs');

test('top selling widget preserves exact normal URLs and its existing slugless exclusion', function () {
    $displayed = remainingBladeProduct('displayed-top');
    remainingBladeProduct('0', ['user_id' => $displayed->user_id]);
    remainingBladeProduct(null, ['user_id' => $displayed->user_id]);
    remainingBladeProduct('', ['user_id' => $displayed->user_id]);
    $html = view('shop.frontend.product_details.top_selling_products', ['detailedProduct' => $displayed])->render();
    assertRemainingBladeLink($html, route('product', '0'));
    expect($html)->not->toContain('~id-');
});

test('seller pages retain exact public product addresses', function (string $area, ?string $slug) {
    $seller = sellerAccount();
    DB::table('shops')->where('user_id', $seller->id)->update(['verification_status' => 1]);
    storeSetting('vendor_system_activation', 1);
    $product = remainingBladeProduct($slug, ['user_id' => $seller->id, 'added_by' => 'seller', 'digital' => $area === 'digital list' ? 1 : 0]);
    $path = match ($area) {
        'dashboard' => route('seller.dashboard'),
        'physical list' => route('seller.products'),
        'digital list' => route('seller.digitalproducts'),
        'reviews' => route('seller.reviews'),
        'order', 'auction order' => '',
    };
    if ($area === 'reviews') {
        $review = Review::forceCreate(['product_id' => $product->id, 'user_id' => createStoreUser()->id, 'rating' => 4, 'comment' => 'D15 review', 'status' => 1]);
        Route::middleware(['web', 'auth:store'])->get('/temp/__d15/reviews', fn () => view('shop.seller.reviews', ['reviews' => Review::whereKey($review->id)->paginate(9)]));
        $path = '/temp/__d15/reviews';
    }
    if (str_contains($area, 'order')) {
        addManualPaymentColumnsToOrders();
        if ($area === 'auction order') {
            $product->forceFill(['auction_product' => 1])->save();
        }
        $line = orderStoreProduct(createStoreUser(), $product);
        $line->forceFill(['product_serial' => $product->serial])->save();
        $line->order->forceFill(['seller_id' => $seller->id, 'shipping_address' => json_encode(['name' => 'D15 Buyer', 'email' => 'buyer@example.test', 'phone' => '123', 'address' => 'Street', 'city' => 'City', 'country' => 'Netherlands', 'postal_code' => '1234AB'])])->save();
        $path = route('seller.orders.show', encrypt($line->order_id));
    }
    $before = $product->fresh()->getRawOriginal();
    $response = $this->get($path)->assertOk();
    assertRemainingBladeLink($response->getContent(), remainingBladeUrl($product, $slug));
    expect($product->fresh()->getRawOriginal())->toBe($before);
})->with(['dashboard', 'physical list', 'digital list', 'reviews', 'order', 'auction order'])->with('remaining Blade slugs');
