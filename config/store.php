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

];
