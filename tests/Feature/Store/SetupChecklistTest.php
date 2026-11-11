<?php

use App\Models\Category;
use App\Models\ManualPaymentMethod;
use App\Models\StoreUser;
use App\Services\Store\SetupChecklist;
use App\Support\StorePermissions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/** @return list<string> every permission any check is gated by */
function setupChecklistPermissions(): array
{
    return array_values(array_unique(array_merge(...array_values(SetupChecklist::PERMISSIONS))));
}

/** A staff account holding every permission a check is gated by. */
function setupChecklistStaff(): StoreUser
{
    return createStoreStaff(...setupChecklistPermissions());
}

/**
 * The reason keys of one check for a staff member holding every permission
 * (empty when done), or null when the check is not listed.
 *
 * @return list<string>|null
 */
function setupChecklistReasons(string $key): ?array
{
    $item = collect(app(SetupChecklist::class)->items(setupChecklistStaff()))->firstWhere('key', $key);

    return $item === null ? null : array_column($item['reasons'], 'key');
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
        'mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1',
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

test('each item carries every permission needed to fix it', function () {
    $items = app(SetupChecklist::class)->items(setupChecklistStaff());

    expect(array_column($items, 'permissions', 'key'))->toBe([
        'smtp' => ['smtp_settings'], 'paymentMethod' => ['features_activation'],
        'cashOnDeliveryProducts' => ['show_all_products'], 'shipping' => ['shipping_configuration'],
        'firebasePush' => ['google_firebase_setting'], 'googleMaps' => ['google_map_setting'],
        'currency' => ['currency_setup'], 'vatTax' => ['vat_&_tax_setup'], 'catalog' => ['show_all_products'],
        'siteBasics' => ['general_settings', 'header_setup', 'footer_setup'], 'language' => ['language_setup'],
    ]);
});

test('every permission a check is gated by is a real store permission', function () {
    expect(array_values(array_diff(setupChecklistPermissions(), array_keys(StorePermissions::all()))))->toBe([]);
});

// reasons

test('a check is done exactly when it has no reason, and every reason names its check', function () {
    foreach (app(SetupChecklist::class)->items(setupChecklistStaff()) as $item) {
        expect($item['done'])->toBe($item['reasons'] === []);
        foreach ($item['reasons'] as $reason) {
            expect($reason['key'])->toStartWith($item['key'].'.')
                ->and(SetupChecklist::TARGETS)->toHaveKey($reason['key']);
        }
    }
});

test('every reason links to a real page and highlights a key that page accepts', function () {
    $this->withoutVite();
    seedStoreCurrency();
    foreach (SetupChecklist::TARGETS as $reason => [$route, $highlight]) {
        expect(Route::has($route))->toBeTrue("{$reason} links to a missing route {$route}");
        if ($highlight !== null) {
            actingAsStoreUser(createStoreStaff(...setupChecklistPermissions()));
            $this->get(route($route, ['highlight' => $highlight]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('highlight', $highlight));
        }
    }
});

test('every page a reason highlights on ignores an unknown, array or missing highlight', function () {
    $this->withoutVite();
    seedStoreCurrency();
    actingAsStoreUser(createStoreStaff(...setupChecklistPermissions()));
    $routes = collect(SetupChecklist::TARGETS)->filter(fn (array $target): bool => $target[1] !== null)
        ->map(fn (array $target): string => $target[0])->unique()->values();

    foreach ($routes as $route) {
        foreach ([['highlight' => 'not_a_setting'], ['highlight' => ['site_name']], []] as $query) {
            $this->get(route($route, $query))->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('highlight', null));
        }
    }
});

test('an empty shop explains every check with its exact missing setting', function () {
    $reasons = collect(app(SetupChecklist::class)->items(setupChecklistStaff()))
        ->mapWithKeys(fn (array $item): array => [$item['key'] => array_column($item['reasons'], 'key')])->all();

    expect($reasons)->toBe([
        'smtp' => ['smtp.username_and_password'],
        'paymentMethod' => ['paymentMethod.none_enabled'],
        'cashOnDeliveryProducts' => [],
        'shipping' => ['shipping.type_unset'],
        'firebasePush' => ['firebasePush.project_unset', 'firebasePush.credentials_missing'],
        'googleMaps' => ['googleMaps.api_key_unset'],
        'currency' => ['currency.default_unset'],
        'vatTax' => ['vatTax.no_active_tax'],
        'catalog' => ['catalog.no_published_product', 'catalog.no_category'],
        'siteBasics' => ['siteBasics.site_name', 'siteBasics.contact_email', 'siteBasics.contact_phone', 'siteBasics.header_logo'],
        'language' => ['language.default_missing'],
    ]);
});

test('a reason carries the page and the setting it highlights', function () {
    storeSetting('shipping_type', 'flat_rate');
    $shipping = collect(app(SetupChecklist::class)->items(setupChecklistStaff()))->firstWhere('key', 'shipping');

    expect($shipping['reasons'])->toBe([
        ['key' => 'shipping.flat_rate_cost', 'route' => 'shipping_configuration.index', 'highlight' => 'flat_rate_shipping_cost'],
    ]);
});

test('a missing category links to the categories page only for staff who may open it', function (bool $canOpen, string $route) {
    $user = createStoreStaff('show_all_products', ...($canOpen ? ['view_product_categories'] : []));
    $catalog = collect(app(SetupChecklist::class)->items($user))->firstWhere('key', 'catalog');

    expect(collect($catalog['reasons'])->firstWhere('key', 'catalog.no_category')['route'])->toBe($route);
})->with([
    'may open categories' => [true, 'categories.index'],
    'may not' => [false, 'products.all'],
]);

// smtp

test('smtp is done as soon as either credential is set, as the dashboard warning has always tested it', function (?string $username, ?string $password, bool $done) {
    config(['mail.mailers.smtp.username' => $username, 'mail.mailers.smtp.password' => $password]);

    expect(setupChecklistDone('smtp'))->toBe($done)
        ->and(setupChecklistReasons('smtp'))->toBe($done ? [] : ['smtp.username_and_password']);
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
    if ($addon) {
        DB::table('addons')->insert(['name' => 'Offline payment', 'unique_identifier' => 'offline_payment', 'activated' => 1]);
    }
    if ($method) {
        ManualPaymentMethod::factory()->create();
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

test('a shipping check that is not done names the exact setting the saved type still needs', function (?string $type, array $settings, ?string $reason) {
    if ($type !== null) {
        storeSetting('shipping_type', $type);
    }
    foreach ($settings as $key => $value) {
        storeSetting($key, $value);
    }

    expect(setupChecklistReasons('shipping'))->toBe($reason === null ? [] : [$reason]);
})->with([
    'no type' => [null, [], 'shipping.type_unset'],
    'an unknown type' => ['teleport', [], 'shipping.type_unset'],
    'product wise' => ['product_wise_shipping', [], null],
    'flat rate, cost never saved' => ['flat_rate', [], 'shipping.flat_rate_cost'],
    'flat rate, cost only for seller wise' => ['flat_rate', ['shipping_cost_admin' => '5'], 'shipping.flat_rate_cost'],
    'flat rate, a saved zero' => ['flat_rate', ['flat_rate_shipping_cost' => '0'], null],
    'seller wise, in-house cost never saved' => ['seller_wise_shipping', [], 'shipping.seller_admin_cost'],
    'area wise, no city' => ['area_wise_shipping', [], 'shipping.area_no_active_city_with_cost'],
    'carrier wise, no carrier' => ['carrier_wise_shipping', [], 'shipping.carrier_none_usable'],
]);

test('the checks read the row the settings pages write: the one without a language wins', function () {
    DB::table('business_settings')->insert([
        ['type' => 'contact_email', 'value' => '', 'lang' => 'en'],
        ['type' => 'contact_email', 'value' => 'shop@example.com', 'lang' => null],
    ]);

    expect(setupChecklistReasons('siteBasics'))->not->toContain('siteBasics.contact_email');
});

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

test('firebase push is not done with a project but no credentials file, and says the file is missing', function () {
    config(['services.fcm.firebase_credentials' => 'framework/testing/missing-fcm.json', 'services.fcm.firebase_project' => 'albarakabv-store']);

    expect(setupChecklistDone('firebasePush'))->toBeFalse()
        ->and(setupChecklistReasons('firebasePush'))->toBe(['firebasePush.credentials_missing']);
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

test('currency is done when the default currency is set and its row exists', function (array $settings, string $seed, bool $done, ?string $reason) {
    if ($seed === 'default') {
        seedStoreCurrency();
    } elseif ($seed === 'row') {
        DB::table('currencies')->insert(['name' => 'Euro', 'symbol' => '€', 'exchange_rate' => 1, 'status' => 1, 'code' => 'EUR']);
    }
    foreach ($settings as $type => $value) {
        storeSetting($type, $value);
    }

    expect(setupChecklistDone('currency'))->toBe($done)
        ->and(setupChecklistReasons('currency'))->toBe($reason === null ? [] : [$reason]);
})->with([
    'no default currency' => [[], '', false, 'currency.default_unset'],
    'a default that is not an id' => [['system_default_currency' => 'EUR'], '', false, 'currency.default_unset'],
    'a default whose row is gone' => [['system_default_currency' => 987654], '', false, 'currency.default_missing'],
    'a default that exists' => [[], 'default', true, null],
    'a currency row but no default set' => [[], 'row', false, 'currency.default_unset'],
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

test('the catalog is done with a published product and a category', function (bool $published, ?bool $product, bool $category, bool $done, array $reasons) {
    if ($product !== null) {
        createStoreProduct(['published' => $published && $product]);
    }
    if ($category) {
        Category::forceCreate(['name' => 'Root', 'parent_id' => 0, 'level' => 0]);
    }

    expect(setupChecklistDone('catalog'))->toBe($done)
        ->and(setupChecklistReasons('catalog'))->toBe($reasons);
})->with([
    'nothing' => [true, null, false, false, ['catalog.no_published_product', 'catalog.no_category']],
    'a published product, no category' => [true, true, false, false, ['catalog.no_category']],
    'a category, no product' => [true, null, true, false, ['catalog.no_published_product']],
    'an unpublished product and a category' => [false, true, true, false, ['catalog.no_published_product']],
    'a published product and a category' => [true, true, true, true, []],
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

    expect(setupChecklistDone('siteBasics'))->toBeFalse()
        ->and(setupChecklistReasons('siteBasics'))->toBe(["siteBasics.{$missing}"]);
})->with(function () {
    foreach (['site_name', 'contact_email', 'contact_phone', 'header_logo'] as $key) {
        yield $key.' missing' => [$key, false];
        yield $key.' blank' => [$key, true];
    }
});

// language

test('language is done when the default language exists and is active', function (?string $default, array $languages, bool $done, ?string $reason) {
    config(['app.default_language' => $default]);
    foreach ($languages as [$code, $status]) {
        DB::table('languages')->insert(['name' => strtoupper($code), 'code' => $code, 'status' => $status]);
    }

    expect(setupChecklistDone('language'))->toBe($done)
        ->and(setupChecklistReasons('language'))->toBe($reason === null ? [] : [$reason]);
})->with([
    'no languages' => ['en', [], false, 'language.default_missing'],
    'the default is inactive' => ['en', [['en', 0]], false, 'language.default_inactive'],
    'only another language is active' => ['en', [['ar', 1], ['en', 0]], false, 'language.default_inactive'],
    'the default is active' => ['en', [['en', 1]], true, null],
    'an arabic default that is active' => ['ar', [['en', 1], ['ar', 1]], true, null],
    'no default configured' => [null, [['en', 1]], false, 'language.default_missing'],
]);

test('language reads the default chosen in the admin, not DEFAULT_LANGUAGE, once one is saved', function (string $saved, array $languages, ?string $reason) {
    config(['app.default_language' => 'en']);
    foreach ($languages as [$code, $status]) {
        DB::table('languages')->insert(['name' => strtoupper($code), 'code' => $code, 'status' => $status]);
    }
    storeSetting('default_language', $saved);
    Cache::forget('business_settings');
    Cache::forget('business_settings:default_language');

    expect(setupChecklistReasons('language'))->toBe($reason === null ? [] : [$reason]);
})->with([
    'a saved active language while .env names an inactive one' => ['ar', [['en', 0], ['ar', 1]], null],
    'a saved language that was switched off while .env names an active one' => ['ar', [['en', 1], ['ar', 0]], 'language.default_inactive'],
    'a saved language that was deleted' => ['xx', [['en', 1]], 'language.default_missing'],
]);

test('each language reason names the exact language and still links to the default language control', function (string $env, ?string $saved, array $languages, string $reason, string $code) {
    config(['app.default_language' => $env]);
    foreach ($languages as [$languageCode, $status]) {
        DB::table('languages')->insert(['name' => strtoupper($languageCode), 'code' => $languageCode, 'status' => $status]);
    }
    if ($saved !== null) {
        storeSetting('default_language', $saved);
    }
    Cache::forget('business_settings');
    Cache::forget('business_settings:default_language');

    $language = collect(app(SetupChecklist::class)->items(setupChecklistStaff()))->firstWhere('key', 'language');

    expect($language['reasons'])->toBe([
        ['key' => $reason, 'route' => 'languages.index', 'highlight' => 'default_language', 'params' => ['code' => $code]],
    ]);
})->with([
    'missing from .env' => ['nl', null, [['en', 1]], 'language.default_missing', 'nl'],
    'inactive from .env' => ['en', null, [['en', 0]], 'language.default_inactive', 'en'],
    'missing, saved' => ['en', 'xx', [['en', 1]], 'language.default_missing', 'xx'],
    'inactive, saved' => ['en', 'ar', [['en', 1], ['ar', 0]], 'language.default_inactive', 'ar'],
]);

test('with no default language at all the reason names none with a dash', function () {
    config(['app.default_language' => null]);
    DB::table('languages')->insert(['name' => 'EN', 'code' => 'en', 'status' => 1]);

    $language = collect(app(SetupChecklist::class)->items(setupChecklistStaff()))->firstWhere('key', 'language');

    expect($language['reasons'][0])->toBe(['key' => 'language.default_missing', 'route' => 'languages.index', 'highlight' => 'default_language', 'params' => ['code' => '-']]);
});

test('only the language reasons carry params, and a done language check has none', function () {
    $items = collect(app(SetupChecklist::class)->items(setupChecklistStaff()));

    foreach ($items->where('key', '!=', 'language') as $item) {
        foreach ($item['reasons'] as $reason) {
            expect($reason)->not->toHaveKey('params');
        }
    }

    DB::table('languages')->insert(['name' => 'EN', 'code' => 'en', 'status' => 1]);

    expect(setupChecklistReasons('language'))->toBe([]);
});

// permission gating and counts

test('a user lacking any permission a check needs does not get the item', function (string $key) {
    $needed = SetupChecklist::PERMISSIONS[$key];

    $onlyIt = app(SetupChecklist::class)->items(createStoreStaff(...$needed));
    expect(array_column($onlyIt, 'key'))->toContain($key);

    foreach ($needed as $missing) {
        $withoutIt = app(SetupChecklist::class)->items(createStoreStaff(...array_values(array_diff(setupChecklistPermissions(), [$missing]))));
        expect(array_column($withoutIt, 'key'))->not->toContain($key);
    }
})->with(array_keys(SetupChecklist::PERMISSIONS));

test('site basics are listed only for staff who can open all three of their pages', function (array $permissions, bool $listed) {
    $items = app(SetupChecklist::class)->items(createStoreStaff(...$permissions));

    expect(in_array('siteBasics', array_column($items, 'key'), true))->toBe($listed);
})->with([
    'general settings only' => [['general_settings'], false],
    'general settings and header' => [['general_settings', 'header_setup'], false],
    'all three' => [['general_settings', 'header_setup', 'footer_setup'], true],
]);

test('smtp reads the settings saved on the SMTP page before the server values', function () {
    config(['mail.mailers.smtp.username' => null, 'mail.mailers.smtp.password' => null]);
    storeSetting('mail_host', 'smtp.example.com');
    storeSetting('mail_port', '587');
    storeSetting('mail_username', 'shop@example.com');

    expect(setupChecklistReasons('smtp'))->toBe([]);
});

test('smtp never mixes a saved host with the server credentials', function () {
    config(['mail.mailers.smtp.username' => 'env-user', 'mail.mailers.smtp.password' => 'env-password']);
    storeSetting('mail_host', 'smtp.example.com');
    storeSetting('mail_port', '587');

    expect(setupChecklistReasons('smtp'))->toBe(['smtp.username_and_password']);
});

test('smtp is not done while emails are only logged, whether the server or the admin chose it', function (string $where) {
    config(['mail.mailers.smtp.username' => 'user']);
    if ($where === 'server') {
        config(['mail.default' => 'log']);
    } else {
        storeSetting('mail_mailer', 'log');
    }

    expect(setupChecklistReasons('smtp'))->toBe(['smtp.mailer_log']);
})->with(['server', 'admin']);

test('smtp names an empty host', function () {
    config(['mail.mailers.smtp.host' => '', 'mail.mailers.smtp.username' => 'user']);

    expect(setupChecklistReasons('smtp'))->toBe(['smtp.host']);
});

test('each smtp reason highlights its own field on the SMTP page', function () {
    config(['mail.default' => 'log', 'mail.mailers.smtp.host' => null]);
    $smtp = collect(app(SetupChecklist::class)->items(setupChecklistStaff()))->firstWhere('key', 'smtp');

    expect($smtp['reasons'])->toBe([
        ['key' => 'smtp.mailer_log', 'route' => 'smtp_settings.index', 'highlight' => 'mail_mailer'],
        ['key' => 'smtp.host', 'route' => 'smtp_settings.index', 'highlight' => 'mail_host'],
        ['key' => 'smtp.username_and_password', 'route' => 'smtp_settings.index', 'highlight' => 'mail_credentials'],
    ]);
});

test('the payment check is listed for staff who can switch payment methods on, not for payment gateway configuration alone', function () {
    $checklist = app(SetupChecklist::class);

    expect(array_column($checklist->items(createStoreStaff('payment_methods_configurations')), 'key'))->not->toContain('paymentMethod')
        ->and(array_column($checklist->items(createStoreStaff('features_activation')), 'key'))->toBe(['paymentMethod']);
});

test('items without a permission are not counted as to do or as total', function () {
    $checklist = app(SetupChecklist::class);
    storeSetting('bkash', 1);
    $user = createStoreStaff('features_activation', 'smtp_settings');

    $items = $checklist->items($user);

    expect($items)->toBe([
        ['key' => 'smtp', 'done' => false, 'permissions' => ['smtp_settings'], 'reasons' => [
            ['key' => 'smtp.username_and_password', 'route' => 'smtp_settings.index', 'highlight' => 'mail_credentials'],
        ]],
        ['key' => 'paymentMethod', 'done' => true, 'permissions' => ['features_activation'], 'reasons' => []],
    ])->and($checklist->progress($items))->toBe(['done' => 1, 'total' => 2]);
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

test('the payload carries the key, state and reasons of each item with the counts', function () {
    $checklist = app(SetupChecklist::class);
    config(['mail.mailers.smtp.username' => 'user']);
    $items = $checklist->items(createStoreStaff('smtp_settings', 'currency_setup'));

    expect($checklist->payload($items))->toBe([
        'items' => [
            ['key' => 'smtp', 'done' => true, 'reasons' => []],
            ['key' => 'currency', 'done' => false, 'reasons' => [
                ['key' => 'currency.default_unset', 'route' => 'currency.index', 'highlight' => 'system_default_currency'],
            ]],
        ],
        'done' => 1,
        'total' => 2,
    ]);
});

test('the checklist re-reads the settings on every call', function () {
    $checklist = app(SetupChecklist::class);
    $user = createStoreStaff('features_activation');

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
