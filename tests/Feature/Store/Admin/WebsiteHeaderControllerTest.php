<?php

use App\Http\Requests\Store\Admin\UpdateWebsiteHeaderRequest;
use App\Models\BusinessSetting;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function websiteHeaderPayload(array $overrides = []): array
{
    return array_replace([
        'header_logo' => '', 'show_language_switcher' => true, 'show_currency_switcher' => false,
        'header_stikcy' => true, 'topbar_banner' => '', 'topbar_banner_medium' => '', 'topbar_banner_small' => '',
        'topbar_banner_link' => '/offers', 'helpline_number' => '12345',
        'header_menu_labels' => ['Offers'], 'header_menu_links' => ['/offers'],
    ], $overrides);
}

function headerSetting(string $key, ?string $value, ?string $lang = null): BusinessSetting
{
    return BusinessSetting::forceCreate(['type' => $key, 'value' => $value, 'lang' => $lang]);
}

test('the header sends only its own eleven settings in the storefront representation', function () {
    actingAsStoreUser(createStoreStaff('header_setup'));
    headerSetting('header_logo', '17');
    headerSetting('header_stikcy', 'on');
    headerSetting('header_menu_labels', '["Offers"]');
    headerSetting('header_menu_links', '["/offers"]');
    headerSetting('header_script', 'secret script');
    headerSetting('footer_text', 'private to footer');

    $this->get(route('website.header'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Website/Header')
        ->has('settings', count(UpdateWebsiteHeaderRequest::KEYS))
        ->where('settings', websiteHeaderPayload([
            'header_logo' => '17', 'show_language_switcher' => false, 'topbar_banner_link' => '', 'helpline_number' => '',
        ])));
});

test('a header save keeps the legacy keys and values without clearing the shared cache', function () {
    $staff = actingAsStoreUser(createStoreStaff('header_setup'));
    $image = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image']);
    headerSetting('header_stikcy', 'on');
    $footer = headerSetting('footer_text', 'Keep footer');
    $script = headerSetting('header_script', 'Keep existing script');
    headerSetting('header_menu_labels', '["Old"]');
    $translation = headerSetting('header_menu_labels', '["Arabic"]', 'ar');
    Cache::put('wms-header-test', 'keep');
    Cache::put('business_settings', 'stale');
    $payload = websiteHeaderPayload(['header_logo' => (string) $image->id, 'header_stikcy' => false]);

    $this->put(route('website.header.update'), ['settings' => $payload])->assertRedirect(route('website.header'));

    expect(get_setting('header_logo'))->toBe((string) $image->id)
        ->and(get_setting('show_language_switcher'))->toBe('on')
        ->and(get_setting('show_currency_switcher'))->toBeNull()
        ->and(get_setting('header_stikcy'))->toBeNull()
        ->and(get_setting('header_menu_labels'))->toBe('["Offers"]')
        ->and(json_decode(get_setting('header_menu_links'), true))->toBe(['/offers'])
        ->and($footer->fresh()->value)->toBe('Keep footer')
        ->and($script->fresh()->value)->toBe('Keep existing script')
        ->and($translation->fresh()->value)->toBe('["Arabic"]')
        ->and(Cache::get('wms-header-test'))->toBe('keep');
    seedStoreCurrency();
    DB::table('languages')->insert(['code' => 'en', 'name' => 'English', 'status' => 1, 'rtl' => 0]);
    $this->get('/temp')->assertOk()->assertViewIs('shop.frontend.index')->assertSee('Offers')->assertSee('href="/offers"', false);
});

test('another settings permission grants neither header reads nor writes', function () {
    actingAsStoreUser(createStoreStaff('currency_setup'));
    $setting = headerSetting('helpline_number', 'unchanged');
    $this->get(route('website.header'))->assertForbidden();
    $this->put(route('website.header.update'), ['settings' => websiteHeaderPayload()])->assertForbidden();
    expect($setting->fresh()->value)->toBe('unchanged')->and(BusinessSetting::count())->toBe(1);
});

test('a header-only editor can modify other settings neither through the retired generic endpoint nor the footer and appearance writers', function () {
    actingAsStoreUser(createStoreStaff('header_setup'));
    $setting = headerSetting('footer_text', 'unchanged');
    $this->post(retiredSettingsWriterUri(), ['types' => ['footer_text'], 'footer_text' => 'forged'])->assertNotFound();
    $this->put(route('website.footer.update'), ['settings' => ['footer_logo' => null], 'lang' => 'en'])->assertForbidden();
    $this->put(route('website.appearance.update'), ['settings' => ['website_name' => 'forged']])->assertForbidden();
    expect($setting->fresh()->value)->toBe('unchanged')->and(BusinessSetting::count())->toBe(1);
});

test('website appearance editors and super admins cannot write scripts through ordinary settings', function (bool $superAdmin) {
    actingAsStoreUser($superAdmin ? createStoreAdmin() : createStoreStaff('website_appearance'));
    $setting = headerSetting('header_script', 'unchanged');
    $this->post(retiredSettingsWriterUri(), ['types' => ['helpline_number', 'header_script'], 'helpline_number' => 'forged', 'header_script' => 'forged'])->assertNotFound();
    $this->put(route('website.appearance.update'), ['settings' => ['website_name' => 'forged', 'header_script' => 'forged']])->assertSessionHasErrors('settings');
    $this->put(route('website.appearance.update'), ['settings' => ['website_name' => 'forged'], 'header_script' => 'forged'])->assertSessionHasErrors('header_script');
    expect($setting->fresh()->value)->toBe('unchanged')->and(BusinessSetting::count())->toBe(1);
})->with([false, true]);

test('unknown and cross-page keys refuse the complete header write', function (string $key, bool $topLevel) {
    actingAsStoreUser(createStoreStaff('header_setup'));
    $setting = headerSetting('helpline_number', 'unchanged');
    $data = ['settings' => websiteHeaderPayload()];
    if ($topLevel) {
        $data[$key] = 'forged';
    } else {
        $data['settings'][$key] = 'forged';
    }
    $this->put(route('website.header.update'), $data)->assertSessionHasErrors($topLevel ? $key : 'settings');
    expect($setting->fresh()->value)->toBe('unchanged')->and(BusinessSetting::count())->toBe(1);
})->with([
    'footer key' => ['footer_text', false], 'arbitrary key' => ['unknown', false],
    'header script' => ['header_script', false], 'footer script' => ['footer_script', false],
    'top-level script' => ['header_script', true], 'legacy types' => ['types', true],
]);

test('the retired generic endpoint cannot bypass header isolation or write scripts', function (string $key, bool $translated) {
    actingAsStoreUser(createStoreStaff('currency_setup'));
    $setting = headerSetting($key, 'unchanged');
    $types = $translated ? [['ar' => $key]] : [$key];
    $this->post(retiredSettingsWriterUri(), ['types' => $types, $key => 'forged'])->assertNotFound();
    expect($setting->fresh()->value)->toBe('unchanged')->and(BusinessSetting::count())->toBe(1);
})->with(['header_logo', 'helpline_number', 'header_menu_labels', 'header_script', 'footer_script'])->with([false, true]);

test('invalid header fields refuse all writes', function (string $key, mixed $value) {
    config(['waf.enabled' => false]);
    actingAsStoreUser(createStoreStaff('header_setup'));
    $setting = headerSetting('helpline_number', 'unchanged');
    $this->put(route('website.header.update'), ['settings' => websiteHeaderPayload([$key => $value])])->assertSessionHasErrors('settings.'.$key);
    expect($setting->fresh()->value)->toBe('unchanged')->and(BusinessSetting::count())->toBe(1);
})->with([
    'missing image' => ['header_logo', '999999'], 'CSV image IDs' => ['header_logo', '1,2'],
    'nonboolean' => ['header_stikcy', 'invalid'], 'too long phone' => ['helpline_number', str_repeat('1', 101)],
    'script URL' => ['topbar_banner_link', 'javascript:alert(1)'], 'protocol relative' => ['topbar_banner_link', '//evil.test'],
    'unpaired menus' => ['header_menu_links', []], 'scalar menu' => ['header_menu_labels', 'bad'],
]);

test('a non-image upload cannot be used for a header image', function () {
    $staff = actingAsStoreUser(createStoreStaff('header_setup'));
    $file = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'document']);
    $this->put(route('website.header.update'), ['settings' => websiteHeaderPayload(['header_logo' => (string) $file->id])])->assertSessionHasErrors('settings.header_logo');
    expect(BusinessSetting::count())->toBe(0);
});

test('a header editor retains the upload access restriction for accountants', function () {
    $accountant = createStoreStaff('header_setup');
    $accountant->forceFill(['user_type' => 'accountant'])->save();
    actingAsStoreUser($accountant);
    $theirs = Upload::factory()->create(['type' => 'image']);
    $mine = Upload::factory()->create(['user_id' => $accountant->id, 'type' => 'image']);
    $this->put(route('website.header.update'), ['settings' => websiteHeaderPayload(['header_logo' => (string) $theirs->id])])->assertSessionHasErrors('settings.header_logo');
    expect(BusinessSetting::count())->toBe(0);
    $this->put(route('website.header.update'), ['settings' => websiteHeaderPayload(['header_logo' => (string) $mine->id])])->assertRedirect(route('website.header'));
});

test('header settings refuse unauthorized store callers without writes', function (string $caller) {
    $setting = headerSetting('helpline_number', 'unchanged');
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route('website.header')), $caller);
    assertStoreCallerRefused($this->put(route('website.header.update'), ['settings' => websiteHeaderPayload()]), $caller);
    expect($setting->fresh()->value)->toBe('unchanged')->and(BusinessSetting::count())->toBe(1);
})->with('refused store admin callers');

test('a WMS login cannot access store header settings', function () {
    $this->actingAs(User::factory()->create(), 'web');
    $this->get(route('website.header'))->assertRedirect(route('shop.login'));
    $this->put(route('website.header.update'), ['settings' => websiteHeaderPayload()])->assertRedirect(route('shop.login'));
    expect(BusinessSetting::count())->toBe(0);
});

test('other legacy settings writers cannot bypass migrated keys or the refused script policy', function (string $key, string $routeName) {
    $staff = $routeName === 'business_settings.update.activation'
        ? createStoreAdmin(true)
        : createStoreStaff('seller_commission_configuration', 'shipping_configuration');
    actingAsStoreUser($staff);
    $protected = headerSetting($key, 'unchanged');
    $noise = headerSetting('vendor_commission', '10');
    $payload = $routeName === 'business_settings.vendor_commission.update'
        ? ['types' => ['vendor_commission', $key], 'vendor_commission' => '25', $key => 'forged']
        : ['type' => $key, 'value' => 'forged', $key => 'forged'];
    if ($routeName === 'business_settings.vendor_commission.update') {
        $this->postJson(route($routeName), $payload)->assertForbidden();
    } else {
        $this->postJson(route($routeName), $payload)->assertUnprocessable();
    }
    expect($protected->fresh()->value)->toBe('unchanged')->and($noise->fresh()->value)->toBe('10')
        ->and(BusinessSetting::count())->toBe(2);
})->with(['header_logo', 'home_slider_images', 'header_script', 'footer_script'])
    ->with(['business_settings.vendor_commission.update', 'shipping_configuration.update', 'business_settings.update.activation']);
