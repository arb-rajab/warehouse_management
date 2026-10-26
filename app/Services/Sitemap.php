<?php

namespace App\Services;

use App\Models\Blog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CustomerProduct;
use App\Models\Page;
use App\Models\Product;
use App\Support\Seo;
use App\Support\StorefrontUrl;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The storefront's sitemap: an index at `sitemap` and, under it, one or more
 * `sitemap.show` files per kind of content, each holding at most $chunkSize
 * URLs (the protocol's limit is 50,000 URLs and 50 MB; a URL is about 150
 * bytes, so the count is the binding limit). See .ai/archive/store-backoffice/changes-20261020.md
 * §42.
 *
 * Only what a visitor can open is listed: published and approved products
 * under the storefront listing's own rules (filter_products(), plus the
 * `approved` flag the product page itself requires), published classifieds
 * when the classified feature is on, published blog posts, pages with a slug
 * (a policy page at its policy route, not at its slug), the home page and the
 * three index pages. Every URL is the canonical one: APP_URL's origin plus the
 * route's path, so it follows the storefront's prefix.
 *
 * `<lastmod>` is printed where `updated_at` is reliable. It is NOT for
 * products: products:sync upserts every product in the feed each hour, which
 * bumps every `updated_at`, so the date would claim all of them changed at the
 * last sync.
 *
 * Cache (store: the app default; lifetime: TTL, one hour; nothing is evicted
 * by hand, a change shows within the hour):
 *   sitemap.counts          array<string, int> URLs per source
 *   sitemap.{source}.{page} the rendered XML of one file
 * The index is rebuilt from `sitemap.counts` on each request. A page number
 * outside 1..ceil(count / chunk) is refused before any key is written, so the
 * URL space cannot be used to fill the cache.
 */
class Sitemap
{
    public const int MAX_URLS_PER_FILE = 50000;

    public const int TTL = 3600;

    public const string COUNTS_KEY = 'sitemap.counts';

    /** The sources in the order the index lists them. */
    private const array SOURCES = ['static', 'pages', 'categories', 'brands', 'products', 'blog', 'classifieds'];

    /** The named route of one item, per source that lists rows. */
    private const array ROUTES = [
        'pages' => 'custom-pages.show_custom_page',
        'categories' => 'products.category',
        'brands' => 'products.brand',
        'products' => 'product',
        'blog' => 'blog.details',
        'classifieds' => 'customer.product',
    ];

    public function __construct(private readonly int $chunkSize = self::MAX_URLS_PER_FILE) {}

    /** The `<sitemapindex>` document. */
    public function index(): string
    {
        $entries = '';

        foreach ($this->counts() as $source => $count) {
            for ($page = 1; $page <= $this->pageCount($count); $page++) {
                $entries .= '  <sitemap><loc>'.$this->escape(Seo::absoluteUrl(route('sitemap.show', [$source, $page], false))).'</loc></sitemap>'."\n";
            }
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n".$entries.'</sitemapindex>'."\n";
    }

    /**
     * One `<urlset>` file, or null for a source or page that does not exist.
     */
    public function urlset(string $source, int $page): ?string
    {
        $count = $this->counts()[$source] ?? 0;

        if ($page < 1 || $page > $this->pageCount($count)) {
            return null;
        }

        return Cache::remember("sitemap.{$source}.{$page}", self::TTL, fn (): string => $this->render($source, $page));
    }

    /**
     * URLs per non-empty source.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        return Cache::remember(self::COUNTS_KEY, self::TTL, function (): array {
            $counts = [];

            foreach (self::SOURCES as $source) {
                $count = $this->count($source);

                if ($count > 0) {
                    $counts[$source] = $count;
                }
            }

            return $counts;
        });
    }

    private function pageCount(int $urls): int
    {
        return (int) ceil($urls / $this->chunkSize);
    }

    private function count(string $source): int
    {
        if ($source === 'static') {
            return count($this->staticEntries());
        }

        return $this->query($source)?->count() ?? 0;
    }

    private function render(string $source, int $page): string
    {
        $entries = '';

        foreach ($this->entries($source, $page) as [$path, $lastModified]) {
            $entries .= '  <url><loc>'.$this->escape(Seo::absoluteUrl($path)).'</loc>'
                .($lastModified === null ? '' : '<lastmod>'.$this->escape($lastModified).'</lastmod>')."</url>\n";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n".$entries.'</urlset>'."\n";
    }

    /**
     * @return iterable<array{0: string, 1: string|null}> path and lastmod
     */
    private function entries(string $source, int $page): iterable
    {
        if ($source === 'static') {
            yield from array_slice($this->staticEntries(), ($page - 1) * $this->chunkSize, $this->chunkSize);

            return;
        }

        $path = $this->pathFor($source);
        $withLastModified = $source !== 'products';

        // Explicit columns, one query per file, rows streamed rather than hydrated.
        foreach ($this->query($source)->forPage($page, $this->chunkSize)->cursor() as $row) {
            yield [$path($row), $withLastModified ? $this->lastModified($row->updated_at ?? null) : null];
        }
    }

    /**
     * The home page, the three index pages and the policy pages that exist.
     *
     * @return list<array{0: string, 1: string|null}>
     */
    private function staticEntries(): array
    {
        $paths = [route('home', [], false), route('brands.all', [], false), route('categories.all', [], false), route('blog', [], false)];

        foreach (Page::query()->whereIn('type', array_keys(Page::POLICY_ROUTES))->orderBy('id')->pluck('type') as $type) {
            $paths[] = route(Page::POLICY_ROUTES[$type], [], false);
        }

        return array_map(fn (string $path): array => [$path, null], array_values(array_unique($paths)));
    }

    /** @return (Closure(\stdClass): string) */
    private function pathFor(string $source): Closure
    {
        $route = self::ROUTES[$source];

        return fn (\stdClass $row): string => isset(StorefrontUrl::PARAMETERS[$route])
            ? StorefrontUrl::forRow($route, $row->slug, $row->id, false)
            : route($route, [$row->slug], false);
    }

    /**
     * The visible rows of a source, in id order, as the base query builder
     * (no model eager loads, no hydration).
     */
    private function query(string $source): ?\Illuminate\Database\Query\Builder
    {
        $builder = match ($source) {
            'pages' => Page::query()
                ->select(['id', 'slug', 'updated_at'])
                ->whereNotIn('type', [...array_keys(Page::POLICY_ROUTES), 'home_page']),
            'categories' => Category::query()
                ->select(['id', 'slug', 'updated_at'])
                ->where('name', '!=', 'Uncategorized'),
            'brands' => Brand::query()->select(['id', 'slug', 'updated_at']),
            'products' => filter_products(Product::query())
                ->select(['id', 'slug'])
                ->where('approved', 1),
            'blog' => Blog::query()
                ->select(['id', 'slug', 'updated_at'])
                ->where('status', 1),
            'classifieds' => get_setting('classified_product') == 1
                ? CustomerProduct::query()->select(['id', 'slug', 'updated_at'])->where('published', 1)->where('status', 1)
                : null,
            default => null,
        };

        return $builder instanceof Builder
            ? $builder->whereNotNull('slug')->where('slug', '!=', '')->orderBy('id')->toBase()
            : null;
    }

    private function lastModified(?string $updatedAt): ?string
    {
        return blank($updatedAt) ? null : Carbon::parse($updatedAt)->toAtomString();
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
