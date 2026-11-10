<?php

use App\Models\Category;
use App\Models\StoreUser;
use App\Services\Store\SetupChecklist;
use App\Support\StorePermissions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** A staff account holding every permission a check is gated by. */
function setupChecklistStaff(): StoreUser
{
    return createStoreStaff(...array_values(array_unique(SetupChecklist::PERMISSIONS)));
}

/**
 * The state of one check for `$user` (every permission by default), or null
 * when the check is not listed for them.
 */
function setupChecklistDone(string $key, ?StoreUser $user = null): ?bool
{
    $items = app(SetupChecklist::class)->items($user ?? setupChecklistStaff());

    return collect($items)->firstWhere('key', $key)['done'] ?? null;
}

beforeEach(function () {
    config([
        'mail.mailers.smtp.username' => null, 'mail.mailers.smtp.password' => null,
        'services.google_maps.key' => null,
        'services.fcm.firebase_credentials' => null, 'services.fcm.firebase_project' => null, 'services.fcm.project_id' => null,
        'app.default_language' => 'en',
    ]);
    Cache::forget('addons');
});

afterEach(fn () => @unlink(storage_path('framework/testing/setup-checklist-fcm.json')));

test('a user holding every permission gets the eleven checks in order, and an empty shop has only the cash check done', function () {
    $items = app(SetupChecklist::class)->items(setupChecklistStaff());

    expect(array_column($items, 'key'))->toBe([
        'smtp', 'paymentMethod', 'cashOnDeliveryProducts', 'shipping', 'firebasePush', 'googleMaps',
        'currency', 'vatTax', 'catalog', 'siteBasics', 'language',
    ])->and(array_column($items, 'done', 'key'))->toBe([
        'smtp' => false, 'paymentMethod' => false, 'cashOnDeliveryProducts' => true, 'shipping' => false,
        'firebasePush' => false, 'googleMaps' => false, 'currency' => false, 'vatTax' => false,
        'catalog' => false, 'siteBasics' => false, 'language' => false,
    ]);
});

test('each item carries the permission that can fix it', function () {
    $items = app(SetupChecklist::class)->items(setupChecklistStaff());

    expect(array_column($items, 'permission', 'key'))->toBe([
        'smtp' => 'smtp_settings', 'paymentMethod' => 'payment_methods_configurations',
        'cashOnDeliveryProducts' => 'show_all_products', 'shipping' => 'shipping_configuration',
        'firebasePush' => 'google_firebase_setting', 'googleMaps' => 'google_map_setting',
        'currency' => 'currency_setup', 'vatTax' => 'vat_&_tax_setup', 'catalog' => 'show_all_products',
        'siteBasics' => 'general_settings', 'language' => 'language_setup',
    ]);
});

test('every permission a check is gated by is a real store permission', function () {
    expect(array_values(array_diff(array_unique(SetupChecklist::PERMISSIONS), array_keys(StorePermissions::all()))))->toBe([]);
});

// smtp

test('smtp is done as soon as either credential is set, as the dashboard warning has always tested it', function (?string $username, ?string $password, bool $done) {
    config(['mail.mailers.smtp.username' => $username, 'mail.mailers.smtp.password' => $password]);

    expect(setupChecklistDone('smtp'))->toBe($done);
})->with([
    'both missing' => [null, null, false],
    'both empty' => ['', '', false],
    'username only' => ['user', null, true],
    'password only' => [null, 'pass', true],
    'both set' => ['user', 'pass', true],
]);

// paymentMethod

test('a payment method is done when the app can list one', function (string $setting, string|int $value, bool $done) {
    storeSetting($setting, $value);

    expect(setupChecklistDone('paymentMethod'))->toBe($done);
})->with([
    'cash on' => ['cash_payment', 1, true],
    'wallet on' => ['wallet_system', 1, true],
    'iyzico on' => ['iyzico', 1, true],
    'bkash on' => ['bkash', 1, true],
    'nagad on' => ['nagad', 1, true],
    'sslcommerz on' => ['sslcommerz_payment', 1, true],
    'a gateway switched off' => ['bkash', 0, false],
    'an unrelated setting' => ['site_name', 1, false],
]);

test('offline payment counts only when its addon is on and a manual method exists', function (bool $addon, bool $method, bool $done) {
    // The legacy table has no migration in this repository: give the query one.
    Schema::create('manual_payment_methods', function ($table) {
        $table->id();
        $table->timestamps();
    });
    if ($addon) {
        DB::table('addons')->insert(['name' => 'Offline payment', 'unique_identifier' => 'offline_payment', 'activated' => 1]);
    }
    if ($method) {
        DB::table('manual_payment_methods')->insert(['created_at' => now(), 'updated_at' => now()]);
    }
    Cache::forget('addons');

    expect(setupChecklistDone('paymentMethod'))->toBe($done);
})->with([
    'addon on, a method' => [true, true, true],
    'addon on, no method' => [true, false, false],
    'addon off, a method' => [false, true, false],
]);

// cashOnDeliveryProducts

test('cash on delivery products are done unless cash payment is on and no product allows it', function (array $settings, array $products, bool $done) {
    foreach ($settings as $type => $value) {
        storeSetting($type, $value);
    }
    foreach ($products as $cashOnDelivery) {
        createStoreProduct(['cash_on_delivery' => $cashOnDelivery]);
    }

    expect(setupChecklistDone('cashOnDeliveryProducts'))->toBe($done);
})->with([
    'cash never set up' => [[], [], true],
    'cash off, no product' => [['cash_payment' => 0], [], true],
    'cash off, a product allows it' => [['cash_payment' => 0], [1], true],
    'cash on, no product at all' => [['cash_payment' => 1], [], false],
    'cash on, no product allows it' => [['cash_payment' => 1], [0, 0], false],
    'cash on, one product allows it' => [['cash_payment' => 1], [0, 1], true],
]);

// shipping (strict, per type)

test('shipping is done per type only when the data that type prices delivery from exists', function (?string $type, array $settings, array $cities, array $carriers, bool $done) {
    if ($type !== null) {
        storeSetting('shipping_type', $type);
    }
    foreach ($settings as $key => $value) {
        storeSetting($key, $value);
    }
    foreach ($cities as [$cost, $status]) {
        DB::table('cities')->insert(['name' => 'City', 'state_id' => 1, 'cost' => $cost, 'status' => $status]);
    }
    foreach ($carriers as [$status, $free, $range, $price]) {
        $carrier = DB::table('carriers')->insertGetId(['name' => 'Carrier', 'transit_time' => '2 days', 'free_shipping' => $free, 'status' => $status]);
        $rangeId = $range ? DB::table('carrier_ranges')->insertGetId(['carrier_id' => $carrier, 'billing_type' => 'weight_based', 'delimiter1' => 0, 'delimiter2' => 10]) : null;
        if ($price && $rangeId !== null) {
            DB::table('carrier_range_prices')->insert(['carrier_id' => $carrier, 'carrier_range_id' => $rangeId, 'zone_id' => 1, 'price' => 4]);
        }
    }

    expect(setupChecklistDone('shipping'))->toBe($done);
})->with([
    // type, extra settings, cities [cost, status], carriers [status, free, has range, has price], done
    'no type' => [null, [], [], [], false],
    'no type but data for every type' => [null, ['flat_rate_shipping_cost' => '5', 'shipping_cost_admin' => '5'], [[3, 1]], [[1, 1, true, true]], false],
    'an unknown type' => ['teleport', [], [], [], false],
    'product wise' => ['product_wise_shipping', [], [], [], true],

    'flat rate without a cost' => ['flat_rate', [], [], [], false],
    'flat rate with a blank cost' => ['flat_rate', ['flat_rate_shipping_cost' => ''], [], [], false],
    'flat rate with a non numeric cost' => ['flat_rate', ['flat_rate_shipping_cost' => 'free'], [], [], false],
    'flat rate with a cost' => ['flat_rate', ['flat_rate_shipping_cost' => '12.5'], [], [], true],
    'flat rate with a deliberate zero cost' => ['flat_rate', ['flat_rate_shipping_cost' => '0'], [], [], true],
    'flat rate with only the seller wise cost' => ['flat_rate', ['shipping_cost_admin' => '5'], [], [], false],

    'seller wise without a cost' => ['seller_wise_shipping', [], [], [], false],
    'seller wise with the in house cost' => ['seller_wise_shipping', ['shipping_cost_admin' => '5'], [], [], true],
    'seller wise with only the flat cost' => ['seller_wise_shipping', ['flat_rate_shipping_cost' => '5'], [], [], false],

    'area wise without a city' => ['area_wise_shipping', [], [], [], false],
    'area wise with a free city' => ['area_wise_shipping', [], [[0, 1]], [], false],
    'area wise with a disabled city that has a cost' => ['area_wise_shipping', [], [[3, 0]], [], false],
    'area wise with a city that has a cost' => ['area_wise_shipping', [], [[0, 1], [3, 1]], [], true],
    'area wise with only a flat cost' => ['area_wise_shipping', ['flat_rate_shipping_cost' => '5'], [], [], false],

    'carrier wise without a carrier' => ['carrier_wise_shipping', [], [], [], false],
    'carrier wise with a carrier that has no range' => ['carrier_wise_shipping', [], [], [[1, 0, false, false]], false],
    'carrier wise with a range but no price' => ['carrier_wise_shipping', [], [], [[1, 0, true, false]], false],
    'carrier wise with a range and a price' => ['carrier_wise_shipping', [], [], [[1, 0, true, true]], true],
    'carrier wise with a disabled carrier that has a price' => ['carrier_wise_shipping', [], [], [[0, 0, true, true]], false],
    'carrier wise with a free shipping carrier' => ['carrier_wise_shipping', [], [], [[1, 1, false, false]], true],
    'carrier wise with a disabled free shipping carrier' => ['carrier_wise_shipping', [], [], [[0, 1, false, false]], false],
    'carrier wise with only a city cost' => ['carrier_wise_shipping', [], [[3, 1]], [], false],
]);

// firebasePush

test('firebase push is done when the push service is configured', function (bool $configured) {
    if ($configured) {
        $directory = storage_path('framework/testing');
        @mkdir($directory, 0755, true);
        file_put_contents($directory.'/setup-checklist-fcm.json', '{}');
        config(['services.fcm.firebase_credentials' => 'framework/testing/setup-checklist-fcm.json', 'services.fcm.firebase_project' => 'albarakabv-store']);
    }

    expect(setupChecklistDone('firebasePush'))->toBe($configured);
})->with([true, false]);

test('firebase push is not done with a project but no credentials file', function () {
    config(['services.fcm.firebase_credentials' => 'framework/testing/missing-fcm.json', 'services.fcm.firebase_project' => 'albarakabv-store']);

    expect(setupChecklistDone('firebasePush'))->toBeFalse();
});

// googleMaps

test('google maps is done when the maps key is set', function (?string $key, bool $done) {
    config(['services.google_maps.key' => $key]);

    expect(setupChecklistDone('googleMaps'))->toBe($done);
})->with([[null, false], ['', false], ['AIza-test-key', true]]);

test('google maps is done by a key saved on the Google map settings page, with no MAP_API_KEY', function () {
    storeSetting('google_map_api_key', 'AIza-saved-key');

    expect(setupChecklistDone('googleMaps'))->toBeTrue();
});

test('google maps is not done by a blank saved key without MAP_API_KEY, and falls back to MAP_API_KEY with one', function () {
    storeSetting('google_map_api_key', '   ');

    expect(setupChecklistDone('googleMaps'))->toBeFalse();

    config(['services.google_maps.key' => 'AIza-env-key']);

    expect(setupChecklistDone('googleMaps'))->toBeTrue();
});

// currency

test('currency is done when the default currency is set and its row exists', function (array $settings, string $seed, bool $done) {
    if ($seed === 'default') {
        seedStoreCurrency();
    } elseif ($seed === 'row') {
        DB::table('currencies')->insert(['name' => 'Euro', 'symbol' => '€', 'exchange_rate' => 1, 'status' => 1, 'code' => 'EUR']);
    }
    foreach ($settings as $type => $value) {
        storeSetting($type, $value);
    }

    expect(setupChecklistDone('currency'))->toBe($done);
})->with([
    'no default currency' => [[], '', false],
    'a default that is not an id' => [['system_default_currency' => 'EUR'], '', false],
    'a default whose row is gone' => [['system_default_currency' => 987654], '', false],
    'a default that exists' => [[], 'default', true],
    'a currency row but no default set' => [[], 'row', false],
]);

// vatTax

test('vat and tax is done when an active tax exists', function (?int $status, bool $done) {
    if ($status !== null) {
        DB::table('taxes')->insert(['name' => 'VAT', 'tax_status' => $status]);
    }

    expect(setupChecklistDone('vatTax'))->toBe($done);
})->with([
    'no tax' => [null, false],
    'an inactive tax' => [0, false],
    'an active tax' => [1, true],
]);

test('vat and tax is done when one of several taxes is active', function () {
    DB::table('taxes')->insert([['name' => 'Old', 'tax_status' => 0], ['name' => 'VAT', 'tax_status' => 1]]);

    expect(setupChecklistDone('vatTax'))->toBeTrue();
});

// catalog

test('the catalog is done with a published product and a category', function (bool $published, ?bool $product, bool $category, bool $done) {
    if ($product !== null) {
        createStoreProduct(['published' => $published && $product]);
    }
    if ($category) {
        Category::forceCreate(['name' => 'Root', 'parent_id' => 0, 'level' => 0]);
    }

    expect(setupChecklistDone('catalog'))->toBe($done);
})->with([
    'nothing' => [true, null, false, false],
    'a published product, no category' => [true, true, false, false],
    'a category, no product' => [true, null, true, false],
    'an unpublished product and a category' => [false, true, true, false],
    'a published product and a category' => [true, true, true, true],
]);

// siteBasics

test('site basics are done only with the name, email, phone and header logo all set', function () {
    foreach (['site_name' => 'Al Baraka', 'contact_email' => 'shop@example.com', 'contact_phone' => '+31 20 000 0000', 'header_logo' => '12'] as $type => $value) {
        storeSetting($type, $value);
    }

    expect(setupChecklistDone('siteBasics'))->toBeTrue();
});

test('site basics are not done while any of the four settings is missing or blank', function (string $missing, bool $blank) {
    foreach (['site_name' => 'Al Baraka', 'contact_email' => 'shop@example.com', 'contact_phone' => '+31 20 000 0000', 'header_logo' => '12'] as $type => $value) {
        if ($type !== $missing || $blank) {
            storeSetting($type, $type === $missing ? '' : $value);
        }
    }

    expect(setupChecklistDone('siteBasics'))->toBeFalse();
})->with(function () {
    foreach (['site_name', 'contact_email', 'contact_phone', 'header_logo'] as $key) {
        yield $key.' missing' => [$key, false];
        yield $key.' blank' => [$key, true];
    }
});

// language

test('language is done when the default language exists and is active', function (?string $default, array $languages, bool $done) {
    config(['app.default_language' => $default]);
    foreach ($languages as [$code, $status]) {
        DB::table('languages')->insert(['name' => strtoupper($code), 'code' => $code, 'status' => $status]);
    }

    expect(setupChecklistDone('language'))->toBe($done);
})->with([
    'no languages' => ['en', [], false],
    'the default is inactive' => ['en', [['en', 0]], false],
    'only another language is active' => ['en', [['ar', 1], ['en', 0]], false],
    'the default is active' => ['en', [['en', 1]], true],
    'an arabic default that is active' => ['ar', [['en', 1], ['ar', 1]], true],
    'no default configured' => [null, [['en', 1]], false],
]);

// permission gating and counts

test('a user lacking the permission does not get the item', function (string $key) {
    $permission = SetupChecklist::PERMISSIONS[$key];
    $others = array_values(array_unique(array_filter(SetupChecklist::PERMISSIONS, fn (string $p): bool => $p !== $permission)));

    $withoutIt = app(SetupChecklist::class)->items(createStoreStaff(...$others));
    $onlyIt = app(SetupChecklist::class)->items(createStoreStaff($permission));

    expect(array_column($withoutIt, 'key'))->not->toContain($key)
        ->and(array_column($onlyIt, 'key'))->toContain($key)
        ->and(array_column($onlyIt, 'permission'))->each->toBe($permission);
})->with(array_keys(SetupChecklist::PERMISSIONS));

test('items without a permission are not counted as to do or as total', function () {
    $checklist = app(SetupChecklist::class);
    storeSetting('bkash', 1);
    $user = createStoreStaff('payment_methods_configurations', 'smtp_settings');

    $items = $checklist->items($user);

    expect($items)->toBe([
        ['key' => 'smtp', 'done' => false, 'permission' => 'smtp_settings'],
        ['key' => 'paymentMethod', 'done' => true, 'permission' => 'payment_methods_configurations'],
    ])->and($checklist->progress($items))->toBe(['done' => 1, 'total' => 2])
        ->and($checklist->pending($items))->toBe(['smtp']);
});

test('a user with no setup permission has an empty checklist', function () {
    $checklist = app(SetupChecklist::class);
    $items = $checklist->items(createStoreStaff('admin_dashboard'));

    expect($items)->toBe([])
        ->and($checklist->progress($items))->toBe(['done' => 0, 'total' => 0])
        ->and($checklist->payload($items))->toBe(['items' => [], 'done' => 0, 'total' => 0]);
});

test('one permission can list two checks and both count', function () {
    $checklist = app(SetupChecklist::class);
    $items = $checklist->items(createStoreStaff('show_all_products'));

    expect(array_column($items, 'key'))->toBe(['cashOnDeliveryProducts', 'catalog'])
        ->and($checklist->progress($items))->toBe(['done' => 1, 'total' => 2]);
});

test('the payload carries only the key and state of each item with the counts', function () {
    $checklist = app(SetupChecklist::class);
    config(['mail.mailers.smtp.username' => 'user']);
    $items = $checklist->items(createStoreStaff('smtp_settings', 'currency_setup'));

    expect($checklist->payload($items))->toBe([
        'items' => [['key' => 'smtp', 'done' => true], ['key' => 'currency', 'done' => false]],
        'done' => 1,
        'total' => 2,
    ]);
});

test('the checklist re-reads the settings on every call', function () {
    $checklist = app(SetupChecklist::class);
    $user = createStoreStaff('payment_methods_configurations');

    expect($checklist->items($user)[0]['done'])->toBeFalse();

    storeSetting('wallet_system', 1);

    expect($checklist->items($user)[0]['done'])->toBeTrue();
});

test('the whole checklist reads its settings in one query', function () {
    $user = setupChecklistStaff();
    DB::enableQueryLog();
    app(SetupChecklist::class)->items($user);
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries->filter(fn (array $q): bool => str_contains($q['query'], 'from "business_settings"')))->toHaveCount(1);
});
