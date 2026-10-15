<?php

use Illuminate\Support\Facades\DB;

/**
 * The storefront's public lookup endpoints are small, rarely-changing and
 * re-fetched by every app launch, so they revalidate with an ETag. The tag
 * is a hash of the body, which already differs per language or currency.
 */
test('public store lookups answer a repeat request that already holds the current data with a 304', function (string $path) {
    DB::table('business_settings')->insert([
        ['type' => 'home_banner1_images', 'value' => '[]'],
    ]);

    $etag = $this->getJson($path)->assertOk()->headers->get('ETag');

    expect($etag)->not->toBeNull();

    $this->getJson($path, ['If-None-Match' => $etag])->assertNotModified();
})->with([
    'languages' => '/temp/api/v2/languages',
    'banners' => '/temp/api/v2/banners',
    'business settings' => '/temp/api/v2/business-settings',
    'colors' => '/temp/api/v2/colors',
    'currencies' => '/temp/api/v2/currencies',
]);
