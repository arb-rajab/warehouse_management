<?php

use App\Models\BusinessSetting;
use Inertia\Testing\AssertableInertia as Assert;

test('only the script permission exposes and writes script settings', function () {
    BusinessSetting::forceCreate(['type' => 'header_script', 'value' => '<script>original</script>']);
    BusinessSetting::forceCreate(['type' => 'smtp_pass', 'value' => 'hidden-password']);
    actingAsStoreUser(createStoreStaff('website_appearance'));
    $this->get(route('website.scripts'))->assertForbidden();
    $this->put(route('website.scripts.update'), ['settings' => ['header_script' => 'attack', 'footer_script' => '']])->assertForbidden();
    $this->get(route('website.appearance'))->assertInertia(fn (Assert $page) => $page->where('scripts', null));
    actingAsStoreUser(createStoreStaff('manage_website_scripts'));
    $this->get(route('website.scripts'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Website/Scripts')->where('settings', ['header_script' => '<script>original</script>', 'footer_script' => '']));
    $this->put(route('website.scripts.update'), ['settings' => ['header_script' => '<script>approved-code</script>', 'footer_script' => '<script>footer-code</script>']])->assertRedirect(route('website.scripts'));
    expect(get_setting('header_script'))->toBe('<script>approved-code</script>')->and(get_setting('smtp_pass'))->toBe('hidden-password');
});
test('script endpoint rejects every extra key atomically', function (string $key) {
    actingAsStoreUser(createStoreAdmin());
    $this->put(route('website.scripts.update'), ['settings' => ['header_script' => 'must-not-write', 'footer_script' => '', $key => 'forged']])->assertSessionHasErrors('settings');
    expect(BusinessSetting::count())->toBe(0);
})->with(['website_name', 'smtp_pass', 'seller_app_link']);
test('script endpoint rejects extra root input and missing script fields', function () {
    actingAsStoreUser(createStoreAdmin());
    $this->put(route('website.scripts.update'), ['settings' => ['header_script' => 'x'], 'types' => ['header_script']])->assertSessionHasErrors(['settings.footer_script', 'types']);
    expect(BusinessSetting::count())->toBe(0);
});
test('scripts refuse unauthorized callers without writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route('website.scripts')), $caller);
    assertStoreCallerRefused($this->put(route('website.scripts.update'), ['settings' => ['header_script' => 'forged', 'footer_script' => '']]), $caller);
    expect(BusinessSetting::count())->toBe(0);
})->with('refused store admin callers');
