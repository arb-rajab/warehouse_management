<?php

use App\Http\Controllers\BusinessSettingsController;
use App\Http\Controllers\Store\Admin\SellerCommissionController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function sellerCommissionPayload(array $overrides = []): array
{
    return array_replace(['types' => ['vendor_commission', 'minimum_seller_amount_withdraw'], 'vendor_commission' => '12.50', 'minimum_seller_amount_withdraw' => '20'], $overrides);
}

test('seller commission renders before settings exist under strict CSP', function () {
    actingAsStoreUser(createStoreStaff('seller_commission_configuration'));
    $response = $this->get(route('business_settings.vendor_commission'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Sellers/Commission/Index'));
    $this->get(route('business_settings.vendor_commission'), inertiaHeaders())->assertOk()->assertHeader('X-Inertia', 'true');
    expect(scriptSrcOf($response))->toBe("script-src 'self'");
});

test('seller commission returns exactly its four settings and supports Inertia visits', function () {
    actingAsStoreUser(createStoreStaff('seller_commission_configuration'));
    foreach (['vendor_commission' => '12.50', 'minimum_seller_amount_withdraw' => '20', 'vendor_commission_activation' => '1', 'category_wise_commission' => '0', 'noise' => 'private'] as $type => $value) {
        DB::table('business_settings')->insert(compact('type', 'value'));
    }
    $this->get(route('business_settings.vendor_commission'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('settings', fn (Assert $settings) => $settings->where('vendor_commission', '12.50')->where('minimum_seller_amount_withdraw', '20')->where('vendor_commission_activation', true)->where('category_wise_commission', false)));
});

test('commission writes create missing rows accept boundaries and invalidate only business settings', function (string $commission, string $minimum) {
    actingAsStoreUser(createStoreStaff('seller_commission_configuration'));
    Artisan::spy();
    Cache::put('business_settings', collect([['type' => 'vendor_commission', 'value' => 'stale']]));
    Cache::put('wms-sentinel', 'keep');
    Cache::put('verified_sellers_id', [123]);
    $this->post(route('business_settings.vendor_commission.update'), sellerCommissionPayload(['vendor_commission' => $commission, 'minimum_seller_amount_withdraw' => $minimum]))->assertRedirectToRoute('business_settings.vendor_commission');
    $this->assertDatabaseHas('business_settings', ['type' => 'vendor_commission', 'value' => $commission]);
    $this->assertDatabaseHas('business_settings', ['type' => 'minimum_seller_amount_withdraw', 'value' => $minimum]);
    expect(Cache::has('business_settings'))->toBeFalse()->and(get_setting('vendor_commission'))->toBe($commission)
        ->and(Cache::get('wms-sentinel'))->toBe('keep')->and(Cache::get('verified_sellers_id'))->toBe([123]);
    Artisan::shouldNotHaveReceived('call');
})->with([['0', '0'], ['100', '0.001'], ['99.99', '1000000']]);

test('each legacy commission subform still updates only its selected key', function (string $key, string $value) {
    actingAsStoreUser(createStoreStaff('seller_commission_configuration'));
    DB::table('business_settings')->insert(['type' => 'noise', 'value' => 'keep']);
    $this->post(route('business_settings.vendor_commission.update'), ['types' => [$key], $key => $value])->assertRedirectToRoute('business_settings.vendor_commission');
    $this->assertDatabaseHas('business_settings', ['type' => $key, 'value' => $value]);
    $this->assertDatabaseHas('business_settings', ['type' => 'noise', 'value' => 'keep']);
    expect(DB::table('business_settings')->count())->toBe(2);
})->with([['vendor_commission', '42.25'], ['minimum_seller_amount_withdraw', '15']]);

test('commission validation refuses invalid values atomically without invalidating cache', function (array $changes, string $error) {
    actingAsStoreUser(createStoreStaff('seller_commission_configuration'));
    DB::table('business_settings')->insert(['type' => 'vendor_commission', 'value' => '5']);
    $before = DB::table('business_settings')->get()->toJson();
    Cache::put('business_settings', collect([['type' => 'vendor_commission', 'value' => '5']]));
    $this->postJson(route('business_settings.vendor_commission.update'), sellerCommissionPayload($changes))->assertUnprocessable()->assertJsonValidationErrors($error);
    expect(DB::table('business_settings')->get()->toJson())->toBe($before)->and(Cache::has('business_settings'))->toBeTrue();
})->with([
    [['vendor_commission' => '-0.01'], 'vendor_commission'], [['vendor_commission' => '100.01'], 'vendor_commission'],
    [['vendor_commission' => '1.001'], 'vendor_commission'], [['vendor_commission' => 'text'], 'vendor_commission'],
    [['vendor_commission' => []], 'vendor_commission'], [['vendor_commission' => null], 'vendor_commission'],
    [['minimum_seller_amount_withdraw' => '-0.001'], 'minimum_seller_amount_withdraw'], [['minimum_seller_amount_withdraw' => 'NaN'], 'minimum_seller_amount_withdraw'],
    [['minimum_seller_amount_withdraw' => []], 'minimum_seller_amount_withdraw'], [['minimum_seller_amount_withdraw' => null], 'minimum_seller_amount_withdraw'],
    [['types' => ['vendor_commission', 'vendor_commission']], 'types.0'], [['site_name' => 'forged'], 'site_name'],
    [['settings' => ['site_name' => 'forged']], 'settings'],
]);

test('foreign plain and localized commission setting types keep the legacy forbidden contract', function (mixed $types) {
    actingAsStoreUser(createStoreStaff('seller_commission_configuration'));
    DB::table('business_settings')->insert(['type' => 'vendor_commission', 'value' => '5']);
    $before = DB::table('business_settings')->get()->toJson();
    $this->postJson(route('business_settings.vendor_commission.update'), sellerCommissionPayload(['types' => $types, 'header_script' => 'forged']))->assertForbidden();
    expect(DB::table('business_settings')->get()->toJson())->toBe($before);
})->with([['vendor_commission'], [[]], [['vendor_commission', 'header_script']], [[['ar' => 'header_script']]], [['vendor_commission', ['en' => 'vendor_commission']]]]);

test('each seller commission action refuses unauthorized callers with unchanged settings', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    DB::table('business_settings')->insert(['type' => 'vendor_commission', 'value' => '5']);
    $before = DB::table('business_settings')->get()->toJson();
    assertStoreCallerRefused($this->get(route('business_settings.vendor_commission')), $caller);
    assertStoreCallerRefused($this->post(route('business_settings.vendor_commission.update'), sellerCommissionPayload()), $caller);
    expect(DB::table('business_settings')->get()->toJson())->toBe($before);
})->with('refused store admin callers');

test('WMS login and unrelated store permissions grant no seller commission action', function (bool $wms) {
    $wms ? actingAsAdmin() : actingAsStoreUser(createStoreStaff('seller_verification_form_configuration'));
    foreach ([$this->get(route('business_settings.vendor_commission')), $this->post(route('business_settings.vendor_commission.update'), sellerCommissionPayload())] as $response) {
        $wms ? $response->assertRedirectToRoute('shop.login') : $response->assertForbidden();
    }
    expect(DB::table('business_settings')->count())->toBe(0);
})->with([false, true]);

test('commission routes keep their names URIs and gates and the replaced view and root methods are gone', function () {
    expect(route('business_settings.vendor_commission', [], false))->toBe('/temp/admin/vendor_commission')
        ->and(route('business_settings.vendor_commission.update', [], false))->toBe('/temp/admin/vendor_commission_update')
        ->and(SellerCommissionController::middleware()[0]->middleware)->toBe('permission:seller_commission_configuration')
        ->and(view()->exists('shop.backend.sellers.seller_commission.index'))->toBeFalse()
        ->and(method_exists(BusinessSettingsController::class, 'vendor_commission'))->toBeFalse()
        ->and(method_exists(BusinessSettingsController::class, 'vendor_commission_update'))->toBeFalse();
});

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

test('activation compatibility refuses every ordinary caller without touching DB or cache', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    DB::table('business_settings')->insert(['type' => 'category_wise_commission', 'value' => '0']);
    Cache::put('business_settings', collect([['type' => 'category_wise_commission', 'value' => '0']]));
    $before = DB::table('business_settings')->get()->toJson();
    assertStoreCallerRefused($this->post(route('business_settings.seller_commission.activation'), ['type' => 'category_wise_commission', 'value' => 1]), $caller);
    expect(DB::table('business_settings')->get()->toJson())->toBe($before)->and(Cache::has('business_settings'))->toBeTrue();
})->with('refused store admin callers');

test('activation remains restricted to vendor admins with features activation and leaves unrelated caches alone', function () {
    actingAsStoreUser(createStoreAdmin(raizerAdmin: true));
    Cache::put('business_settings', collect());
    Cache::put('wms-sentinel', 'keep');
    $this->post(route('business_settings.seller_commission.activation'), ['type' => 'vendor_commission_activation', 'value' => 0])->assertOk()->assertContent('1');
    expect(Cache::has('business_settings'))->toBeFalse()->and(Cache::get('wms-sentinel'))->toBe('keep');
    $this->assertDatabaseHas('business_settings', ['type' => 'vendor_commission_activation', 'value' => '0']);
    $before = DB::table('business_settings')->get()->toJson();
    $this->postJson(route('business_settings.seller_commission.activation'), ['type' => 'vendor_commission_activation', 'value' => 'bad'])->assertUnprocessable();
    actingAsStoreUser(createStoreAdmin(raizerAdmin: false));
    $this->post(route('business_settings.seller_commission.activation'), ['type' => 'vendor_commission_activation', 'value' => 1])->assertForbidden();
    $vendorWithoutPermission = createStoreUser('admin');
    $vendorWithoutPermission->forceFill(['raizer_admin' => true])->save();
    actingAsStoreUser($vendorWithoutPermission);
    $this->post(route('business_settings.seller_commission.activation'), ['type' => 'vendor_commission_activation', 'value' => 1])->assertForbidden();
    auth('store')->logout();
    auth()->shouldUse('web');
    actingAsAdmin();
    $this->post(route('business_settings.seller_commission.activation'), ['type' => 'vendor_commission_activation', 'value' => 1])->assertRedirectToRoute('shop.login');
    expect(DB::table('business_settings')->get()->toJson())->toBe($before);
});
