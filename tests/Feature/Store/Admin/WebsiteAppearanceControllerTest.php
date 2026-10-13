<?php

use App\Http\Middleware\BlockMaliciousRequests;
use App\Http\Requests\Store\Admin\UpdateGeneralSettingsRequest;
use App\Http\Requests\Store\Admin\UpdateWebsiteAppearanceRequest;
use App\Http\Requests\Store\Admin\UpdateWebsiteFooterRequest;
use App\Http\Requests\Store\Admin\UpdateWebsiteHeaderRequest;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function appearanceSetting(string $type, ?string $value): void
{
    DB::table('business_settings')->insert(['type' => $type, 'value' => $value]);
}

/** @return list<array<string, mixed>> */
function appearanceSettingsSnapshot(): array
{
    return DB::table('business_settings')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
}

test('the appearance screen renders the Inertia page', function () {
    actingAsStoreUser(createStoreStaff('website_appearance'));
    $this->get(route('website.appearance'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Website/Appearance'));
});

test('the appearance screen sends exactly its own settings, cleaned rich text, the raw scripts and permitted previews', function () {
    $staff = actingAsStoreUser(createStoreStaff('website_appearance'));
    $icon = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image', 'file_original_name' => 'Icon', 'file_size' => 16, 'external_link' => 'https://example.com/icon.png']);
    $foreign = Upload::factory()->create(['user_id' => createStoreUser('customer')->id, 'type' => 'image']);
    appearanceSetting('website_name', 'Holland Store');
    appearanceSetting('site_motto', 'Fresh food');
    appearanceSetting('site_icon', (string) $icon->id);
    appearanceSetting('base_color', '#d43533');
    appearanceSetting('base_hov_color', '#9d1b1a');
    appearanceSetting('login_page_image', (string) $foreign->id);
    appearanceSetting('meta_title', 'Holland');
    appearanceSetting('meta_description', 'Groceries');
    appearanceSetting('meta_keywords', 'dates, rice');
    appearanceSetting('cookies_agreement_text', '<p>We use <b>cookies</b></p><img src=x onerror=alert(1)>');
    appearanceSetting('show_cookies_agreement', 'on');
    appearanceSetting('show_website_popup', '1');
    appearanceSetting('website_popup_content', '<h2>Hello</h2><script>alert(1)</script>');
    appearanceSetting('seller_login_page_bg', '9');
    appearanceSetting('header_script', '<script>track("header")</script>');
    appearanceSetting('footer_script', '<noscript><img src="https://example.com/pixel"></noscript>');
    appearanceSetting('smtp_password', 'private');

    $response = $this->get(route('website.appearance'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Website/Appearance')
        ->has('settings', fn (Assert $settings) => $settings
            ->where('website_name', 'Holland Store')
            ->where('site_motto', 'Fresh food')
            ->where('base_color', '#d43533')
            ->where('base_hov_color', '#9d1b1a')
            ->where('meta_title', 'Holland')
            ->where('meta_description', 'Groceries')
            ->where('meta_keywords', 'dates, rice')
            ->where('site_icon', (string) $icon->id)
            ->where('login_page_image', (string) $foreign->id)
            ->where('register_page_image', '')
            ->where('flash_deal_banner', '')
            ->where('flash_deal_banner_small', '')
            ->where('meta_image', '')
            ->where('cookies_agreement_text', '<p>We use <b>cookies</b></p><img src="x">')
            ->where('website_popup_content', '<h2>Hello</h2>')
            ->where('show_cookies_agreement', true)
            ->where('show_website_popup', false)
            ->where('show_subscribe_form', false))
        ->where('scripts', ['header_script' => '<script>track("header")</script>', 'footer_script' => '<noscript><img src="https://example.com/pixel"></noscript>'])
        ->where('initialFiles', [
            'site_icon' => [['id' => $icon->id, 'name' => 'Icon', 'type' => 'image', 'size' => 16, 'url' => 'https://example.com/icon.png']],
            'login_page_image' => [],
            'register_page_image' => [],
            'flash_deal_banner' => [],
            'flash_deal_banner_small' => [],
            'meta_image' => [],
        ]));
    // The page JSON escapes the stored closing tag, so it cannot end the
    // element that carries the page data.
    $response->assertDontSee('private');
    expect($response->getContent())->toContain('track(\\"header\\")<\\/script>')->not->toContain('track(\\"header\\")</script>');
});

test('the appearance screen offers the seller and delivery boy login backgrounds only while their feature is on', function () {
    $staff = actingAsStoreUser(createStoreStaff('website_appearance'));
    $image = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image']);
    $this->get(route('website.appearance'))->assertInertia(fn (Assert $page) => $page
        ->missing('settings.seller_login_page_bg')->missing('settings.delivery_boy_login_page_bg')
        ->missing('initialFiles.seller_login_page_bg'));
    $this->put(route('website.appearance.update'), ['settings' => websiteAppearancePayload(['seller_login_page_bg' => (string) $image->id])])
        ->assertSessionHasErrors('settings');

    appearanceSetting('vendor_system_activation', '1');
    DB::table('addons')->insert(['name' => 'Delivery boy', 'unique_identifier' => 'delivery_boy', 'activated' => 1]);
    Cache::forget('addons');
    $this->get(route('website.appearance'))->assertInertia(fn (Assert $page) => $page
        ->where('settings.seller_login_page_bg', '')->where('settings.delivery_boy_login_page_bg', '')
        ->where('initialFiles.seller_login_page_bg', [])->where('initialFiles.delivery_boy_login_page_bg', []));
    $this->put(route('website.appearance.update'), ['settings' => websiteAppearancePayload([
        'seller_login_page_bg' => (string) $image->id,
        'delivery_boy_login_page_bg' => (string) $image->id,
    ])])->assertRedirect(route('website.appearance'));
    expect(get_setting('seller_login_page_bg'))->toBe((string) $image->id)
        ->and(get_setting('delivery_boy_login_page_bg'))->toBe((string) $image->id);
});

test('an appearance save writes each key in the legacy stored format and nothing else', function () {
    $staff = actingAsStoreUser(createStoreStaff('website_appearance'));
    $images = [];
    foreach (['site_icon', 'login_page_image', 'register_page_image', 'flash_deal_banner', 'flash_deal_banner_small', 'meta_image'] as $key) {
        $images[$key] = (string) Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image'])->id;
    }
    appearanceSetting('show_website_popup', 'on');
    appearanceSetting('header_script', '<script>keep()</script>');
    appearanceSetting('footer_logo', '3');

    $this->put(route('website.appearance.update'), ['settings' => websiteAppearancePayload([
        ...$images,
        'website_name' => 'Holland Store',
        'site_motto' => 'Fresh food',
        'base_color' => '#d43533',
        'base_hov_color' => '#abc',
        'meta_title' => 'Holland',
        'meta_description' => 'Groceries delivered',
        'meta_keywords' => 'dates, rice',
        'cookies_agreement_text' => '<p>We use <strong>cookies</strong>. <a href="https://example.com/privacy">Privacy</a></p>',
        'show_cookies_agreement' => true,
        'show_website_popup' => false,
        'website_popup_content' => '<h2>Welcome</h2>',
        'show_subscribe_form' => true,
    ])])->assertRedirect(route('website.appearance'));

    foreach ($images as $key => $id) {
        expect(get_setting($key))->toBe($id);
    }
    expect(get_setting('website_name'))->toBe('Holland Store')
        ->and(get_setting('base_hov_color'))->toBe('#abc')
        ->and(get_setting('meta_keywords'))->toBe('dates, rice')
        ->and(get_setting('cookies_agreement_text'))->toBe('<p>We use <strong>cookies</strong>. <a href="https://example.com/privacy" rel="noopener noreferrer nofollow">Privacy</a></p>')
        ->and(get_setting('show_cookies_agreement'))->toBe('on')
        ->and(get_setting('show_website_popup'))->toBeNull()
        ->and(get_setting('show_subscribe_form'))->toBe('on')
        ->and(get_setting('website_popup_content'))->toBe('<h2>Welcome</h2>')
        ->and(get_setting('header_script'))->toBe('<script>keep()</script>')
        ->and(get_setting('footer_logo'))->toBe('3')
        ->and(DB::table('business_settings')->whereNotIn('type', [...UpdateWebsiteAppearanceRequest::KEYS, 'header_script', 'footer_logo'])->count())->toBe(0)
        ->and(DB::table('business_settings')->whereNotNull('lang')->count())->toBe(0);
});

test('the appearance writer refuses foreign, reserved, environment, script and general settings keys before any write', function (string $key, bool $topLevel) {
    actingAsStoreUser(createStoreStaff('website_appearance'));
    appearanceSetting('website_name', 'unchanged');
    $before = appearanceSettingsSnapshot();
    $payload = ['settings' => websiteAppearancePayload(['website_name' => 'changed'])];
    if ($topLevel) {
        $payload[$key] = 'forged';
    } else {
        $payload['settings'][$key] = 'forged';
    }
    $this->put(route('website.appearance.update'), $payload)->assertSessionHasErrors($topLevel ? $key : 'settings');
    expect(appearanceSettingsSnapshot())->toEqual($before);
})->with([
    'header script' => ['header_script', false],
    'footer script' => ['footer_script', false],
    'top-level header script' => ['header_script', true],
    'top-level footer script' => ['footer_script', true],
    'footer key' => ['footer_logo', false],
    'header key' => ['header_logo', false],
    'general settings key' => ['site_name', false],
    'timezone' => ['timezone', false],
    'reserved shipping key' => ['shipping_type', false],
    'environment key' => ['FORCE_HTTPS', false],
    'top-level environment key' => ['APP_KEY', true],
    'legacy types' => ['types', true],
    'language' => ['lang', true],
]);

test('every other settings page key is refused by the appearance writer', function () {
    actingAsStoreUser(createStoreStaff('website_appearance'));
    $before = appearanceSettingsSnapshot();
    foreach (array_diff([...UpdateWebsiteFooterRequest::KEYS, ...UpdateWebsiteHeaderRequest::KEYS, ...UpdateGeneralSettingsRequest::KEYS], UpdateWebsiteAppearanceRequest::KEYS) as $key) {
        $this->put(route('website.appearance.update'), ['settings' => websiteAppearancePayload([$key => '1'])])->assertSessionHasErrors('settings');
    }
    expect(appearanceSettingsSnapshot())->toEqual($before);
});

test('appearance rich text is cleaned of scripts, handlers and unsafe URLs while its formatting is kept', function (string $hostile, string $removed) {
    $this->withoutMiddleware(BlockMaliciousRequests::class);
    actingAsStoreUser(createStoreStaff('website_appearance'));
    $this->put(route('website.appearance.update'), ['settings' => websiteAppearancePayload([
        'cookies_agreement_text' => '<p><strong>Kept</strong></p>'.$hostile,
        'website_popup_content' => '<h2 style="text-align: center">Kept</h2>'.$hostile,
    ])])->assertSessionHasNoErrors()->assertRedirect();
    foreach (['cookies_agreement_text' => '<p><strong>Kept</strong></p>', 'website_popup_content' => '<h2 style="text-align: center">Kept</h2>'] as $key => $kept) {
        $stored = get_setting($key);
        expect($stored)->toStartWith($kept)->not->toContain($removed)->and(clean_html($stored))->toBe($stored);
    }
})->with([
    'script element' => ['<script>alert(1)</script>', 'alert'],
    'image error handler' => ['<img src="x" onerror="alert(1)">', 'onerror'],
    'toggle handler' => ['<details open ontoggle="alert(1)">x</details>', 'ontoggle'],
    'javascript link' => ['<a href="javascript:alert(1)">Link</a>', 'javascript'],
    'encoded javascript link' => ['<a href="jav&#x61;script:alert(1)">Link</a>', 'script:'],
    'object' => ['<object data="https://example.com/x"></object>', 'object'],
    'html data image' => ['<img src="data:text/html;base64,PHNjcmlwdD4=">', 'data:'],
]);

test('the WAF still refuses a raw script payload in the appearance rich text before any write', function () {
    actingAsStoreUser(createStoreStaff('website_appearance'));
    $before = appearanceSettingsSnapshot();
    $this->put(route('website.appearance.update'), ['settings' => websiteAppearancePayload(['website_popup_content' => '<script>alert(1)</script>'])])->assertForbidden();
    expect(appearanceSettingsSnapshot())->toEqual($before);
});

test('appearance fields are validated before any write', function (string $key, mixed $value) {
    config(['waf.enabled' => false]);
    actingAsStoreUser(createStoreStaff('website_appearance'));
    $before = appearanceSettingsSnapshot();
    $this->put(route('website.appearance.update'), ['settings' => websiteAppearancePayload([$key => $value])])->assertSessionHasErrors('settings.'.$key);
    expect(appearanceSettingsSnapshot())->toEqual($before);
})->with([
    'css injection colour' => ['base_color', 'red; background: url(x)'],
    'named colour' => ['base_hov_color', 'red'],
    'four digit colour' => ['base_color', '#abcd'],
    'long name' => ['website_name', str_repeat('n', 256)],
    'long motto' => ['site_motto', str_repeat('m', 256)],
    'long meta title' => ['meta_title', str_repeat('t', 256)],
    'switch' => ['show_subscribe_form', 'yes please'],
    'missing switch' => ['show_cookies_agreement', null],
    'image id' => ['site_icon', 'abc'],
    'missing image' => ['meta_image', '999999'],
]);

test('appearance images allow own and library images while a retained foreign image grants no replacement or preview access', function () {
    $staff = actingAsStoreUser(createStoreStaff('website_appearance'));
    $own = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image']);
    $foreign = Upload::factory()->create(['user_id' => createStoreUser('customer')->id, 'type' => 'image']);
    $otherForeign = Upload::factory()->create(['user_id' => createStoreUser('customer')->id, 'type' => 'image']);
    $document = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'document']);
    $save = fn (array $settings) => $this->put(route('website.appearance.update'), ['settings' => websiteAppearancePayload($settings)]);

    foreach ([$foreign, $document] as $upload) {
        $before = appearanceSettingsSnapshot();
        $save(['site_icon' => (string) $upload->id])->assertSessionHasErrors('settings.site_icon');
        expect(appearanceSettingsSnapshot())->toEqual($before);
    }
    $save(['site_icon' => (string) $own->id])->assertSessionHasNoErrors()->assertRedirect();
    expect(get_setting('site_icon'))->toBe((string) $own->id);
    DB::table('business_settings')->where('type', 'meta_image')->update(['value' => (string) $foreign->id]);
    $this->get(route('website.appearance'))->assertInertia(fn (Assert $page) => $page->where('initialFiles.meta_image', [])->where('settings.meta_image', (string) $foreign->id));
    $save(['site_icon' => (string) $own->id, 'meta_image' => (string) $foreign->id])->assertSessionHasNoErrors()->assertRedirect();
    expect(get_setting('meta_image'))->toBe((string) $foreign->id);
    $save(['meta_image' => (string) $otherForeign->id])->assertSessionHasErrors('settings.meta_image');
    actingAsStoreUser(createStoreStaff('website_appearance', 'view_uploads'));
    $save(['meta_image' => (string) $otherForeign->id])->assertSessionHasNoErrors()->assertRedirect();
    expect(get_setting('meta_image'))->toBe((string) $otherForeign->id)->and(get_setting('site_icon'))->toBeNull();
});

test('the appearance screen refuses unauthorized callers without changes', function (string $caller) {
    $before = appearanceSettingsSnapshot();
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route('website.appearance')), $caller);
    assertStoreCallerRefused($this->put(route('website.appearance.update'), ['settings' => websiteAppearancePayload(['website_name' => 'Refused'])]), $caller);
    assertStoreCallerRefused($this->post(route('website.scripts.compatibility.update'), ['types' => ['header_script', 'footer_script'], 'header_script' => 'refused', 'footer_script' => null]), $caller);
    expect(appearanceSettingsSnapshot())->toEqual($before);
})->with('refused store admin callers');

test('neighbouring website and settings permissions grant no appearance access', function (string $permission) {
    actingAsStoreUser(createStoreStaff($permission));
    $before = appearanceSettingsSnapshot();
    $this->get(route('website.appearance'))->assertForbidden();
    $this->put(route('website.appearance.update'), ['settings' => websiteAppearancePayload(['website_name' => 'Refused'])])->assertForbidden();
    $this->post(route('website.scripts.compatibility.update'), ['types' => ['header_script', 'footer_script'], 'header_script' => 'refused', 'footer_script' => null])->assertForbidden();
    expect(appearanceSettingsSnapshot())->toEqual($before);
})->with(['header_setup', 'footer_setup', 'general_settings', 'edit_website_page']);

test('WMS authentication grants no appearance action and no store writes', function () {
    $this->actingAs(User::factory()->create(), 'web');
    $before = appearanceSettingsSnapshot();
    $this->get(route('website.appearance'))->assertRedirect(route('shop.login'));
    $this->put(route('website.appearance.update'), ['settings' => websiteAppearancePayload(['website_name' => 'WMS'])])->assertRedirect(route('shop.login'));
    $this->post(route('website.scripts.compatibility.update'), ['types' => ['header_script', 'footer_script'], 'header_script' => 'wms', 'footer_script' => null])->assertRedirect(route('shop.login'));
    expect(appearanceSettingsSnapshot())->toEqual($before);
});

test('the scripts compatibility endpoint is unchanged: two raw keys, back to the appearance screen, printed raw on the storefront', function () {
    seedStoreCurrency();
    actingAsStoreUser(createStoreStaff('website_appearance'));
    $header = '<meta name="e2e-verification" content="abc123"><style>.promo { color: #d43533 }</style>';
    $footer = '<noscript><img src="https://example.com/pixel?id=1&amp;ev=PageView"></noscript>';
    $this->post(route('website.scripts.compatibility.update'), ['types' => ['header_script', 'footer_script'], 'header_script' => $header, 'footer_script' => $footer])
        ->assertRedirect(route('website.appearance'));
    expect(get_setting('header_script'))->toBe($header)->and(get_setting('footer_script'))->toBe($footer);
    $this->get(route('website.appearance'))->assertInertia(fn (Assert $page) => $page->where('scripts', ['header_script' => $header, 'footer_script' => $footer]));

    $before = appearanceSettingsSnapshot();
    $this->post(route('website.scripts.compatibility.update'), ['types' => ['header_script', 'footer_script'], 'header_script' => '<script>proposed()</script>', 'footer_script' => null])->assertForbidden();
    $this->postJson(route('website.scripts.compatibility.update'), ['types' => ['header_script', 'footer_script'], 'header_script' => 'x', 'footer_script' => null, 'website_name' => 'Injected'])->assertUnprocessable();
    $this->postJson(route('website.scripts.compatibility.update'), ['settings' => ['header_script' => 'x']])->assertUnprocessable();
    expect(appearanceSettingsSnapshot())->toEqual($before);

    auth('store')->logout();
    $html = $this->get('/temp/users/login')->assertOk()->getContent();
    expect($html)->toContain($header)->toContain($footer)
        ->and(strpos($html, $header))->toBeLessThan(strpos($html, '</head>'))
        ->and(strpos($html, $footer))->toBeLessThan(strpos($html, '</body>'));
});

test('a saved appearance still renders on the storefront in its stored representation', function () {
    seedStoreCurrency();
    $staff = actingAsStoreUser(createStoreStaff('website_appearance'));
    $icon = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image', 'external_link' => 'https://example.com/icon.png']);
    $this->put(route('website.appearance.update'), ['settings' => websiteAppearancePayload([
        'website_name' => 'Holland Store',
        'site_motto' => 'Fresh food',
        'site_icon' => (string) $icon->id,
        'base_color' => '#123456',
        'meta_description' => 'Groceries delivered',
        'cookies_agreement_text' => '<p>We use <strong>cookies</strong></p>',
        'show_cookies_agreement' => true,
    ])])->assertSessionHasNoErrors()->assertRedirect();
    auth('store')->logout();

    $this->get('/temp/users/login')->assertOk()
        ->assertSee('<title>Holland Store | Fresh food</title>', false)
        ->assertSee('<link rel="icon" href="https://example.com/icon.png">', false)
        ->assertSee('--primary: #123456;', false)
        ->assertSee('content="Groceries delivered"', false)
        ->assertSee('<p>We use <strong>cookies</strong></p>', false);
    $this->getJson('/temp/api/v2/business-settings')->assertOk()->assertJsonFragment(['type' => 'site_icon']);
});

test('the appearance routes keep their names and the Blade screen is gone', function () {
    expect(route('website.appearance', [], false))->toBe('/temp/admin/website/appearance')
        ->and(route('website.appearance.update', [], false))->toBe('/temp/admin/website/appearance')
        ->and(route('website.scripts.compatibility.update', [], false))->toBe('/temp/admin/website/scripts/compatibility')
        ->and(view()->exists('shop.backend.website_settings.appearance'))->toBeFalse();
});
