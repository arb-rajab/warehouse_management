<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Store App Asset Base URL
    |--------------------------------------------------------------------------
    |
    | The store app owns the shared `products` and `uploads` tables, and stores
    | only a relative file path in `uploads.file_name` — the absolute URL is
    | built by whichever app serves the file. This is that app's public base
    | URL, used by Product::imageUrl(). When an upload carries its own
    | `external_link` (a CDN or S3 URL), that wins and this is not consulted.
    |
    | A null value means product images resolve to null rather than to a
    | broken relative URL. See .ai/rules/shared-database.md.
    |
    */

    'asset_base_url' => env('STORE_ASSET_BASE_URL'),

];
