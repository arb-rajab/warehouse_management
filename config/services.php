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
    // 'paytm-wallet' have no payment flow: both gateway packages were removed in
    // the legacy repo's own phase-1 pruning. Their keys can be stored on the
    // Payment methods page (App\Support\StorePaymentGateways), ready for an
    // integration; nothing charges with them.

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
        // Empty: the store's own currency.
        'currency' => env('STRIPE_CURRENCY'),
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
        // Verifies the widget's token on the server (App\Rules\Recaptcha);
        // with reCAPTCHA on and this empty, the forms that show it refuse.
        'secret_key' => env('CAPTCHA_SECRET'),
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

    // iyzico's credentials, read by App\Services\IyzicoPayments for the
    // storefront's and the mobile API's checkout.
    'iyzico' => [
        'api_key' => env('IYZICO_API_KEY'),
        'secret_key' => env('IYZICO_SECRET_KEY'),
        'currency' => env('IYZICO_CURRENCY_CODE', 'TRY'),
        // iyzico requires an identity number for every buyer. The store has none for its customers, so
        // this placeholder (iyzico's documented value for buyers with no Turkish identity number) is sent.
        'identity_number' => env('IYZICO_IDENTITY_NUMBER', '11111111111'),
    ],

    // The currency SSLCommerz is charged in. Amounts are converted to it from the
    // store's default currency (App\Services\GatewayCurrency), so the store needs
    // an active `currencies` row with this code and a rate.
    'sslcommerz' => [
        'currency' => env('SSLCZ_CURRENCY', 'BDT'),
        'store_id' => env('SSLCZ_STORE_ID'),
        'store_password' => env('SSLCZ_STORE_PASSWD'),
    ],

    // Every gateway credential is read through App\Support\StorePaymentGateways::get(), which returns the
    // values saved on the Payment methods page and falls back to these (never env() outside config/:
    // production runs `artisan config:cache`, after which env() returns null).
    'bkash' => [
        'currency' => env('BKASH_CURRENCY', 'BDT'),
        'app_key' => env('BKASH_CHECKOUT_APP_KEY'),
        'app_secret' => env('BKASH_CHECKOUT_APP_SECRET'),
        'username' => env('BKASH_CHECKOUT_USER_NAME'),
        'password' => env('BKASH_CHECKOUT_PASSWORD'),
    ],

    // Nagad charges in BDT only; the amount is converted to it from the store's currency.
    'nagad' => [
        'currency' => env('NAGAD_CURRENCY', 'BDT'),
    ],

    'aamarpay' => [
        'currency' => env('AAMARPAY_CURRENCY', 'BDT'),
        'store_id' => env('AAMARPAY_STORE_ID'),
        'signature_key' => env('AAMARPAY_SIGNATURE_KEY'),
    ],

    // N-Genius runs against its UAT test environment unless NGENIUS_MODE=live. The live URLs below are
    // N-Genius's documented production endpoints; check them against the provider's documentation, or
    // override them with the NGENIUS_*_URL variables, before going live.
    'ngenius' => [
        'mode' => env('NGENIUS_MODE', 'uat'),
        'currency' => env('NGENIUS_CURRENCY', 'AED'),
        'api_key' => env('NGENIUS_API_KEY'),
        'outlet_id' => env('NGENIUS_OUTLET_ID'),
        'urls' => [
            'uat' => [
                'identity' => env('NGENIUS_UAT_IDENTITY_URL', 'https://identity-uat.ngenius-payments.com/auth/realms/ni/protocol/openid-connect/token'),
                'gateway' => env('NGENIUS_UAT_GATEWAY_URL', 'https://api-gateway-uat.ngenius-payments.com'),
            ],
            'live' => [
                'identity' => env('NGENIUS_LIVE_IDENTITY_URL', 'https://identity.ngenius-payments.com/auth/realms/ni/protocol/openid-connect/token'),
                'gateway' => env('NGENIUS_LIVE_GATEWAY_URL', 'https://api-gateway.ngenius-payments.com'),
            ],
        ],
    ],

    // Empty currency: VoguePay is charged in the store's own currency.
    'voguepay' => [
        'currency' => env('VOGUE_CURRENCY'),
        'merchant_id' => env('VOGUE_MERCHANT_ID'),
    ],

    'otajer' => [
        'url' => env('OTAJER_URL') ?: 'https://stores.otajer.com',
        'token' => env('OTAJER_TOKEN') ?: null,
    ],

    // Ghostscript compresses the receipt bill PDFs (App\Services\PdfOptimizer).
    // Best effort: without the binary a PDF is stored as sent.
    'ghostscript' => [
        'compress_pdfs' => (bool) env('GHOSTSCRIPT_COMPRESS_PDFS', true),
        'binary' => env('GHOSTSCRIPT_BINARY') ?: 'gs',
        'timeout' => (int) env('GHOSTSCRIPT_TIMEOUT', 30),
    ],

];
