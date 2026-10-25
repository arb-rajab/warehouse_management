<?php

use Illuminate\Support\Facades\File;

/**
 * This inventory includes guarded and commented builders, so re-enabling old
 * markup cannot silently reintroduce direct slug URL generation. The explicit
 * file:line list is a ratchet: remove an entry when migrating that call.
 *
 * @return list<int>
 */
function rawStorefrontRouteLines(string $source): array
{
    preg_match_all('/\b(?:route|to_route)\s*\(\s*([\'"])(?:product|products\.category|products\.brand)\1\s*,|\bpublicProduct\.url\s*\(\s*row\.slug\s*\)/', $source, $matches, PREG_OFFSET_CAPTURE);

    return array_map(fn (array $match): int => substr_count(substr($source, 0, $match[1]), "\n") + 1, $matches[0]);
}

test('storefront route builders use the shared helper except for the exact D15 remainder', function () {
    $actual = [];
    foreach (['app', 'resources', 'public/assets/js'] as $directory) {
        foreach (File::allFiles(base_path($directory)) as $file) {
            if (! in_array($file->getExtension(), ['php', 'js', 'ts', 'vue'], true)) {
                continue;
            }
            foreach (rawStorefrontRouteLines($file->getContents()) as $line) {
                $actual[] = $directory.'/'.$file->getRelativePathname().':'.$line;
            }
        }
    }
    $remaining = [
        'app/Http/Controllers/Store/Admin/DynamicPopupController.php:121',
        'app/Http/Resources/Store/DashboardProductResource.php:27',
        'app/Http/Resources/Store/DigitalProductResource.php:39',
        'app/Http/Resources/Store/ProductListResource.php:71',
        'app/Http/Resources/Store/ReviewResource.php:32',
        'app/Http/Resources/V2/AuctionProductDetailCollection.php:82',
        'app/Http/Resources/V2/ClassifiedProductDetailCollection.php:66',
        'app/Http/Resources/V2/DigitalProductDetailCollection.php:69',
        'app/Http/Resources/V2/ProductDetailCollection.php:156',
        'resources/js/pages/Store/Admin/Customers/Customers/Views.vue:68',
        'resources/views/shop/frontend/digital_product_details.blade.php:160',
        'resources/views/shop/frontend/digital_product_details.blade.php:477',
        'resources/views/shop/frontend/digital_product_details.blade.php:489',
        'resources/views/shop/frontend/digital_product_details.blade.php:637',
        'resources/views/shop/frontend/digital_product_details.blade.php:648',
        'resources/views/shop/frontend/digital_product_details.blade.php:810',
        'resources/views/shop/frontend/flash_deal_details.blade.php:36',
        'resources/views/shop/frontend/flash_deal_details.blade.php:38',
        'resources/views/shop/frontend/partials/addedToCart.blade.php:56',
        'resources/views/shop/frontend/partials/addedToCart.blade.php:66',
        'resources/views/shop/frontend/partials/coupon_box.blade.php:74',
        'resources/views/shop/frontend/product_details/top_selling_products.blade.php:12',
        'resources/views/shop/frontend/product_details/top_selling_products.blade.php:25',
        'resources/views/shop/frontend/user/customer/dashboard.blade.php:263',
        'resources/views/shop/frontend/user/customer/dashboard.blade.php:279',
        'resources/views/shop/frontend/user/digital_purchase_history.blade.php:23',
        'resources/views/shop/seller/dashboard.blade.php:542',
        'resources/views/shop/seller/dashboard.blade.php:561',
        'resources/views/shop/seller/orders/show.blade.php:161',
        'resources/views/shop/seller/orders/show.blade.php:165',
        'resources/views/shop/seller/orders/show.blade.php:174',
        'resources/views/shop/seller/orders/show.blade.php:179',
        'resources/views/shop/seller/orders/show_backup.blade.php:150',
        'resources/views/shop/seller/product/digitalproducts/index.blade.php:82',
        'resources/views/shop/seller/product/products/index.blade.php:95',
        'resources/views/shop/seller/reviews.blade.php:31',
    ];
    sort($actual);
    sort($remaining);

    expect($actual)->toBe($remaining, 'New direct builder, or fixed call still in the D15 remainder. Migrate with StorefrontUrl and shrink this list.');
});

test('the route guard detects multiline PHP, array parameters, resources and client-side builders', function () {
    expect(rawStorefrontRouteLines(<<<'SOURCE'
route('product', $product->slug)
route(
    "products.category",
    ['category_slug' => $category->slug]
)
URL::route('products.brand', $brand->getAttribute('slug'))
route('product', ['slug' => $slug])
publicProduct.url(row.slug)
StorefrontUrl::to($product)
route('product.edit', $product->id)
$this->route('product')
SOURCE))->toBe([1, 2, 6, 7, 8]);
});
