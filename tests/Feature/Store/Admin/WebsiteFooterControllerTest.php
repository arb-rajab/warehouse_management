<?php

use App\Http\Middleware\BlockMaliciousRequests;
use App\Http\Requests\Store\Admin\UpdateGeneralSettingsRequest;
use App\Http\Requests\Store\Admin\UpdateWebsiteAppearanceRequest;
use App\Http\Requests\Store\Admin\UpdateWebsiteFooterRequest;
use App\Http\Requests\Store\Admin\UpdateWebsiteHeaderRequest;
use App\Models\Language;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['app.default_language' => 'en']);
    Language::forceCreate(['code' => 'en', 'name' => 'English', 'rtl' => 0]);
    Language::forceCreate(['code' => 'ar', 'name' => 'Arabic', 'rtl' => 1]);
});

function footerSetting(string $type, ?string $value, ?string $lang = null): void
{
    DB::table('business_settings')->insert(['type' => $type, 'value' => $value, 'lang' => $lang]);
}

/** @return list<array<string, mixed>> */
function footerSettingsSnapshot(): array
{
    return DB::table('business_settings')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
}

test('the footer screen renders the Inertia page', function () {
    actingAsStoreUser(createStoreStaff('footer_setup'));
    $this->get(route('website.footer'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Website/Footer'));
});

test('the footer sends exactly its own settings for the chosen language, cleaned, with permitted previews', function () {
    $staff = actingAsStoreUser(createStoreStaff('footer_setup'));
    $logo = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image', 'file_original_name' => 'Footer logo', 'file_size' => 64, 'external_link' => 'https://example.com/footer.png']);
    $visa = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image', 'file_original_name' => 'Visa', 'file_size' => 32, 'external_link' => 'https://example.com/visa.png']);
    $foreign = Upload::factory()->create(['user_id' => createStoreUser('customer')->id, 'type' => 'image']);
    footerSetting('footer_logo', (string) $logo->id);
    footerSetting('about_us_description', '<p>About <b>us</b></p><script>alert(1)</script>', 'en');
    footerSetting('about_us_description', '<p>عنا</p>', 'ar');
    footerSetting('contact_address', 'Main street 1', 'en');
    footerSetting('contact_address', 'الشارع الرئيسي', 'ar');
    footerSetting('contact_phone', '+31 20 123 4567');
    footerSetting('contact_email', 'shop@example.com');
    footerSetting('widget_one', 'Links', 'en');
    footerSetting('widget_one_labels', '["Help","Terms"]', 'en');
    footerSetting('widget_one_labels', '["مساعدة"]', 'ar');
    footerSetting('widget_one_links', '["\/help","https:\/\/example.com\/terms","\/orphan"]');
    footerSetting('frontend_copyright_text', '<p>© Store <img src="x" onerror="alert(1)"></p>', 'en');
    footerSetting('show_social_links', 'on');
    footerSetting('facebook_link', 'https://facebook.example.com/store');
    footerSetting('play_store_link', 'https://play.example.com/store');
    footerSetting('payment_method_images', $visa->id.','.$foreign->id);
    footerSetting('registration_tutorial_button', '1');
    footerSetting('registration_tutorial_text', 'How to register');
    footerSetting('registration_tutorial_link', '/help/register');
    footerSetting('seller_app_link', 'https://example.com/seller');
    footerSetting('header_script', '<script>secret()</script>');
    footerSetting('smtp_password', 'private');
    $visaPreview = ['id' => $visa->id, 'name' => 'Visa', 'type' => 'image', 'size' => 32, 'url' => 'https://example.com/visa.png'];

    $response = $this->get(route('website.footer', ['lang' => 'en']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Website/Footer')
        ->has('settings', fn (Assert $settings) => $settings
            ->where('footer_logo', (string) $logo->id)
            ->where('about_us_description', '<p>About <b>us</b></p>')
            ->where('play_store_link', 'https://play.example.com/store')
            ->where('app_store_link', '')
            ->where('contact_address', 'Main street 1')
            ->where('contact_phone', '+31 20 123 4567')
            ->where('contact_email', 'shop@example.com')
            ->where('widget_one', 'Links')
            ->where('widget_one_labels', ['Help', 'Terms'])
            ->where('widget_one_links', ['/help', 'https://example.com/terms'])
            ->where('frontend_copyright_text', '<p>© Store <img src="x"></p>')
            ->where('show_social_links', true)
            ->where('facebook_link', 'https://facebook.example.com/store')
            ->where('twitter_link', '')
            ->where('instagram_link', '')
            ->where('youtube_link', '')
            ->where('linkedin_link', '')
            ->where('payment_method_images', $visa->id.','.$foreign->id)
            ->where('registration_tutorial_button', true)
            ->where('registration_tutorial_text', 'How to register')
            ->where('registration_tutorial_link', '/help/register'))
        ->where('lang', 'en')
        ->where('languages', [['code' => 'en', 'name' => 'English'], ['code' => 'ar', 'name' => 'Arabic']])
        ->where('initialFiles', [
            'footer_logo' => [['id' => $logo->id, 'name' => 'Footer logo', 'type' => 'image', 'size' => 64, 'url' => 'https://example.com/footer.png']],
            'payment_method_images' => [$visaPreview],
        ]));
    $response->assertDontSee('secret()')->assertDontSee('private')->assertDontSee('https://example.com/seller');

    $this->get(route('website.footer', ['lang' => 'ar']))->assertInertia(fn (Assert $page) => $page
        ->where('lang', 'ar')
        ->where('settings.about_us_description', '<p>عنا</p>')
        ->where('settings.contact_address', 'الشارع الرئيسي')
        ->where('settings.widget_one', 'Links')
        ->where('settings.widget_one_labels', ['مساعدة'])
        ->where('settings.widget_one_links', ['/help'])
        ->where('settings.frontend_copyright_text', '<p>© Store <img src="x"></p>'));
    $this->withSession(['locale' => 'ar'])->get(route('website.footer'))->assertInertia(fn (Assert $page) => $page->where('lang', 'ar'));
    // Each real request boots with APP_LOCALE, independent of the previous request.
    config(['app.locale' => 'en']);
    $this->withSession(['locale' => 'fr'])->get(route('website.footer'))->assertInertia(fn (Assert $page) => $page->where('lang', 'en'));
    $this->getJson(route('website.footer', ['lang' => 'fr']))->assertUnprocessable()->assertJsonValidationErrors('lang');
});

test('the footer offers the seller and delivery boy app links only while their feature is on', function () {
    actingAsStoreUser(createStoreStaff('footer_setup'));
    footerSetting('seller_app_link', 'https://example.com/seller');
    footerSetting('delivery_boy_app_link', 'https://example.com/delivery');
    $this->get(route('website.footer'))->assertInertia(fn (Assert $page) => $page
        ->missing('settings.seller_app_link')->missing('settings.delivery_boy_app_link'));
    $this->put(route('website.footer.update'), ['settings' => websiteFooterPayload(['seller_app_link' => 'https://example.com/new']), 'lang' => 'en'])
        ->assertSessionHasErrors('settings');

    footerSetting('vendor_system_activation', '1');
    DB::table('addons')->insert(['name' => 'Delivery boy', 'unique_identifier' => 'delivery_boy', 'activated' => 1]);
    Cache::forget('addons');
    $this->get(route('website.footer'))->assertInertia(fn (Assert $page) => $page
        ->where('settings.seller_app_link', 'https://example.com/seller')
        ->where('settings.delivery_boy_app_link', 'https://example.com/delivery'));
    $this->put(route('website.footer.update'), ['settings' => websiteFooterPayload([
        'seller_app_link' => 'https://example.com/new-seller',
        'delivery_boy_app_link' => '',
    ]), 'lang' => 'en'])->assertRedirect(route('website.footer', ['lang' => 'en']));
    expect(get_setting('seller_app_link'))->toBe('https://example.com/new-seller')
        ->and(get_setting('delivery_boy_app_link'))->toBeNull();
});

test('a footer save writes each key in the legacy stored format, the translatable ones for its language only', function () {
    $staff = actingAsStoreUser(createStoreStaff('footer_setup'));
    $logo = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image']);
    $visa = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image']);
    $mastercard = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image']);
    footerSetting('about_us_description', '<p>English about</p>', 'en');
    footerSetting('widget_one_labels', '["Help"]', 'en');
    footerSetting('show_social_links', 'on');
    footerSetting('header_script', '<script>keep()</script>');
    footerSetting('website_name', 'Untouched');

    $this->put(route('website.footer.update'), ['settings' => websiteFooterPayload([
        'footer_logo' => (string) $logo->id,
        'about_us_description' => '<p>نبذة <strong>عنا</strong></p>',
        'play_store_link' => 'https://play.example.com/store',
        'contact_address' => 'العنوان',
        'contact_phone' => '+31 20 123 4567',
        'contact_email' => 'shop@example.com',
        'widget_one' => 'روابط',
        'widget_one_labels' => ['مساعدة', 'الشروط'],
        'widget_one_links' => ['/help', 'https://example.com/terms'],
        'frontend_copyright_text' => '<p>© <a href="https://example.com">Store</a></p>',
        'show_social_links' => false,
        'facebook_link' => 'https://facebook.example.com/store',
        'payment_method_images' => $visa->id.','.$mastercard->id,
        'registration_tutorial_button' => true,
        'registration_tutorial_text' => 'How to register',
        'registration_tutorial_link' => '#register',
    ]), 'lang' => 'ar'])->assertRedirect(route('website.footer', ['lang' => 'ar']));

    $stored = fn (string $type, ?string $lang = null) => DB::table('business_settings')->where('type', $type)->where('lang', $lang)->value('value');
    expect($stored('footer_logo'))->toBe((string) $logo->id)
        ->and($stored('about_us_description', 'ar'))->toBe('<p>نبذة <strong>عنا</strong></p>')
        ->and($stored('about_us_description', 'en'))->toBe('<p>English about</p>')
        ->and($stored('contact_address', 'ar'))->toBe('العنوان')
        ->and($stored('widget_one', 'ar'))->toBe('روابط')
        ->and($stored('widget_one_labels', 'ar'))->toBe(json_encode(['مساعدة', 'الشروط']))
        ->and($stored('widget_one_labels', 'en'))->toBe('["Help"]')
        ->and($stored('widget_one_links'))->toBe('["\/help","https:\/\/example.com\/terms"]')
        ->and($stored('frontend_copyright_text', 'ar'))->toBe('<p>© <a href="https://example.com" rel="noopener noreferrer nofollow">Store</a></p>')
        ->and($stored('show_social_links'))->toBeNull()
        ->and($stored('registration_tutorial_button'))->toBe('on')
        ->and($stored('payment_method_images'))->toBe($visa->id.','.$mastercard->id)
        ->and($stored('contact_phone'))->toBe('+31 20 123 4567')
        ->and($stored('app_store_link'))->toBeNull()
        ->and($stored('header_script'))->toBe('<script>keep()</script>')
        ->and($stored('website_name'))->toBe('Untouched')
        ->and(DB::table('business_settings')->whereNotIn('type', [...UpdateWebsiteFooterRequest::KEYS, 'header_script', 'website_name'])->count())->toBe(0)
        ->and(get_setting('about_us_description', null, 'ar'))->toBe('<p>نبذة <strong>عنا</strong></p>');
});

test('the footer writer refuses foreign, reserved, environment, script and general settings keys before any write', function (string $key, bool $topLevel) {
    actingAsStoreUser(createStoreStaff('footer_setup'));
    footerSetting('contact_phone', 'unchanged');
    $before = footerSettingsSnapshot();
    $payload = ['settings' => websiteFooterPayload(['contact_phone' => 'changed']), 'lang' => 'en'];
    if ($topLevel) {
        $payload[$key] = 'forged';
    } else {
        $payload['settings'][$key] = 'forged';
    }
    $this->put(route('website.footer.update'), $payload)->assertSessionHasErrors($topLevel ? $key : 'settings');
    expect(footerSettingsSnapshot())->toEqual($before);
})->with([
    'appearance key' => ['website_name', false],
    'header key' => ['header_logo', false],
    'general settings key' => ['site_name', false],
    'timezone' => ['timezone', false],
    'header script' => ['header_script', false],
    'footer script' => ['footer_script', false],
    'top-level script' => ['footer_script', true],
    'reserved currency key' => ['system_default_currency', false],
    'maintenance mode' => ['maintenance_mode', false],
    'environment key' => ['APP_KEY', false],
    'top-level environment key' => ['MAIL_PASSWORD', true],
    'legacy types' => ['types', true],
    'unknown' => ['planted_setting', false],
]);

test('every other settings page key is refused by the footer writer', function () {
    actingAsStoreUser(createStoreStaff('footer_setup'));
    $before = footerSettingsSnapshot();
    foreach (array_diff([...UpdateWebsiteAppearanceRequest::KEYS, ...UpdateWebsiteHeaderRequest::KEYS, ...UpdateGeneralSettingsRequest::KEYS], UpdateWebsiteFooterRequest::KEYS) as $key) {
        $this->put(route('website.footer.update'), ['settings' => websiteFooterPayload([$key => '1']), 'lang' => 'en'])->assertSessionHasErrors('settings');
    }
    expect(footerSettingsSnapshot())->toEqual($before);
});

test('footer rich text is cleaned of scripts, handlers and unsafe URLs while its formatting is kept', function (string $hostile, string $removed) {
    $this->withoutMiddleware(BlockMaliciousRequests::class);
    actingAsStoreUser(createStoreStaff('footer_setup'));
    $this->put(route('website.footer.update'), ['settings' => websiteFooterPayload([
        'about_us_description' => '<p style="text-align: center"><strong>Kept</strong> <em>text</em></p>'.$hostile,
        'frontend_copyright_text' => '<p><u>Kept</u></p>'.$hostile,
    ]), 'lang' => 'en'])->assertSessionHasNoErrors()->assertRedirect();
    $about = get_setting('about_us_description', null, 'en');
    $copyright = get_setting('frontend_copyright_text', null, 'en');
    expect($about)->toStartWith('<p style="text-align: center"><strong>Kept</strong> <em>text</em></p>')->not->toContain($removed)
        ->and($copyright)->toStartWith('<p><u>Kept</u></p>')->not->toContain($removed)
        ->and(clean_html($about))->toBe($about)
        ->and(clean_html($copyright))->toBe($copyright);
})->with([
    'script element' => ['<script>alert(1)</script>', 'alert'],
    'image error handler' => ['<img src="x" onerror="alert(1)">', 'onerror'],
    'focus handler' => ['<p tabindex="0" onfocus="alert(1)">Focus</p>', 'onfocus'],
    'javascript link' => ['<a href="javascript:alert(1)">Link</a>', 'javascript'],
    'obfuscated javascript link' => ['<a href="java&#x09;script:alert(1)">Link</a>', 'script:'],
    'svg script' => ['<svg><script>alert(1)</script></svg>', 'svg'],
    'iframe' => ['<iframe src="https://example.com"></iframe>', 'iframe'],
    'style url' => ['<p style="background-image: url(https://example.com/x.png)">Styled</p>', 'url('],
]);

test('the WAF still refuses a raw script payload in the footer before any write', function () {
    actingAsStoreUser(createStoreStaff('footer_setup'));
    $before = footerSettingsSnapshot();
    $this->put(route('website.footer.update'), ['settings' => websiteFooterPayload(['about_us_description' => '<p>Text</p><script>alert(1)</script>']), 'lang' => 'en'])->assertForbidden();
    expect(footerSettingsSnapshot())->toEqual($before);
});

test('legacy footer markup written by the Blade editor round-trips unchanged', function () {
    actingAsStoreUser(createStoreStaff('footer_setup'));
    $legacy = '<p><b>Bold</b> <u>under</u> <i>italic</i></p><ul><li>One</li><li>Two</li></ul><ol><li>First</li></ol>';
    footerSetting('about_us_description', $legacy, 'en');
    $this->get(route('website.footer', ['lang' => 'en']))->assertInertia(fn (Assert $page) => $page->where('settings.about_us_description', $legacy));
    $this->put(route('website.footer.update'), ['settings' => websiteFooterPayload(['about_us_description' => $legacy]), 'lang' => 'en'])->assertSessionHasNoErrors()->assertRedirect();
    expect(get_setting('about_us_description', null, 'en'))->toBe($legacy);
});

test('footer fields are validated before any write', function (string $key, mixed $value, ?string $error = null) {
    config(['waf.enabled' => false]);
    actingAsStoreUser(createStoreStaff('footer_setup'));
    $before = footerSettingsSnapshot();
    $this->put(route('website.footer.update'), ['settings' => websiteFooterPayload([$key => $value]), 'lang' => 'en'])
        ->assertSessionHasErrors($error ?? 'settings.'.$key);
    expect(footerSettingsSnapshot())->toEqual($before);
})->with([
    'javascript link' => ['facebook_link', 'javascript:alert(1)'],
    'protocol-relative link' => ['play_store_link', '//evil.example.com'],
    'spaced link' => ['registration_tutorial_link', 'https://example.com/a b'],
    'long link' => ['twitter_link', 'https://example.com/'.str_repeat('a', 2040)],
    'invalid email' => ['contact_email', 'not-an-email'],
    'long phone' => ['contact_phone', str_repeat('1', 256)],
    'long title' => ['widget_one', str_repeat('t', 256)],
    'switch' => ['show_social_links', 'maybe'],
    'missing switch' => ['registration_tutorial_button', null],
    'unpaired links' => ['widget_one_labels', ['Help'], 'settings.widget_one_links'],
    'blank label' => ['widget_one_labels', [''], 'settings.widget_one_labels.0'],
    'too many labels' => ['widget_one_labels', array_fill(0, 51, 'Label')],
    'payment ids' => ['payment_method_images', '1,,2'],
    'logo id' => ['footer_logo', 'abc'],
]);

test('footer images allow own and library images while retained foreign images grant no replacement or preview access', function () {
    $staff = actingAsStoreUser(createStoreStaff('footer_setup'));
    $own = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image']);
    $foreign = Upload::factory()->create(['user_id' => createStoreUser('customer')->id, 'type' => 'image']);
    $otherForeign = Upload::factory()->create(['user_id' => createStoreUser('customer')->id, 'type' => 'image']);
    $document = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'document']);
    $save = fn (array $settings) => $this->put(route('website.footer.update'), ['settings' => websiteFooterPayload($settings), 'lang' => 'en']);

    foreach ([['footer_logo' => (string) $foreign->id], ['footer_logo' => (string) $document->id], ['payment_method_images' => (string) $foreign->id],
        ['payment_method_images' => $own->id.','.$own->id], ['payment_method_images' => $own->id.','.$document->id], ['payment_method_images' => '999999']] as $invalid) {
        $before = footerSettingsSnapshot();
        $save($invalid)->assertSessionHasErrors('settings.'.array_key_first($invalid));
        expect(footerSettingsSnapshot())->toEqual($before);
    }
    $save(['footer_logo' => (string) $own->id, 'payment_method_images' => (string) $own->id])->assertSessionHasNoErrors()->assertRedirect();
    expect(get_setting('footer_logo'))->toBe((string) $own->id)->and(get_setting('payment_method_images'))->toBe((string) $own->id);

    DB::table('business_settings')->where('type', 'footer_logo')->update(['value' => (string) $foreign->id]);
    DB::table('business_settings')->where('type', 'payment_method_images')->update(['value' => $foreign->id.','.$own->id]);
    $this->get(route('website.footer'))->assertInertia(fn (Assert $page) => $page
        ->where('initialFiles.footer_logo', [])->has('initialFiles.payment_method_images', 1)
        ->where('initialFiles.payment_method_images.0.id', $own->id));
    $save(['footer_logo' => (string) $foreign->id, 'payment_method_images' => $foreign->id.','.$own->id])->assertSessionHasNoErrors()->assertRedirect();
    expect(get_setting('footer_logo'))->toBe((string) $foreign->id)->and(get_setting('payment_method_images'))->toBe($foreign->id.','.$own->id);
    $save(['footer_logo' => (string) $otherForeign->id])->assertSessionHasErrors('settings.footer_logo');
    $save(['payment_method_images' => $foreign->id.','.$otherForeign->id])->assertSessionHasErrors('settings.payment_method_images');

    actingAsStoreUser(createStoreStaff('footer_setup', 'view_uploads'));
    $save(['footer_logo' => (string) $otherForeign->id, 'payment_method_images' => $otherForeign->id.','.$foreign->id])->assertSessionHasNoErrors()->assertRedirect();
    expect(get_setting('footer_logo'))->toBe((string) $otherForeign->id);
    $save([])->assertSessionHasNoErrors()->assertRedirect();
    expect(get_setting('footer_logo'))->toBeNull()->and(get_setting('payment_method_images'))->toBeNull();
});

test('the footer refuses unauthorized callers without changes', function (string $caller) {
    $before = footerSettingsSnapshot();
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route('website.footer')), $caller);
    assertStoreCallerRefused($this->put(route('website.footer.update'), ['settings' => websiteFooterPayload(['contact_phone' => 'refused']), 'lang' => 'en']), $caller);
    expect(footerSettingsSnapshot())->toEqual($before);
})->with('refused store admin callers');

test('neighbouring website and settings permissions grant no footer access', function (string $permission) {
    actingAsStoreUser(createStoreStaff($permission));
    $before = footerSettingsSnapshot();
    $this->get(route('website.footer'))->assertForbidden();
    $this->put(route('website.footer.update'), ['settings' => websiteFooterPayload(['contact_phone' => 'refused']), 'lang' => 'en'])->assertForbidden();
    expect(footerSettingsSnapshot())->toEqual($before);
})->with(['header_setup', 'website_appearance', 'general_settings', 'edit_website_page']);

test('WMS authentication grants no footer action and no store writes', function () {
    $this->actingAs(User::factory()->create(), 'web');
    $before = footerSettingsSnapshot();
    $this->get(route('website.footer'))->assertRedirect(route('shop.login'));
    $this->put(route('website.footer.update'), ['settings' => websiteFooterPayload(['contact_phone' => 'wms']), 'lang' => 'en'])->assertRedirect(route('shop.login'));
    expect(footerSettingsSnapshot())->toEqual($before);
});

test('a saved footer still renders on the storefront in its stored representation', function () {
    seedStoreCurrency();
    $staff = actingAsStoreUser(createStoreStaff('footer_setup'));
    $visa = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image', 'external_link' => 'https://example.com/visa.png']);
    $this->put(route('website.footer.update'), ['settings' => websiteFooterPayload([
        'about_us_description' => '<p>Fresh <strong>dates</strong></p>',
        'contact_address' => 'Main street 1',
        'contact_email' => 'shop@example.com',
        'widget_one' => 'Useful links',
        'widget_one_labels' => ['Help centre'],
        'widget_one_links' => ['/help'],
        'frontend_copyright_text' => '<p>© Holland Store</p>',
        'show_social_links' => true,
        'facebook_link' => 'https://facebook.example.com/store',
        'payment_method_images' => (string) $visa->id,
    ]), 'lang' => 'en'])->assertSessionHasNoErrors()->assertRedirect();
    auth('store')->logout();

    $this->get('/temp/users/login')->assertOk()
        ->assertSee('<p>Fresh <strong>dates</strong></p>', false)
        ->assertSee('Main street 1')
        ->assertSee('mailto:shop@example.com', false)
        ->assertSee('Help centre')
        ->assertSee('href="/help"', false)
        ->assertSee('<p>© Holland Store</p>', false)
        ->assertSee('https://facebook.example.com/store', false)
        ->assertSee('https://example.com/visa.png', false);
});

test('the footer routes keep their names and the Blade screen is gone', function () {
    expect(route('website.footer', [], false))->toBe('/temp/admin/website/footer')
        ->and(route('website.footer.update', [], false))->toBe('/temp/admin/website/footer')
        ->and(Route::has('business_settings.update'))->toBeFalse()
        ->and(view()->exists('shop.backend.website_settings.footer'))->toBeFalse();
});
