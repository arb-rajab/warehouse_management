<?php

return [

    /*
    |--------------------------------------------------------------------------
    | iOS Universal Links
    |--------------------------------------------------------------------------
    |
    | Served at /.well-known/apple-app-site-association so iOS opens the
    | mobile app directly for "cell/*" links instead of the browser fallback
    | page. DEEPLINK_APPLE_APP_ID (format: "{TeamID}.{BundleID}") is supplied
    | by the mobile app project.
    |
    */

    'apple_app_site_association' => [
        'applinks' => [
            'apps' => [],
            'details' => [
                [
                    'appID' => env('DEEPLINK_APPLE_APP_ID'),
                    'paths' => ['/cell/*'],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Android App Links
    |--------------------------------------------------------------------------
    |
    | Served at /.well-known/assetlinks.json. DEEPLINK_ANDROID_PACKAGE_NAME
    | and the comma-separated DEEPLINK_ANDROID_CERT_FINGERPRINTS are supplied
    | by the mobile app project.
    |
    */

    'assetlinks' => [
        [
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => env('DEEPLINK_ANDROID_PACKAGE_NAME'),
                'sha256_cert_fingerprints' => array_filter(
                    explode(',', (string) env('DEEPLINK_ANDROID_CERT_FINGERPRINTS', ''))
                ),
            ],
        ],
    ],

];
