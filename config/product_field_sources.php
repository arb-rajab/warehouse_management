<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Otajer-synced product fields
    |--------------------------------------------------------------------------
    |
    | Every `products` column the Otajer sync (`products:sync`) writes. Each
    | one is owned either by the sync ('otajer') or by the store admin panel
    | ('admin'); the Store admin chooses per field under Setup > Product data
    | sources, and App\Services\ProductFieldSourceService is the only reader
    | and writer of that choice. A column the sync starts writing must be
    | registered here in the same change (.ai/rules/product-field-sources.md).
    |
    | default:      the owner while no choice is stored. 'otajer' for all three
    |               keeps the behaviour from before the setting existed.
    | insert_value: what the sync writes into the column when it creates a
    |               product while the admin owns the field: the sync never
    |               copies the feed's value into an admin-owned column. A new
    |               product therefore starts unnamed and unpublished until the
    |               admin fills it in.
    |
    | Keys are stored in business_settings.type (VARCHAR(30)) behind the
    | `product_source_` prefix, so a field name may be at most 15 characters.
    |
    */

    'fields' => [
        'name' => ['default' => 'otajer', 'insert_value' => ''],
        'ar_name' => ['default' => 'otajer', 'insert_value' => ''],
        'published' => ['default' => 'otajer', 'insert_value' => 0],
    ],

];
