<?php

use Illuminate\Support\Arr;

/**
 * Every deploy runs `config:cache`, after which env() outside config/ returns
 * null. The legacy store read its settings through env() at hundreds of sites,
 * so on a deployed host emails had no app name or sender, the S3 branches never
 * ran and role creation failed. These keys have a config equivalent now; a
 * read of one through env() is a bug that no test on an uncached config sees.
 *
 * Still read through env(), and not listed: the payment gateways other than
 * PayHere (their routes answer 404 until a gateway is enabled in
 * config/store.php, and its credentials move to config first), DEMO_MODE (null
 * means off, which is the safe reading), FORCE_HTTPS (turning it on is a
 * decision, not a fix: behind a proxy that does not report https it redirects
 * forever) and the admin settings screens under
 * resources/views/shop/backend/setup_configurations, which print credentials
 * and write nothing now that the .env writer refuses.
 *
 * @var array<string, string> key => the config() call that replaces it
 */
const ENV_KEYS_WITH_A_CONFIG_EQUIVALENT = [
    'APP_NAME' => "config('app.name')",
    'APP_URL' => "config('app.url')",
    'MAIL_FROM_ADDRESS' => "config('mail.from.address')",
    'MAIL_FROM_NAME' => "config('mail.from.name')",
    'FILESYSTEM_DRIVER' => "config('filesystems.default')",
    'SESSION_DRIVER' => "config('session.driver')",
    'CACHE_DRIVER' => "config('cache.default')",
    'MAIL_DRIVER' => "config('mail.default')",
    'DEFAULT_LANGUAGE' => "config('app.default_language')",
    'CAPTCHA_KEY' => "config('services.recaptcha.site_key')",
    'MAP_API_KEY' => "config('services.google_maps.key')",
    'FACEBOOK_PIXEL_ID' => "config('services.facebook.pixel_id')",
    'FACEBOOK_PAGE_ID' => "config('services.facebook.page_id')",
    'FACEBOOK_APP_ID' => "config('services.facebook.app_id')",
    'TRACKING_ID' => "config('services.google_analytics.tracking_id')",
    'MAIL_USERNAME' => "config('mail.mailers.smtp.username')",
    'MAIL_PASSWORD' => "config('mail.mailers.smtp.password')",
    'OPENCAGE_API_KEY' => "config('services.opencage.key')",
    'FCM_SERVER_KEY' => "config('services.fcm.server_key')",
    'FIREBASE_CREDENTIALS' => "config('services.fcm.firebase_credentials')",
    'FIREBASE_PROJECT' => "config('services.fcm.firebase_project')",
    'TWITTER_CLIENT_ID' => "config('services.twitter.client_id')",
    'SIGN_IN_WITH_APPLE_CLIENT_ID' => "config('services.apple.client_id')",
    'SIGN_IN_WITH_APPLE_CLIENT_SECRET' => "config('services.apple.client_secret')",
    'SIGN_IN_WITH_APPLE_REDIRECT' => "config('services.apple.redirect')",
    'IP_GEO_LOCATION_KEY' => "config('services.ip_geolocation.key')",
    'IP_GEO_LOCATION_TIMEOUT_SECONDS' => "config('services.ip_geolocation.timeout')",
    'AWS_URL' => "config('filesystems.disks.s3.url')",
];

test('nothing outside config reads a key that has a config equivalent through env()', function () {
    $offenders = [];
    $pattern = '/\benv\(\s*[\'"]('.implode('|', array_keys(ENV_KEYS_WITH_A_CONFIG_EQUIVALENT)).')[\'"]/';

    foreach (sourceFilesUnder(['app', 'bootstrap', 'routes', 'resources/views', 'database/migrations']) as $path => $source) {
        if (str_starts_with($path, 'resources/views/shop/backend/setup_configurations/')) {
            continue;
        }

        if (preg_match_all($pattern, $source, $matches)) {
            foreach (array_unique($matches[1]) as $key) {
                $offenders[] = "$path: env('$key') -> ".ENV_KEYS_WITH_A_CONFIG_EQUIVALENT[$key];
            }
        }
    }

    expect($offenders)->toBe([]);
});

test('the credentials the store reads at runtime are in config, under the names the code uses', function () {
    $keys = [
        'services.recaptcha.site_key', 'services.google_maps.key', 'services.facebook.pixel_id',
        'services.facebook.page_id', 'services.facebook.app_id', 'services.google_analytics.tracking_id',
        'services.opencage.key', 'services.fcm.server_key', 'services.fcm.firebase_credentials',
        'services.fcm.firebase_project', 'services.apple.client_id', 'services.apple.client_secret',
        'services.apple.redirect', 'services.ip_geolocation.key',
    ];

    foreach ($keys as $key) {
        expect(Arr::has(config()->all(), $key))->toBeTrue();
    }
});

test('the IP geolocation request has a timeout even when none is configured', function () {
    expect(config('services.ip_geolocation.timeout'))->toBeInt()->toBeGreaterThan(0);
});
