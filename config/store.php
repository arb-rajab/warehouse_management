<?php

use App\Http\Controllers\Api\V2\BkashController as ApiBkashController;
use App\Http\Controllers\Api\V2\IyzicoController as ApiIyzicoController;
use App\Http\Controllers\Api\V2\NagadController as ApiNagadController;
use App\Http\Controllers\Api\V2\SslCommerzController as ApiSslCommerzController;
use App\Http\Controllers\Payment\AamarpayController;
use App\Http\Controllers\Payment\BkashController;
use App\Http\Controllers\Payment\IyzicoController;
use App\Http\Controllers\Payment\NagadController;
use App\Http\Controllers\Payment\NgeniusController;
use App\Http\Controllers\Payment\PayhereController;
use App\Http\Controllers\Payment\SslcommerzController;
use App\Http\Controllers\Payment\VoguepayController;

return [

    /*
    |--------------------------------------------------------------------------
    | Store App Asset Base URL
    |--------------------------------------------------------------------------
    |
    | The store app owns the `uploads` table this app reads, and stores only a
    | relative file path in `uploads.file_name` — the absolute URL is built by
    | whichever app serves the file. This is the store app's public base URL,
    | used by Product::imageUrl(). When an upload carries its own
    | `external_link` (a CDN or S3 URL), that wins and this is not consulted.
    |
    | A null value means product images resolve to null rather than to a
    | broken relative URL. See .ai/rules/shared-database.md.
    |
    */

    'asset_base_url' => env('STORE_ASSET_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Extra Product Image Hosts
    |--------------------------------------------------------------------------
    |
    | Hosts, besides the asset base URL's own, that Admin\ProductController::
    | image() may download product images from (an upload's `external_link`
    | CDN/S3 host). Comma-separated in STORE_IMAGE_HOSTS. Any other host is
    | refused, so a crafted `external_link` can't make this app fetch an
    | internal address.
    |
    */

    'image_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('STORE_IMAGE_HOSTS', ''))))),

    /*
    |--------------------------------------------------------------------------
    | Store App Product Sync URL
    |--------------------------------------------------------------------------
    |
    | The full REST endpoint `products:sync` (SyncProductsCommand) polls on a
    | schedule to pull product name/Arabic name/active-status updates into this
    | app's `products` table. The store's API key is embedded in the URL
    | path itself, which is why this is one opaque env value rather than a
    | base URL plus a separate key. See .ai/rules/shared-database.md.
    |
    */

    'products_sync_url' => env('STORE_PRODUCTS_SYNC_URL'),

    /*
    | The legacy back office's demo switch (`DEMO_MODE=On`): the own-profile
    | update refuses to write while it is on.
    */
    'demo_mode' => env('DEMO_MODE') === 'On',

    /*
    |--------------------------------------------------------------------------
    | Enabled Payment Gateways
    |--------------------------------------------------------------------------
    |
    | The legacy store's hand-rolled payment gateways whose routes (checkout and
    | callbacks) answer at all, as a comma-separated list in STORE_PAYMENT_GATEWAYS:
    | aamarpay, bkash, iyzico, nagad, ngenius, payhere, sslcommerz, voguepay.
    | Empty by default: every other gateway route is a 404
    | (EnsurePaymentGatewayEnabled). Cash on delivery and the manual payment
    | methods are not gateways.
    |
    | Enabling one is not enough on its own. Most of these still read their
    | credentials with env(), which is null once config is cached (only PayHere
    | has been moved to config/services.php), and each callback has to verify
    | the gateway's signature before it marks anything paid. See
    | .ai/rules/legacy-albaraka-holland.md, section 0.3.
    |
    */

    'enabled_payment_gateways' => array_values(array_filter(array_map('trim', explode(',', (string) env('STORE_PAYMENT_GATEWAYS', ''))))),

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Controllers
    |--------------------------------------------------------------------------
    |
    | Which controllers belong to which gateway, so RouteServiceProvider can put
    | `payment.gateway:<name>` on every route that reaches one.
    |
    */

    'payment_gateway_controllers' => [
        'aamarpay' => [AamarpayController::class],
        'bkash' => [BkashController::class, ApiBkashController::class],
        'iyzico' => [IyzicoController::class, ApiIyzicoController::class],
        'nagad' => [NagadController::class, ApiNagadController::class],
        'ngenius' => [NgeniusController::class],
        'payhere' => [PayhereController::class],
        'sslcommerz' => [SslcommerzController::class, ApiSslCommerzController::class],
        'voguepay' => [VoguepayController::class],
    ],

    /*
    |--------------------------------------------------------------------------
    | Content-Security-Policy for the Store's Blade Pages
    |--------------------------------------------------------------------------
    |
    | The store's server-rendered pages run inline `<script>` blocks and load
    | jQuery plugins, fonts, maps and embeds from third-party hosts, which
    | config/secure-headers.php's strict policy blocks. HandleStoreBladePages
    | swaps these directives in for a Blade page only; Inertia pages, store
    | ones included, keep the strict policy. Each entry replaces that whole
    | directive. Shrink this only as the remaining store Blade consumers permit;
    | retained storefront, seller and authentication pages still use it after
    | the back office moves (.ai/rules/store-backoffice-migration.md §2.5).
    |
    */

    'blade_csp' => [
        'script-src' => [
            'self' => true,
            'unsafe-inline' => true,
            'schemes' => ['https:'],
        ],
        'style-src' => [
            'self' => true,
            'unsafe-inline' => true,
            'schemes' => ['https:'],
        ],
        'font-src' => [
            'self' => true,
            'schemes' => ['https:', 'data:'],
        ],
        'img-src' => [
            'self' => true,
            'schemes' => ['https:', 'data:', 'blob:'],
        ],
        'frame-src' => [
            'self' => true,
            'schemes' => ['https:'],
        ],
        'connect-src' => [
            'self' => true,
            'schemes' => ['https:'],
        ],
    ],

];
