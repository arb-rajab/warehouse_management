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
    | default:      the owner while no choice is stored. 'otajer' for all of
    |               them: the first sync after a field is added overwrites its
    |               value on feed-origin rows.
    | insert_value: what the sync writes into the column when it creates a
    |               product while the admin owns the field: the sync never
    |               copies the feed's value into an admin-owned column. A new
    |               product therefore starts unnamed, unpublished and unpriced
    |               until the admin fills it in.
    |
    | Keys are stored in business_settings.type (VARCHAR(30)) behind the
    | `product_source_` prefix, so a field name may be at most 15 characters.
    |
    */

    'fields' => [
        'name' => ['default' => 'otajer', 'insert_value' => ''],
        'ar_name' => ['default' => 'otajer', 'insert_value' => ''],
        'published' => ['default' => 'otajer', 'insert_value' => 0],
        // The margin collection (product_collections.serial = the feed's
        // Mat_Class2_ID). 0 points at no collection.
        'collection_id' => ['default' => 'otajer', 'insert_value' => 0],
        // The feed's Serial, Price, UnitEnName, UnitID, TaxPercent, Desc and
        // Unit_Equal. A row whose feed value is missing or unusable keeps the
        // current value. While unit_price is Otajer-owned the sync also
        // re-prices the PerBox/PerPiece product_stocks of feed-origin rows.
        'serial' => ['default' => 'otajer', 'insert_value' => ''],
        'unit_price' => ['default' => 'otajer', 'insert_value' => 0],
        'unit' => ['default' => 'otajer', 'insert_value' => ''],
        'api_unitId' => ['default' => 'otajer', 'insert_value' => ''],
        'api_unit_name' => ['default' => 'otajer', 'insert_value' => ''],
        'tax' => ['default' => 'otajer', 'insert_value' => 0],
        'description' => ['default' => 'otajer', 'insert_value' => ''],
        // 1, not 0: the legacy imports divide the box price by it.
        'unit_equal' => ['default' => 'otajer', 'insert_value' => 1],
    ],

];
