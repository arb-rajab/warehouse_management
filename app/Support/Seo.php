<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * What the storefront layout (shop/frontend/layouts/app.blade.php) prints in
 * its <head> for search engines and link previews: the page's title and
 * description, its canonical URL (which og:url repeats), its robots directive
 * and its Open Graph / Twitter tags. The decisions are recorded in
 * .ai/rules/store-backoffice-migration.md §42.
 *
 * A view still sets its own values with the @section names it always used
 * (`meta_title`, `meta_description`, `meta_keywords`, and `meta` for extra
 * tags); `meta_image`, `og_type`, `robots` and `canonical` are optional
 * additions. Every value that is blank falls back to the settings, so a
 * product without a meta title no longer prints an empty <title>.
 *
 * URLs are built from APP_URL (scheme, host, port) plus the request's own path,
 * which already carries the storefront's `/temp` prefix while it is mounted
 * there and follows it when it is removed. Nothing here names the prefix. Every
 * value is returned as plain text: the template escapes it once.
 */
final class Seo
{
    /**
     * Query parameters that change what a storefront page shows. Everything
     * else (utm_*, fbclid, gclid, referral codes, ...) is dropped from the
     * canonical URL and never makes a page a variant.
     */
    public const array CONTENT_QUERY_KEYS = [
        'brand', 'category', 'color', 'condition', 'keyword', 'max_price', 'min_price', 'page',
        'rating', 'search', 'selected_attribute_values', 'selected_categories', 'seller_id', 'sort_by',
    ];

    public const string INDEXABLE = 'index, follow';

    public const string VARIANT = 'noindex, follow';

    public const string PRIVATE = 'noindex, nofollow';

    /** Route names that are public but never belong in a search index. */
    private const array PRIVATE_ROUTE_NAMES = [
        'cart', 'checkout.*', 'compare', 'deliveryboy.login', 'email.verification.confirmation',
        'email_change.callback', 'order_confirmed', 'orders.track', 'password.*', 'register',
        'seller.login', 'shop.login', 'shop.logout', 'terms.activation', 'user.login',
        'user.registration', 'verification.*',
    ];

    /** Route middleware that means "this page belongs to a signed-in account". */
    private const array PRIVATE_MIDDLEWARE = [
        'admin', 'auth', 'customer', 'rep_or_admin', 'seller', 'signed', 'unbanned', 'user', 'verified',
    ];

    public function __construct(private readonly Request $request) {}

    /**
     * scheme://host[:port] of APP_URL, so every host the app answers on names
     * the same canonical site. Falls back to the request's own origin when
     * APP_URL has no host.
     */
    public static function origin(?Request $request = null): string
    {
        $parts = parse_url((string) config('app.url'));

        if (! isset($parts['host'])) {
            return ($request ?? request())->getSchemeAndHttpHost();
        }

        return ($parts['scheme'] ?? 'https').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /** An absolute URL on the canonical origin for a path such as `route($name, $parameters, false)`. */
    public static function absoluteUrl(string $path): string
    {
        return self::origin().'/'.ltrim($path, '/');
    }

    /**
     * The page's canonical URL: the origin, the request path without a
     * trailing slash, and the query parameters that change its content, in a
     * fixed order. `page=1` is the same page as none.
     */
    public function canonicalUrl(): string
    {
        $path = StorefrontUrl::requestPath($this->request) ?? '/'.trim($this->request->getPathInfo(), '/');
        $query = $this->contentQuery();

        return self::origin($this->request).$path.($query === '' ? '' : (str_contains($path, '?') ? '&' : '?').$query);
    }

    /**
     * The robots directive for the page. A view's own `robots` section wins;
     * then pages that belong to an account or to a transaction are
     * `noindex, nofollow`; internal search results and filtered or sorted
     * listing variants are `noindex, follow` (their links to products are
     * still worth following, the duplicate lists are not worth indexing);
     * everything else, including `?page=2` of a plain listing, is indexable.
     */
    public function robots(string $override = ''): string
    {
        if ($override !== '') {
            return $override;
        }

        if ($this->isPrivatePage()) {
            return self::PRIVATE;
        }

        if ($this->request->routeIs('search') || $this->isListingVariant()) {
            return self::VARIANT;
        }

        return self::INDEXABLE;
    }

    /**
     * Everything the layout prints, from the raw @section contents (which are
     * already HTML-escaped, so they are decoded here and escaped once by the
     * template).
     *
     * @param  array{title?: string, description?: string, keywords?: string, image?: string, type?: string, robots?: string, canonical?: string}  $sections
     * @return array{title: string, description: string, keywords: string, canonical: string, robots: string, ogType: string, ogTitle: string, ogDescription: string, image: string|null, twitterCard: string, siteName: string, fbAppId: string}
     */
    public function head(array $sections): array
    {
        $title = self::text($sections['title'] ?? '');
        $description = self::text($sections['description'] ?? '');
        $defaultTitle = self::text((string) get_setting('meta_title'));
        $defaultDescription = self::text((string) get_setting('meta_description'));
        $canonical = self::text($sections['canonical'] ?? '');
        $image = $this->image(self::text($sections['image'] ?? ''));

        return [
            'title' => $title !== '' ? $title : ($defaultTitle !== '' ? $defaultTitle : trim(get_setting('website_name').' | '.get_setting('site_motto'))),
            'description' => $description !== '' ? $description : $defaultDescription,
            'keywords' => self::text($sections['keywords'] ?? '') ?: self::text((string) get_setting('meta_keywords')),
            'canonical' => $canonical === '' ? $this->canonicalUrl() : (str_starts_with($canonical, '/') ? self::absoluteUrl($canonical) : $canonical),
            'robots' => $this->robots(self::text($sections['robots'] ?? '')),
            'ogType' => self::text($sections['type'] ?? '') ?: 'website',
            'ogTitle' => $title !== '' ? $title : $defaultTitle,
            'ogDescription' => $description !== '' ? $description : $defaultDescription,
            'image' => $image,
            'twitterCard' => $image === null ? 'summary' : 'summary_large_image',
            'siteName' => (string) config('app.name'),
            'fbAppId' => (string) config('services.facebook.pixel_id'),
        ];
    }

    /**
     * Decode a @section's content (HTML-escaped by its `{{ }}`) to plain text:
     * entities decoded once, then any tags that were in the value stripped.
     */
    public static function text(?string $html): string
    {
        return Str::squish(strip_tags(html_entity_decode((string) $html, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /**
     * The page's own image, else the settings' one. uploaded_asset() answers
     * with the placeholder for an id that is not an upload, which is not an
     * image worth sharing.
     */
    private function image(string $pageImage): ?string
    {
        $placeholder = static_asset('assets/img/placeholder.jpg');

        foreach ([$pageImage, $this->settingsImage()] as $candidate) {
            if ($candidate !== '' && $candidate !== $placeholder) {
                return $candidate;
            }
        }

        return null;
    }

    private function settingsImage(): string
    {
        $id = get_setting('meta_image');

        return blank($id) ? '' : (string) uploaded_asset($id);
    }

    /** The content-changing query as a string, keys sorted, empty values dropped. */
    private function contentQuery(): string
    {
        $query = Arr::only($this->request->query(), self::CONTENT_QUERY_KEYS);
        $query = self::withoutEmpty($query);

        if (isset($query['page']) && (! is_scalar($query['page']) || (int) $query['page'] <= 1)) {
            unset($query['page']);
        } elseif (isset($query['page'])) {
            $query['page'] = (int) $query['page'];
        }

        ksort($query);

        return http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private static function withoutEmpty(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = self::withoutEmpty($value);
            }

            if ($values[$key] === null || $values[$key] === '' || $values[$key] === []) {
                unset($values[$key]);
            }
        }

        return $values;
    }

    /** Whether the request filters, sorts or searches rather than showing the plain page. */
    private function isListingVariant(): bool
    {
        $variant = Arr::except(Arr::only($this->request->query(), self::CONTENT_QUERY_KEYS), ['page']);

        return self::withoutEmpty($variant) !== [];
    }

    private function isPrivatePage(): bool
    {
        $route = $this->request->route();

        if ($route === null) {
            return false;
        }

        if ($this->request->routeIs(...self::PRIVATE_ROUTE_NAMES)) {
            return true;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && in_array(Str::before($middleware, ':'), self::PRIVATE_MIDDLEWARE, true)) {
                return true;
            }
        }

        return false;
    }
}
