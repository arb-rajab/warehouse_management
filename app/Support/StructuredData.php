<?php

namespace App\Support;

use App\Models\Blog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\StoreUser;
use Illuminate\Support\Str;

/**
 * The schema.org JSON-LD the storefront prints in <head> (shop/frontend/
 * partials/json_ld.blade.php), one array per <script> block. Each builder
 * returns only what the page really has: no rating without reviews, no author
 * the blog does not record, no price the visitor is not shown. The decisions
 * are recorded in store-backoffice-migration.md §42.
 *
 * encode() is the only way a schema reaches the page. Its HEX flags turn `<`,
 * `>`, `&`, `'` and `"` into \u escapes, so no product name can close the
 * <script> element or break out of the JSON.
 */
final class StructuredData
{
    private const int DESCRIPTION_LIMIT = 500;

    private const int IMAGE_LIMIT = 5;

    /** @param  array<string, mixed>  $schema */
    public static function encode(array $schema): string
    {
        return json_encode(
            $schema,
            JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
        );
    }

    /** User- or admin-written HTML as plain text: sanitized, stripped, entities decoded, whitespace squeezed, truncated. */
    public static function plainText(?string $html, int $limit = self::DESCRIPTION_LIMIT): string
    {
        $text = Str::squish(html_entity_decode(strip_tags(clean_html($html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return Str::limit($text, $limit, '...');
    }

    /**
     * The home page: the site (with its search, which is a real GET route) and
     * the organization behind it.
     *
     * @return list<array<string, mixed>>
     */
    public static function home(): array
    {
        $home = self::url(route('home', [], false));

        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => self::siteName(),
                'url' => $home,
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => ['@type' => 'EntryPoint', 'urlTemplate' => self::url(route('search', [], false)).'?keyword={search_term_string}'],
                    'query-input' => 'required name=search_term_string',
                ],
            ],
            ['@context' => 'https://schema.org', ...self::organization()],
        ];
    }

    /**
     * A product page: the Product and its breadcrumb.
     *
     * The price is offered only to a visitor the page shows it to (a signed-in,
     * admin-verified account): the storefront hides every price from everyone
     * else, and structured data must not publish what the page withholds. A
     * product with variants has a price range the page prints as such, so it
     * gets no single price either.
     *
     * @return list<array<string, mixed>>
     */
    public static function product(Product $product): array
    {
        $url = self::url(route('product', [$product->slug], false));
        $description = self::plainText($product->description) ?: self::plainText($product->meta_description);

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'url' => $url,
            'image' => self::productImages($product),
            'description' => $description,
            'sku' => self::sku($product),
            'brand' => $product->brand === null ? null : ['@type' => 'Brand', 'name' => $product->brand->name],
            'offers' => self::offer($product, $url),
            'aggregateRating' => self::aggregateRating($product),
        ];

        $trail = [['name' => translate('Home'), 'url' => self::url(route('home', [], false))]];

        foreach (self::categoryTrail($product->category) as $category) {
            $trail[] = ['name' => (string) $category->getTranslation('name'), 'url' => self::url(route('products.category', [$category->slug], false))];
        }

        $trail[] = ['name' => $product->name, 'url' => $url];

        return [self::filled($schema), self::breadcrumbs($trail)];
    }

    /**
     * A category or brand listing's breadcrumb; nothing for search or the plain listing.
     *
     * @return list<array<string, mixed>>
     */
    public static function listing(?int $categoryId, ?int $brandId): array
    {
        $trail = [['name' => translate('Home'), 'url' => self::url(route('home', [], false))]];

        if ($categoryId !== null) {
            foreach (self::categoryTrail(Category::query()->find($categoryId)) as $category) {
                $trail[] = ['name' => (string) $category->getTranslation('name'), 'url' => self::url(route('products.category', [$category->slug], false))];
            }
        } elseif ($brandId !== null && ($brand = Brand::query()->find($brandId)) !== null) {
            $trail[] = ['name' => translate('Brands'), 'url' => self::url(route('brands.all', [], false))];
            $trail[] = ['name' => (string) $brand->getTranslation('name'), 'url' => self::url(route('products.brand', [$brand->slug], false))];
        }

        return count($trail) > 1 ? [self::breadcrumbs($trail)] : [];
    }

    /**
     * A blog post. The blog records no author, so there is none to name; the
     * organization is the publisher.
     *
     * @return array<string, mixed>
     */
    public static function blogPost(Blog $blog): array
    {
        $url = self::url(route('blog.details', [$blog->slug], false));
        $image = self::realImage($blog->meta_img ?: $blog->banner);

        return self::filled([
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => Str::limit((string) $blog->title, 110, ''),
            'description' => self::plainText($blog->meta_description ?: $blog->short_description),
            'datePublished' => $blog->created_at?->toAtomString(),
            'dateModified' => ($blog->updated_at ?? $blog->created_at)?->toAtomString(),
            'image' => $image === null ? null : [$image],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
            'publisher' => self::organization(),
        ]);
    }

    /**
     * @param  list<array{name: string, url: string}>  $trail
     * @return array<string, mixed>
     */
    private static function breadcrumbs(array $trail): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $crumb, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ], $trail, array_keys($trail)),
        ];
    }

    /** @return array<string, mixed> */
    private static function organization(): array
    {
        $logo = blank(get_setting('header_logo')) ? null : self::realImage(get_setting('header_logo'));

        return self::filled([
            '@type' => 'Organization',
            'name' => self::siteName(),
            'url' => self::url(route('home', [], false)),
            'logo' => $logo,
        ]);
    }

    /** @return array<string, mixed>|null */
    private static function offer(Product $product, string $url): ?array
    {
        if (! self::priceIsShown() || $product->variant_product) {
            return null;
        }

        $quantity = $product->digital == 1 ? 1 : $product->stocks->sum('qty');

        return [
            '@type' => 'Offer',
            'url' => $url,
            'priceCurrency' => get_system_default_currency()->code,
            'price' => number_format((float) home_discounted_base_price($product, false), 2, '.', ''),
            'availability' => $quantity > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        ];
    }

    /**
     * Only when the page shows its reviews and there are some; the rating is
     * the mean of those reviews, the same ones the page counts.
     *
     * @return array<string, mixed>|null
     */
    private static function aggregateRating(Product $product): ?array
    {
        if (get_setting('Product_Reviews_Section') != 1 || $product->reviews->isEmpty()) {
            return null;
        }

        return [
            '@type' => 'AggregateRating',
            'ratingValue' => round($product->reviews->avg('rating'), 1),
            'reviewCount' => $product->reviews->count(),
            'bestRating' => 5,
            'worstRating' => 1,
        ];
    }

    /** The page prints a price only to a signed-in, admin-verified account (product_details/details.blade.php). */
    private static function priceIsShown(): bool
    {
        $user = auth()->user();

        return $user instanceof StoreUser && (bool) $user->admin_verified;
    }

    private static function sku(Product $product): ?string
    {
        $sku = $product->stocks->pluck('sku')->first(fn ($candidate) => filled($candidate));

        return $sku ?? (blank($product->mat_id) ? null : (string) $product->mat_id);
    }

    /** @return list<string> */
    private static function productImages(Product $product): array
    {
        $ids = [...explode(',', (string) $product->photos), $product->thumbnail_img, $product->meta_img];
        $images = [];

        foreach ($ids as $id) {
            $image = self::realImage($id);

            if ($image !== null) {
                $images[$image] = $image;
            }
        }

        return array_slice(array_values($images), 0, self::IMAGE_LIMIT);
    }

    /** The upload's URL, or null for a blank id or one that is not an upload (uploaded_asset() answers with the placeholder). */
    private static function realImage(mixed $id): ?string
    {
        if (blank($id)) {
            return null;
        }

        $url = uploaded_asset($id);

        return $url === static_asset('assets/img/placeholder.jpg') ? null : $url;
    }

    /**
     * The category and its ancestors, root first.
     *
     * @return list<Category>
     */
    private static function categoryTrail(?Category $category): array
    {
        $trail = [];

        for ($depth = 0; $category !== null && $depth < 8; $depth++) {
            array_unshift($trail, $category);
            $category = $category->parent_id ? Category::query()->find($category->parent_id) : null;
        }

        return $trail;
    }

    private static function siteName(): string
    {
        return (string) (get_setting('website_name') ?: config('app.name'));
    }

    private static function url(string $path): string
    {
        return Seo::absoluteUrl($path);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private static function filled(array $schema): array
    {
        return array_filter($schema, fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }
}
