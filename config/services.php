<?php

use App\Models\StoreUser;

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Merged from the legacy store's config/services.php (see
    // .ai/rules/legacy-albaraka-holland.md sections 0.3 and 1). 'stripe' and
    // 'paytm-wallet' are inert: both gateway packages were removed in the
    // legacy repo's own phase-1 pruning, and nothing here reads these keys.

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'stripe' => [
        'model' => StoreUser::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('APP_URL').'/social-login/google/callback',
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('APP_URL').'/social-login/facebook/callback',
        'pixel_id' => env('FACEBOOK_PIXEL_ID'),
        'page_id' => env('FACEBOOK_PAGE_ID'),
        'app_id' => env('FACEBOOK_APP_ID'),
    ],

    'twitter' => [
        'client_id' => env('TWITTER_CLIENT_ID'),
        'client_secret' => env('TWITTER_CLIENT_SECRET'),
        'redirect' => env('APP_URL').'/social-login/twitter/callback',
    ],

    'paytm-wallet' => [
        'env' => env('PAYTM_ENVIRONMENT'),
        'merchant_id' => env('PAYTM_MERCHANT_ID'),
        'merchant_key' => env('PAYTM_MERCHANT_KEY'),
        'merchant_website' => env('PAYTM_MERCHANT_WEBSITE'),
        'channel' => env('PAYTM_CHANNEL'),
        'industry_type' => env('PAYTM_INDUSTRY_TYPE'),
    ],

    // Was hardcoded to 'albarakabv-store' with no way to override; kept as
    // the same default, now overridable, per the audit's section 0.3.
    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID', 'albarakabv-store'),
        'server_key' => env('FCM_SERVER_KEY'),
        'firebase_credentials' => env('FIREBASE_CREDENTIALS'),
        'firebase_project' => env('FIREBASE_PROJECT'),
    ],

    // Read by the storefront's pages and the services that call these APIs. They
    // are config because env() outside config/ returns null once config is cached.
    'recaptcha' => [
        'site_key' => env('CAPTCHA_KEY'),
    ],

    'google_maps' => [
        'key' => env('MAP_API_KEY'),
    ],

    'google_analytics' => [
        'tracking_id' => env('TRACKING_ID'),
    ],

    'opencage' => [
        'key' => env('OPENCAGE_API_KEY'),
    ],

    'ip_geolocation' => [
        'key' => env('IP_GEO_LOCATION_KEY'),
        // Seconds. The request had no limit at all, because the key was always null.
        'timeout' => (int) env('IP_GEO_LOCATION_TIMEOUT_SECONDS', 5),
    ],

    'apple' => [
        'client_id' => env('SIGN_IN_WITH_APPLE_CLIENT_ID'),
        'client_secret' => env('SIGN_IN_WITH_APPLE_CLIENT_SECRET'),
        'redirect' => env('SIGN_IN_WITH_APPLE_REDIRECT'),
    ],

    // The legacy store's ERP ("otajer"): the host its console commands and
    // UserService call, and the API token they append to the path. The token was
    // once also committed as a literal in AllProductsCommand, so the one that
    // was in the repository is compromised and has to be reissued.
    // PayHere's credentials. The notify handlers verify the gateway's signature
    // with `secret`, so they refuse every notification while it is empty.
    'payhere' => [
        'merchant_id' => env('PAYHERE_MERCHANT_ID'),
        'secret' => env('PAYHERE_SECRET'),
        'currency' => env('PAYHERE_CURRENCY'),
    ],

    'otajer' => [
        'url' => env('OTAJER_URL') ?: 'https://stores.otajer.com',
        'token' => env('OTAJER_TOKEN'),
    ],

];
