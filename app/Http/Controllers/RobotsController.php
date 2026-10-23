<?php

namespace App\Http\Controllers;

use App\Support\Seo;
use Illuminate\Http\Response;

/**
 * GET /robots.txt, generated rather than a file in public/ so the Disallow
 * paths follow the routes (the storefront's temporary /temp prefix included)
 * and the Sitemap line follows APP_URL. A static public/robots.txt would be
 * served by the web server before this route is reached, so there is none.
 *
 * The storefront stays open. Disallowed: the two back offices, the account,
 * cart and checkout pages and the seller area. Internal search results and
 * filtered listings are deliberately NOT disallowed: they carry
 * `noindex, follow`, which a crawler can only see if it may fetch the page.
 * See store-backoffice-migration.md §42.
 */
class RobotsController extends Controller
{
    /** Named routes whose path, and everything below it, crawlers must stay out of. */
    private const array DISALLOWED_ROUTES = [
        'admin.dashboard', 'shop.admin.dashboard', 'password.change', 'health',
        'cart', 'checkout.shipping_info',
        'dashboard', 'profile', 'purchase_history.index', 'digital_purchase_history.index', 'wallet.index',
        'wishlists.index', 'support_ticket.index', 'conversations.index', 'messages.index', 'all-notifications',
        'customer_products.index', 'followed_seller', 'addresses.index', 'product-queries.index', 'payments.index',
    ];

    public function __invoke(): Response
    {
        $paths = array_map(fn (string $name): string => route($name, [], false), self::DISALLOWED_ROUTES);

        // The seller area is a directory: `<prefix>/seller` alone would also
        // match the public /seller-policy page.
        $paths[] = dirname(route('seller.dashboard', [], false)).'/';

        // The API groups sit beside the storefront (`<prefix>/api`) and at the root (`/api`).
        $storefront = rtrim(route('home', [], false), '/');
        $paths[] = $storefront.'/api/';
        $paths[] = '/api/';

        $lines = ['User-agent: *'];

        foreach (array_unique($paths) as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.Seo::absoluteUrl(route('sitemap', [], false));

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
