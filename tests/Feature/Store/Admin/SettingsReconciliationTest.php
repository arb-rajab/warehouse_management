<?php

use App\Http\Controllers\BusinessSettingsController;
use App\Http\Controllers\Store\Admin\SettingsController;
use App\Http\Requests\Store\Admin\UpdateGeneralSettingsRequest;
use App\Http\Requests\Store\Admin\UpdateWebsiteAppearanceRequest;
use App\Http\Requests\Store\Admin\UpdateWebsiteFooterRequest;
use App\Http\Requests\Store\Admin\UpdateWebsiteHeaderRequest;
use App\Http\Requests\Store\Admin\UpdateWebsiteHomeRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// The generic writer is retired (§20): its URI answers 404 for every key.
// The footer and appearance writers that replaced it refuse every other
// page's key, plain or in the legacy localized `types[][lang]` shape, before
// their first write. `website_name` is the appearance form's own key.
test('no settings writer reachable by the website permissions writes another page\'s settings key', function (string $key, bool $localized) {
    DB::table('business_settings')->insert(['type' => 'website_name', 'value' => 'Original']);
    actingAsStoreUser(createStoreStaff('footer_setup', 'website_appearance', 'general_settings', 'edit_website_page'));
    $this->post(retiredSettingsWriterUri(), [
        'types' => ['website_name', $localized ? ['ar' => $key] : $key],
        'website_name' => 'Changed',
        $key => '1',
    ])->assertNotFound();
    $writers = [];
    if (! in_array($key, UpdateWebsiteFooterRequest::KEYS, true)) {
        $writers[] = [route('website.footer.update'), ['settings' => websiteFooterPayload(['contact_phone' => 'Changed']), 'lang' => 'en']];
    }
    if (! in_array($key, UpdateWebsiteAppearanceRequest::KEYS, true)) {
        $writers[] = [route('website.appearance.update'), ['settings' => websiteAppearancePayload(['website_name' => 'Changed'])]];
    }
    foreach ($writers as [$uri, $payload]) {
        $forged = $localized ? [...$payload, 'types' => [['ar' => $key]], $key => '1'] : array_replace_recursive($payload, ['settings' => [$key => '1']]);
        $this->put($uri, $forged)->assertSessionHasErrors($localized ? ['types', $key] : 'settings');
    }
    $this->assertDatabaseHas('business_settings', ['type' => 'website_name', 'value' => 'Original']);
    expect(DB::table('business_settings')->count())->toBe(1);
})->with(array_values(array_unique(array_merge(array_merge(...array_column(SettingsController::PAGES, 'keys')), UpdateWebsiteHeaderRequest::KEYS, UpdateWebsiteHomeRequest::KEYS, UpdateGeneralSettingsRequest::KEYS, UpdateWebsiteFooterRequest::KEYS, UpdateWebsiteAppearanceRequest::KEYS, ['header_script', 'footer_script', 'google_map', 'google_map_latitude', 'google_map_longtitude', 'MAP_API_KEY']))))->with([false, true]);

// The four permissions that gated the generic writer (#391's gap: any one of
// them wrote footer and appearance keys) now reach only their own page.
test('each former generic writer permission saves only its own website page', function (string $permission) {
    actingAsStoreUser(createStoreStaff($permission));
    DB::table('business_settings')->insert(['type' => 'footer_logo', 'value' => null]);

    $footer = $this->put(route('website.footer.update'), ['settings' => websiteFooterPayload(['contact_phone' => '+31 20 555 0000']), 'lang' => 'en']);
    $appearance = $this->put(route('website.appearance.update'), ['settings' => websiteAppearancePayload(['website_name' => 'Storefront'])]);
    $this->post(retiredSettingsWriterUri(), ['types' => ['website_name'], 'website_name' => 'Generic'])->assertNotFound();

    if ($permission === 'footer_setup') {
        $footer->assertRedirect(route('website.footer', ['lang' => 'en']));
        $this->assertDatabaseHas('business_settings', ['type' => 'contact_phone', 'value' => '+31 20 555 0000', 'lang' => null]);
    } else {
        $footer->assertForbidden();
        $this->assertDatabaseMissing('business_settings', ['type' => 'contact_phone']);
    }
    if ($permission === 'website_appearance') {
        $appearance->assertRedirect(route('website.appearance'));
        $this->assertDatabaseHas('business_settings', ['type' => 'website_name', 'value' => 'Storefront']);
    } else {
        $appearance->assertForbidden();
        $this->assertDatabaseMissing('business_settings', ['type' => 'website_name']);
    }
    $this->assertDatabaseMissing('business_settings', ['value' => 'Generic']);
})->with(['general_settings', 'footer_setup', 'website_appearance', 'edit_website_page']);

test('the retained seller activation form has an exact two-key contract', function () {
    actingAsStoreUser(createStoreAdmin(raizerAdmin: true));
    foreach (['vendor_commission_activation', 'category_wise_commission'] as $key) {
        $this->post(route('business_settings.seller_commission.activation'), ['type' => $key, 'value' => 1])->assertOk()->assertContent('1');
        $this->assertDatabaseHas('business_settings', ['type' => $key, 'value' => '1']);
    }
    $this->postJson(route('business_settings.seller_commission.activation'), ['type' => 'system_default_currency', 'value' => 1])->assertUnprocessable();
    $this->postJson(route('business_settings.seller_commission.activation'), ['type' => 'category_wise_commission', 'value' => 0, 'settings' => ['site_name' => 'Injected']])->assertUnprocessable();
    $this->assertDatabaseHas('business_settings', ['type' => 'category_wise_commission', 'value' => '1']);
    expect(DB::table('business_settings')->count())->toBe(2);
});

test('seller activation compatibility does not grant staff access', function () {
    actingAsStoreUser(createStoreStaff('seller_commission_configuration', 'features_activation'));
    $this->post(route('business_settings.seller_commission.activation'), ['type' => 'category_wise_commission', 'value' => 1])->assertForbidden();
    expect(DB::table('business_settings')->count())->toBe(0);
});

test('the retained script form writes only its two existing settings', function () {
    actingAsStoreUser(createStoreStaff('website_appearance'));
    $payload = ['types' => ['header_script', 'footer_script'], 'header_script' => 'Existing script content', 'footer_script' => null];
    $this->post(route('website.scripts.compatibility.update'), $payload)->assertRedirect(route('website.appearance'));
    $this->assertDatabaseHas('business_settings', ['type' => 'header_script', 'value' => 'Existing script content']);
    $this->postJson(route('website.scripts.compatibility.update'), array_replace($payload, ['types' => [['ar' => 'header_script'], 'footer_script']]))->assertUnprocessable();
    $this->postJson(route('website.scripts.compatibility.update'), $payload + ['site_name' => 'Injected'])->assertUnprocessable();
    $this->post(route('website.scripts.compatibility.update'), array_replace($payload, ['header_script' => '<script>proposed()</script>']))->assertForbidden();
    $this->assertDatabaseHas('business_settings', ['type' => 'header_script', 'value' => 'Existing script content']);
    expect(DB::table('business_settings')->count())->toBe(2);
});

test('general settings permission cannot write scripts through either endpoint', function () {
    actingAsStoreUser(createStoreStaff('general_settings'));
    $this->post(route('website.scripts.compatibility.update'), ['types' => ['header_script', 'footer_script'], 'header_script' => 'script', 'footer_script' => null])->assertForbidden();
    expect(DB::table('business_settings')->count())->toBe(0);
});

// Its last consumers, the footer and appearance Blade forms, are gone; the
// retained Blade views, the admin JavaScript and the application name no
// route, handler or URI of it.
test('the generic settings writer is retired with no route, handler or remaining consumer', function () {
    expect(Route::has('business_settings.update'))->toBeFalse()
        ->and(method_exists(BusinessSettingsController::class, 'update'))->toBeFalse()
        ->and(collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => $route->uri() === ltrim(retiredSettingsWriterUri(), '/'))->all())->toBe([]);
    $consumers = [];
    foreach (sourceFilesUnder(['resources/views', 'app', 'routes']) as $path => $source) {
        if (preg_match("/business_settings\\.update['\"]|business-settings\\/update['\"]/", $source) === 1) {
            $consumers[] = $path;
        }
    }
    // The generated Wayfinder actions and routes are rebuilt from the routes
    // and are not consumers; the hand-written admin JavaScript is.
    foreach (['public/assets/js/aiz-core.js', 'resources/js'] as $path) {
        $files = is_dir(base_path($path)) ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($path), FilesystemIterator::SKIP_DOTS)) : [new SplFileInfo(base_path($path))];
        foreach ($files as $file) {
            $generated = str_contains($file->getPathname(), '/resources/js/actions/') || str_contains($file->getPathname(), '/resources/js/routes/');
            if (! $generated && in_array($file->getExtension(), ['js', 'ts', 'vue'], true) && preg_match("/business_settings\\.update['\"`]|business-settings\\/update['\"`]/", (string) file_get_contents($file->getPathname())) === 1) {
                $consumers[] = $file->getPathname();
            }
        }
    }
    expect($consumers)->toBe([]);
});
