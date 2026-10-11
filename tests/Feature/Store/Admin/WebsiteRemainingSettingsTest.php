<?php

use App\Http\Requests\Store\Admin\UpdateWebsiteAppearanceRequest;
use App\Http\Requests\Store\Admin\UpdateWebsiteFooterRequest;
use App\Models\BusinessSetting;
use App\Models\Language;
use App\Models\Upload;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

function remainingSettingsPayload(string $page): array
{
    $keys = $page === 'footer' ? UpdateWebsiteFooterRequest::KEYS : UpdateWebsiteAppearanceRequest::KEYS;
    $values = array_fill_keys($keys, '');
    foreach ($keys as $key) {
        if (str_starts_with($key, 'show_') || $key === 'registration_tutorial_button') {
            $values[$key] = false;
        }
    }
    if ($page === 'footer') {
        $values['widget_one_labels'] = ['Help'];
        $values['widget_one_links'] = ['/help'];
        $values['about_us_description'] = '<p>About</p>';
        $values['seller_app_link'] = 'https://example.com/seller';
    } else {
        $values['base_color'] = '#112233';
        $values['base_hov_color'] = '#123';
        $values['website_name'] = 'Store';
    }

    return ['settings' => $values, ...($page === 'footer' ? ['lang' => 'en'] : [])];
}
test('remaining settings render narrow safe props behind their exact gates', function (string $name, string $permission, string $component) {
    BusinessSetting::forceCreate(['type' => 'smtp_pass', 'value' => 'private-secret']);
    BusinessSetting::forceCreate(['type' => 'header_script', 'value' => '<script>custom-code</script>']);
    actingAsStoreUser(createStoreStaff());
    $this->get(route('website.'.$name))->assertForbidden();
    actingAsStoreUser(createStoreStaff($permission));
    $response = $this->get(route('website.'.$name))->assertOk()->assertInertia(fn (Assert $page) => $page->component($component)->has('settings', count($name === 'footer' ? UpdateWebsiteFooterRequest::KEYS : UpdateWebsiteAppearanceRequest::KEYS)));
    $response->assertDontSee('private-secret')->assertDontSee('custom-code');
    if ($name === 'appearance') {
        $response->assertInertia(fn (Assert $page) => $page->where('scripts', null));
    }
})->with([['footer', 'footer_setup', 'Store/Admin/Website/Footer'], ['appearance', 'website_appearance', 'Store/Admin/Website/Appearance']]);
test('remaining settings write only their page allowlists and invalidate only store settings', function (string $name, string $permission) {
    actingAsStoreUser(createStoreStaff($permission));
    BusinessSetting::forceCreate(['type' => 'header_script', 'value' => 'keep-script']);
    BusinessSetting::forceCreate(['type' => 'smtp_pass', 'value' => 'keep-password']);
    Cache::put('business_settings', 'old');
    Cache::put('wms-sentinel', 'keep');
    $payload = remainingSettingsPayload($name);
    $this->put(route('website.'.$name.'.update'), $payload)->assertRedirect();
    expect(get_setting($name === 'footer' ? 'seller_app_link' : 'website_name'))->toBe($name === 'footer' ? 'https://example.com/seller' : 'Store')
        ->and(BusinessSetting::where('type', 'smtp_pass')->value('value'))->toBe('keep-password')->and(BusinessSetting::where('type', 'header_script')->value('value'))->toBe('keep-script')->and(Cache::get('wms-sentinel'))->toBe('keep');
})->with([['footer', 'footer_setup'], ['appearance', 'website_appearance']]);
test('footer translations keep other languages and shared links intact', function () {
    actingAsStoreUser(createStoreStaff('footer_setup'));
    Language::forceCreate(['name' => 'Arabic', 'code' => 'ar']);
    BusinessSetting::forceCreate(['type' => 'about_us_description', 'lang' => 'en', 'value' => 'English']);
    $payload = remainingSettingsPayload('footer');
    $payload['lang'] = 'ar';
    $payload['settings']['about_us_description'] = '<p>عربي</p><script>attack</script>';
    $payload['settings']['widget_one_labels'] = ['مساعدة'];
    $this->put(route('website.footer.update'), $payload)->assertRedirect(route('website.footer', ['lang' => 'ar']));
    expect(get_setting('about_us_description', null, 'ar'))->toBe('<p>عربي</p>')->and(get_setting('about_us_description', null, 'en'))->toBe('English')->and(json_decode(get_setting('widget_one_links'), true))->toBe(['/help']);
    $this->get(route('website.footer', ['lang' => 'ar']))->assertInertia(fn (Assert $page) => $page->where('lang', 'ar')->where('settings.about_us_description', '<p>عربي</p>')->where('settings.widget_one_labels', ['مساعدة']));
    $payload['lang'] = 'xx';
    $this->put(route('website.footer.update'), $payload)->assertSessionHasErrors('lang');
});
test('remaining settings reject unknown keys and mismatched page writes atomically', function (string $name, string $permission, string $key) {
    actingAsStoreUser(createStoreStaff($permission));
    $payload = remainingSettingsPayload($name);
    $payload['settings'][$key] = 'forged';
    $before = BusinessSetting::count();
    $this->put(route('website.'.$name.'.update'), $payload)->assertSessionHasErrors('settings');
    expect(BusinessSetting::count())->toBe($before);
})->with([['footer', 'footer_setup', 'header_script'], ['footer', 'footer_setup', 'website_name'], ['appearance', 'website_appearance', 'seller_app_link'], ['appearance', 'website_appearance', 'smtp_pass'], ['appearance', 'website_appearance', 'footer_script']]);
test('remaining settings reject extra root input and invalid links, images and pairs', function () {
    $staff = createStoreStaff('footer_setup');
    $staff->forceFill(['user_type' => 'accountant'])->save();
    actingAsStoreUser($staff);
    $other = Upload::factory()->create(['user_id' => createStoreUser()->id, 'type' => 'image']);
    $payload = remainingSettingsPayload('footer');
    $payload['settings']['footer_logo'] = $other->id;
    $payload['settings']['payment_method_images'] = '999999';
    $payload['settings']['seller_app_link'] = 'javascript:alert(1)';
    $payload['settings']['widget_one_links'] = [];
    $payload['header_script'] = 'forged';
    $this->put(route('website.footer.update'), $payload)->assertSessionHasErrors(['settings.footer_logo', 'settings.payment_method_images', 'settings.seller_app_link', 'settings.widget_one_links', 'header_script']);
    expect(BusinessSetting::count())->toBe(0);
});
test('appearance validates colours and sanitizes rich content on save and read', function () {
    actingAsStoreUser(createStoreStaff('website_appearance'));
    $payload = remainingSettingsPayload('appearance');
    $payload['settings']['base_color'] = 'red; background:url(x)';
    $this->put(route('website.appearance.update'), $payload)->assertSessionHasErrors('settings.base_color');
    $payload['settings']['base_color'] = '#abcdef';
    $payload['settings']['website_popup_content'] = '<h2>Keep</h2><img src="x" onerror="attack"><script>attack</script>';
    $this->put(route('website.appearance.update'), $payload)->assertRedirect();
    expect(get_setting('website_popup_content'))->toBe('<h2>Keep</h2><img src="x">');
    $this->get(route('website.appearance'))->assertInertia(fn (Assert $page) => $page->where('settings.website_popup_content', '<h2>Keep</h2><img src="x">'));
});
test('remaining settings refuse unauthorized callers without changing rows', function (string $caller, string $name) {
    $before = BusinessSetting::count();
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route('website.'.$name)), $caller);
    assertStoreCallerRefused($this->put(route('website.'.$name.'.update'), remainingSettingsPayload($name)), $caller);
    expect(BusinessSetting::count())->toBe($before);
})->with('refused store admin callers')->with(['footer', 'appearance']);
test('each remaining page permission refuses the other page write', function () {
    actingAsStoreUser(createStoreStaff('footer_setup'));
    $this->put(route('website.appearance.update'), remainingSettingsPayload('appearance'))->assertForbidden();
    actingAsStoreUser(createStoreStaff('website_appearance'));
    $this->put(route('website.footer.update'), remainingSettingsPayload('footer'))->assertForbidden();
    expect(BusinessSetting::count())->toBe(0);
});
test('legacy arbitrary settings writers refuse footer appearance and script keys before writes', function (string $key) {
    actingAsStoreUser(createStoreAdmin());
    $before = BusinessSetting::count();
    $this->post(route('business_settings.update'), ['types' => ['unrelated', $key], 'unrelated' => 'must-not-write', $key => 'forged'])->assertForbidden();
    expect(BusinessSetting::count())->toBe($before);
})->with(['seller_app_link', 'about_us_description', 'site_icon', 'website_popup_content', 'header_script', 'footer_script']);
