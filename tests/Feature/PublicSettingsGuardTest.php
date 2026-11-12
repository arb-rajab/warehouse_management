<?php

use App\Http\Controllers\Api\V1\BusinessSettingController;
use App\Http\Controllers\Store\Admin\SettingsController;
use App\Models\BusinessSetting;
use App\Services\ProductFieldSourceService;
use App\Support\GoogleMapsKey;
use App\Support\PublicSettingsGuard;
use App\Support\StoreDefaultLanguage;
use App\Support\StoreDeliverySettings;
use App\Support\StoreIntegrationIds;
use App\Support\StoreMailSettings;
use App\Support\StoreRecaptchaSettings;
use Symfony\Component\Finder\Finder;

/**
 * PublicSettingsGuard: what the public, unauthenticated business-settings
 * endpoint may return (a block-list, see the class), and the enforcement test
 * that makes every `business_settings` key get classified.
 */
const PUBLIC_SETTINGS_URL = '/api/v1/store/business-settings';

/** @return list<string> the lower-cased public keys */
function publicSettingsList(): array
{
    return array_map('strtolower', PublicSettingsGuard::PUBLIC);
}

/**
 * Every `business_settings` key the code names: the admin pages' keys, the
 * support classes' keys and every literal read in `app/` and `resources/views/`.
 *
 * @return array<string, string> key => where it was found
 */
function businessSettingKeysInCode(): array
{
    $found = [];
    $add = function (string $key, string $source) use (&$found): void {
        $found[$key] ??= $source;
    };

    foreach (SettingsController::PAGES as $page => $definition) {
        foreach ([...$definition['keys'], ...($definition['sections'] ?? []), ...array_values($definition['ids'] ?? [])] as $key) {
            $add($key, "SettingsController::PAGES['$page']");
        }
    }

    foreach (['mail' => StoreMailSettings::KEYS, 'recaptcha' => StoreRecaptchaSettings::KEYS] as $class => $keys) {
        foreach ($keys as $key) {
            $add($key, "Store{$class}Settings::KEYS");
        }
    }

    foreach ([...array_keys(StoreIntegrationIds::FIELDS), ...array_keys(StoreDeliverySettings::FIELDS), StoreDefaultLanguage::KEY, GoogleMapsKey::SETTING, ...array_keys(BusinessSetting::CURRENCY_DEFAULTS), ProductFieldSourceService::KEY_PREFIX.'name'] as $key) {
        $add($key, 'a support class');
    }

    // The settings forms' own key lists (`Update*Request::KEYS`).
    foreach ((new Finder)->files()->in(app_path('Http/Requests/Store/Admin'))->name('*.php') as $file) {
        $class = 'App\\Http\\Requests\\Store\\Admin\\'.$file->getBasename('.php');

        if (defined($class.'::KEYS')) {
            foreach (constant($class.'::KEYS') as $key) {
                $add($key, $class.'::KEYS');
            }
        }
    }

    // Literal reads: get_setting('x') and BusinessSetting::where('type', 'x').
    $patterns = [
        '/\bget_setting\(\s*[\'"]([^\'"]+)[\'"]/',
        '/BusinessSetting::(?:query\(\)->)?where\(\s*\'type\',\s*\'([^\']+)\'/',
    ];

    foreach ((new Finder)->files()->in([app_path(), resource_path('views')])->name('*.php') as $file) {
        $source = $file->getContents();

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $source, $matches) > 0) {
                foreach ($matches[1] as $key) {
                    $add($key, 'a read in '.$file->getRelativePathname());
                }
            }
        }
    }

    return $found;
}

// enforcement: every key is classified

test('every business setting key the code names is blocked or listed as public', function () {
    $keys = businessSettingKeysInCode();
    $public = publicSettingsList();

    $unclassified = array_filter($keys, fn (string $source, string $key): bool => ! PublicSettingsGuard::isBlocked($key) && ! in_array(strtolower($key), $public, true), ARRAY_FILTER_USE_BOTH);

    $this->assertSame([], $unclassified, 'These business_settings keys are neither blocked nor listed as public. '
        .'Classify each in App\\Support\\PublicSettingsGuard (BLOCKED, BLOCKED_PREFIXES or PUBLIC) and say why: '.json_encode($unclassified, JSON_PRETTY_PRINT));
});

test('the enforcement scan really finds the keys', function () {
    $keys = businessSettingKeysInCode();

    // A scan that silently matched nothing would pass the test above for good.
    expect(count($keys))->toBeGreaterThan(150)
        ->and($keys)->toHaveKeys(['site_name', 'mail_host', 'recaptcha_secret_key_encrypted', 'google_map_api_key', 'google_analytics_tracking_id', 'minimum_order_amount', 'Product_Reviews_Section', 'header_script']);
});

test('the lists are consistent', function () {
    $public = publicSettingsList();

    // No public key is blocked, and no key is listed twice.
    expect(array_filter(PublicSettingsGuard::PUBLIC, PublicSettingsGuard::isBlocked(...)))->toBe([])
        ->and(count($public))->toBe(count(array_unique($public)))
        // A named key is blocked, spelled lower case, and never also public.
        ->and(array_filter(PublicSettingsGuard::named(), fn (string $key): bool => ! PublicSettingsGuard::isBlocked($key) || $key !== strtolower($key)))->toBe([])
        ->and(array_intersect(PublicSettingsGuard::named(), $public))->toBe([])
        // The families are lower case and end where a name continues.
        ->and(array_filter(PublicSettingsGuard::BLOCKED_PREFIXES, fn (string $prefix): bool => $prefix !== strtolower($prefix) || ! str_ends_with($prefix, '_')))->toBe([])
        // An exception is listed public, is not a named key, and has a reason in the class.
        ->and(array_diff(PublicSettingsGuard::PUBLIC_EXCEPTIONS, $public))->toBe([])
        ->and(array_intersect(PublicSettingsGuard::PUBLIC_EXCEPTIONS, PublicSettingsGuard::named()))->toBe([])
        ->and(PublicSettingsGuard::PUBLIC_EXCEPTIONS)->toBe(['recaptcha_site_key']);
});

test('the keys the support classes own are blocked, the public IDs are not', function () {
    foreach ([...StoreMailSettings::KEYS, StoreRecaptchaSettings::KEYS['secret'], GoogleMapsKey::SETTING] as $key) {
        expect(PublicSettingsGuard::isBlocked($key))->toBeTrue($key);
    }

    foreach ([...array_keys(StoreIntegrationIds::FIELDS), StoreRecaptchaSettings::KEYS['site_key'], ...array_keys(StoreDeliverySettings::FIELDS), StoreDefaultLanguage::KEY] as $key) {
        expect(PublicSettingsGuard::isBlocked($key))->toBeFalse($key);
    }
});

test('the other public readers only use keys the guard allows', function () {
    expect(array_filter(BusinessSettingController::PUBLIC_KEYS, PublicSettingsGuard::isBlocked(...)))->toBe([])
        ->and(array_filter(['facebook_login', 'google_login', 'twitter_login'], PublicSettingsGuard::isBlocked(...)))->toBe([]);
});

// the rules

test('the name rules block secret-like keys', function (string $key) {
    expect(PublicSettingsGuard::isBlocked($key))->toBeTrue();
})->with([
    'payment gateway prefix' => 'payment_gateway_stripe_enabled',
    'payment gateway prefix, long' => 'payment_gateway_anything_at_all',
    'mail prefix' => 'mail_anything',
    'smtp prefix' => 'smtp_host',
    'firebase prefix' => 'firebase_project',
    'fcm prefix' => 'fcm_server',
    'sms prefix' => 'sms_provider',
    'provider prefix' => 'stripe_publishable',
    'secret suffix' => 'bkash_app_secret',
    'key suffix' => 'bkash_api_key',
    'a bare word' => 'secret',
    'the bare word key' => 'key',
    'password suffix' => 'seller_password',
    'token suffix' => 'device_token',
    'credentials suffix' => 'firebase_credentials',
    'encrypted suffix' => 'anything_encrypted',
    'a hyphen separator' => 'api-key',
    'no separator' => 'accesstoken',
    'camel case' => 'apiKey',
    'a word in the middle' => 'stripe_secret_live',
    'a token word in the middle' => 'token_expiry',
    'internal state' => 'product_source_name',
    'seeder tracking' => 'store_filter_seed_v1',
    'default representative' => 'default_representative_id',
    'pasted code' => 'header_script',
    'the maps key' => 'google_map_api_key',
    'the reCAPTCHA secret field' => 'recaptcha_secret_key',
    'empty' => '',
    'blank' => " \t ",
]);

test('public keys are not blocked, the reCAPTCHA site key is the exception', function (string $key) {
    expect(PublicSettingsGuard::isBlocked($key))->toBeFalse();
})->with([
    'site name' => 'site_name',
    'the site key' => 'recaptcha_site_key',
    'the site key, shouting' => 'RECAPTCHA_SITE_KEY',
    'the site key, padded' => "  recaptcha_site_key\n",
    'mixed case legacy key' => 'In_House_Products_Section',
    'an ID' => 'facebook_app_id',
    'a word that merely ends like key' => 'monkey',
    'a gateway switch' => 'bkash',
    'a sandbox flag' => 'bkash_sandbox',
]);

test('mixed case, padding and invisible characters cannot get around the rules', function (string $key) {
    expect(PublicSettingsGuard::isBlocked($key))->toBeTrue();
})->with([
    'upper case' => 'MAIL_HOST',
    'mixed case' => 'Mail_Password_Encrypted',
    'spaces' => '  mail_host  ',
    'tab and newline' => "\tmail_host\n",
    'non-breaking space' => "mail_host\u{00A0}",
    'zero width space' => "\u{200B}mail_host",
    'upper case maps key' => 'Google_Map_API_Key',
    'upper case secret' => 'RECAPTCHA_SECRET_KEY_ENCRYPTED',
    'padded gateway family' => " Payment_Gateway_X \r\n",
]);

test('an invalid UTF-8 name is blocked, not an error', function () {
    expect(PublicSettingsGuard::isBlocked("mail_host\xFF"))->toBeTrue();
});

test('requested() normalises what the caller sent', function () {
    expect(PublicSettingsGuard::requested(' site_name , MAIL_HOST,,  ,Mail_Host ,google_map_api_key,recaptcha_site_key '))->toBe(['site_name', 'recaptcha_site_key'])
        ->and(PublicSettingsGuard::requested(['site_name,mail_host', ' site_name', 'stripe_secret']))->toBe(['site_name'])
        ->and(PublicSettingsGuard::requested(null))->toBe([])
        ->and(PublicSettingsGuard::requested(''))->toBe([])
        ->and(PublicSettingsGuard::requested(',,, ,'))->toBe([])
        ->and(PublicSettingsGuard::requested(12))->toBe([])
        ->and(PublicSettingsGuard::requested(true))->toBe([])
        ->and(PublicSettingsGuard::requested([['site_name'], 5, null, new stdClass, ['a' => 'b']]))->toBe([]);
});

test('requested() caps the number of names', function () {
    $names = collect(range(1, PublicSettingsGuard::MAX_KEYS + 50))->map(fn (int $i): string => "custom_setting_$i")->implode(',');

    expect(PublicSettingsGuard::requested($names))->toHaveCount(PublicSettingsGuard::MAX_KEYS);
});

test('filter() judges the rows read by their own type', function () {
    // A MySQL collation can match a stored `Mail_Host ` to the requested `mail_host`.
    $rows = [
        ['id' => 1, 'type' => 'site_name'],
        ['id' => 2, 'type' => 'Mail_Host '],
        (object) ['id' => 3, 'type' => 'stripe_secret'],
        (object) ['id' => 4, 'type' => 'recaptcha_site_key'],
        ['id' => 5],
    ];

    expect(PublicSettingsGuard::filter($rows)->pluck('id')->all())->toBe([1, 4]);
});

// the endpoint

test('a request naming blocked and allowed keys returns only the allowed ones', function () {
    storeSetting('site_name', 'Albaraka');
    storeSetting('minimum_order_amount', 25);
    storeSetting('mail_host', 'smtp.secret-host.example');
    storeSetting('mail_password_encrypted', 'encrypted-mail-password');
    storeSetting('recaptcha_secret_key_encrypted', 'encrypted-recaptcha-secret');
    storeSetting('google_map_api_key', 'maps-key-value');
    storeSetting('stripe_secret', 'sk_live_value');
    storeSetting('payment_gateway_bkash', 'gateway-flag');
    storeSetting('firebase_server', 'firebase-value');
    storeSetting('header_script', '<script>token="abc"</script>');
    storeSetting('custom_api_key', 'custom-key-value');

    $keys = ['site_name', 'mail_host', 'mail_password_encrypted', 'recaptcha_secret_key_encrypted', 'google_map_api_key', 'stripe_secret', 'payment_gateway_bkash', 'firebase_server', 'header_script', 'custom_api_key', 'minimum_order_amount'];
    $response = $this->postJson(PUBLIC_SETTINGS_URL, ['keys' => implode(',', $keys)])->assertOk();

    expect(array_column($response->json(), 'type'))->toEqualCanonicalizing(['site_name', 'minimum_order_amount'])
        ->and($response->getContent())->not->toContain('secret-host', 'encrypted-mail-password', 'encrypted-recaptcha-secret', 'maps-key-value', 'sk_live_value', 'gateway-flag', 'firebase-value', 'token=', 'custom-key-value');
});

test('the response shape for an allowed key is the whole row', function () {
    storeSetting('site_name', 'Albaraka');

    $rows = $this->postJson(PUBLIC_SETTINGS_URL, ['keys' => 'site_name'])->assertOk()->json();

    expect($rows)->toHaveCount(1)
        ->and(array_keys($rows[0]))->toBe(['id', 'type', 'value', 'lang', 'created_at', 'updated_at'])
        ->and($rows[0]['type'])->toBe('site_name')
        ->and($rows[0]['value'])->toBe('Albaraka')
        ->and($rows[0]['lang'])->toBeNull()
        ->and($rows[0]['id'])->toBeInt();
});

test('keys that match a name rule are blocked even when they exist', function (string $key) {
    storeSetting($key, 'value-that-must-not-leak');
    storeSetting('site_name', 'Albaraka');

    $response = $this->postJson(PUBLIC_SETTINGS_URL, ['keys' => "$key,site_name"])->assertOk();

    expect(array_column($response->json(), 'type'))->toBe(['site_name'])
        ->and($response->getContent())->not->toContain('value-that-must-not-leak');
})->with([
    'gateway family' => 'payment_gateway_enabled',
    'mail family' => 'mail_anything',
    'secret' => 'gateway_secret',
    'key' => 'gateway_api_key',
    'password' => 'gateway_password',
    'token' => 'gateway_token',
    'credentials' => 'gateway_credentials',
    'encrypted' => 'gateway_encrypted',
]);

test('mixed case and whitespace in the keys parameter cannot bypass the guard', function () {
    storeSetting('site_name', 'Albaraka');
    storeSetting('mail_host', 'smtp.secret-host.example');
    storeSetting('google_map_api_key', 'maps-key-value');
    storeSetting('stripe_secret', 'sk_live_value');

    $response = $this->postJson(PUBLIC_SETTINGS_URL, ['keys' => " MAIL_HOST , Mail_Host\t,\nGoogle_Map_Api_Key ,stripe_secret ,\u{00A0}mail_host, site_name ,,  "])->assertOk();

    expect(array_column($response->json(), 'type'))->toBe(['site_name'])
        ->and($response->getContent())->not->toContain('secret-host', 'maps-key-value', 'sk_live_value');
});

test('a stored padded or upper-case twin of a blocked key never leaves', function () {
    // Rows a legacy import could hold next to `site_name`.
    storeSetting('site_name', 'Albaraka');
    storeSetting('MAIL_HOST ', 'smtp.secret-host.example');
    storeSetting('Stripe_Secret', 'sk_live_value');

    $response = $this->postJson(PUBLIC_SETTINGS_URL, ['keys' => 'site_name,MAIL_HOST ,Stripe_Secret'])->assertOk();

    expect(array_column($response->json(), 'type'))->toBe(['site_name'])
        ->and($response->getContent())->not->toContain('secret-host', 'sk_live_value');
});

test('an empty, missing or unusable keys parameter answers an empty list', function (array $payload) {
    storeSetting('site_name', 'Albaraka');

    $response = $this->postJson(PUBLIC_SETTINGS_URL, $payload)->assertOk();

    expect($response->json())->toBe([]);
})->with([
    'missing' => [[]],
    'null' => [['keys' => null]],
    'empty string' => [['keys' => '']],
    'only commas and spaces' => [['keys' => ' , ,,']],
    'a number' => [['keys' => 12345]],
    'a boolean' => [['keys' => true]],
    'only blocked names' => [['keys' => 'mail_host,stripe_secret']],
    'an empty array' => [['keys' => []]],
    'a nested array' => [['keys' => [['site_name']]]],
]);

test('an array instead of a string is handled safely', function () {
    storeSetting('site_name', 'Albaraka');
    storeSetting('minimum_order_amount', 25);
    storeSetting('mail_host', 'smtp.secret-host.example');

    $response = $this->postJson(PUBLIC_SETTINGS_URL, ['keys' => ['site_name', ' MAIL_HOST ', 'minimum_order_amount,mail_host', ['nested'], 7, null]])->assertOk();

    expect(array_column($response->json(), 'type'))->toEqualCanonicalizing(['site_name', 'minimum_order_amount'])
        ->and($response->getContent())->not->toContain('secret-host');

    // Also as a form post, where `keys[]=...` arrives as an array too.
    $form = $this->post(PUBLIC_SETTINGS_URL, ['keys' => ['site_name', 'mail_host']], ['Accept' => 'application/json'])->assertOk();

    expect(array_column($form->json(), 'type'))->toBe(['site_name']);
});

test('the keys the apps rely on still come back', function () {
    $values = [
        'site_name' => 'Albaraka', 'header_logo' => '12', 'site_icon' => '13', 'system_default_currency' => '1',
        'vendor_system_activation' => '1', 'wallet_system' => '0', 'minimum_order_amount' => '25',
        'minimum_order_amount_check' => '1', 'cash_payment' => '1', 'pickup_point' => '0', 'shipping_type' => 'product_wise_shipping',
        'home_slider_images' => '["1","2"]', 'home_categories' => '[3,4]', 'google_analytics_tracking_id' => 'G-ABCDE12345',
        'facebook_pixel_id' => '1234567890', 'facebook_page_id' => '1234567891', 'facebook_app_id' => '1234567892',
        'recaptcha_site_key' => 'site-key-0123456789abcdef', 'google_recaptcha' => '1', 'google_firebase' => '1',
        'facebook_login' => '1', 'google_login' => '0', 'twitter_login' => '0', 'default_language' => 'en',
        'delivery_lead_days' => '2', 'delivery_timezone' => 'Europe/Amsterdam', 'sslcommerz_payment' => '1', 'bkash_sandbox' => '1',
        'conversation_system' => '1', 'email_verification' => '1', 'guest_checkout_active' => '1', 'default_specific_product' => '9',
        'contact_email' => 'shop@example.com', 'current_version' => '1.0',
    ];

    foreach ($values as $type => $value) {
        storeSetting($type, $value);
    }

    $rows = collect($this->postJson(PUBLIC_SETTINGS_URL, ['keys' => implode(',', array_keys($values))])->assertOk()->json());

    expect($rows->pluck('value', 'type')->all())->toEqual($values);
});

test('the GET list of public settings goes through the guard and keeps its keys', function () {
    storeSetting('site_name', 'Albaraka');
    storeSetting('cash_payment', '1');
    storeSetting('mail_host', 'smtp.secret-host.example');
    storeSetting('google_map_api_key', 'maps-key-value');

    $response = $this->getJson(PUBLIC_SETTINGS_URL)->assertOk();

    expect(collect($response->json('data'))->pluck('type')->all())->toEqualCanonicalizing(['site_name', 'cash_payment'])
        ->and($response->getContent())->not->toContain('secret-host', 'maps-key-value');
});

test('the social login list keeps its three keys', function () {
    foreach (['facebook_login', 'google_login', 'twitter_login', 'apple_login'] as $key) {
        storeSetting($key, '1');
    }

    $rows = $this->getJson('/api/v1/store/activated-social-login')->assertOk()->json();

    expect(array_column($rows, 'type'))->toEqualCanonicalizing(['facebook_login', 'google_login', 'twitter_login']);
});
