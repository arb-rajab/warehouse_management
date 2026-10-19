<?php

use App\Http\Controllers\BusinessSettingsController;
use App\Http\Controllers\Store\Admin\SellerVerificationFormController;
use App\Models\Shop;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function sellerVerificationFields(): array
{
    return [
        ['type' => 'select', 'label' => 'Country', 'options' => ['NL', 'سوريا']],
        ['type' => 'text', 'label' => 'Company'],
        ['type' => 'multi_select', 'label' => 'Markets', 'options' => ['Retail', 'Wholesale']],
        ['type' => 'radio', 'label' => 'Registered', 'options' => ['Yes', 'No']],
        ['type' => 'file', 'label' => 'Licence'],
    ];
}

function sellerVerificationStoredJson(): string
{
    return '[{"type":"select","label":"Country","options":"[\\"NL\\",\\"\\\\u0633\\\\u0648\\\\u0631\\\\u064a\\\\u0627\\"]"},{"type":"text","label":"Company"},{"type":"multi_select","label":"Markets","options":"[\\"Retail\\",\\"Wholesale\\"]"},{"type":"radio","label":"Registered","options":"[\\"Yes\\",\\"No\\"]"},{"type":"file","label":"Licence"}]';
}

test('verification form renders an empty builder when its setting row is missing', function () {
    actingAsStoreUser(createStoreStaff('seller_verification_form_configuration'));
    $response = $this->get(route('seller_verification_form.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Sellers/VerificationForm/Index')->where('fields', []));
    $this->get(route('seller_verification_form.index'), inertiaHeaders())->assertOk()->assertHeader('X-Inertia', 'true');
    expect(scriptSrcOf($response))->toBe("script-src 'self'")->and(DB::table('business_settings')->count())->toBe(0);
});

test('verification form creates a missing setting row with byte compatible ordered JSON and round trips its complete payload', function () {
    actingAsStoreUser(createStoreStaff('seller_verification_form_configuration'));
    Artisan::spy();
    Cache::put('business_settings', collect());
    Cache::put('wms-sentinel', 'keep');
    $this->post(route('seller_verification_form.update'), ['fields' => sellerVerificationFields()])->assertRedirectToRoute('seller_verification_form.index');
    expect(DB::table('business_settings')->where('type', 'verification_form')->value('value'))->toBe(sellerVerificationStoredJson())
        ->and(Cache::has('business_settings'))->toBeFalse()->and(Cache::get('wms-sentinel'))->toBe('keep');
    Artisan::shouldNotHaveReceived('call');
    $this->get(route('seller_verification_form.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('fields', 5)
            ->has('fields.0', fn (Assert $field) => $field->where('type', 'select')->where('label', 'Country')->where('options', ['NL', 'سوريا']))
            ->has('fields.1', fn (Assert $field) => $field->where('type', 'text')->where('label', 'Company'))
            ->has('fields.2', fn (Assert $field) => $field->where('type', 'multi_select')->where('label', 'Markets')->where('options', ['Retail', 'Wholesale']))
            ->has('fields.3', fn (Assert $field) => $field->where('type', 'radio')->where('label', 'Registered')->where('options', ['Yes', 'No']))
            ->has('fields.4', fn (Assert $field) => $field->where('type', 'file')->where('label', 'Licence')));
});

test('an empty field list deliberately saves an empty form for new and existing settings', function (bool $exists) {
    actingAsStoreUser(createStoreStaff('seller_verification_form_configuration'));
    if ($exists) {
        DB::table('business_settings')->insert(['type' => 'verification_form', 'value' => sellerVerificationStoredJson()]);
    }
    $this->post(route('seller_verification_form.update'), ['fields' => []])->assertRedirectToRoute('seller_verification_form.index');
    $this->assertDatabaseHas('business_settings', ['type' => 'verification_form', 'value' => '[]']);
    expect(DB::table('business_settings')->count())->toBe(1);
    $this->get(route('seller_verification_form.index'))->assertInertia(fn (Assert $page) => $page->where('fields', []));
})->with([false, true]);

test('seller verification screens still read nested options JSON and store answers in field order', function () {
    actingAsStoreUser(createStoreStaff('seller_verification_form_configuration'));
    $this->post(route('seller_verification_form.update'), ['fields' => sellerVerificationFields()])->assertRedirect();
    $seller = createStoreUser('seller');
    $shop = Shop::forceCreate(['user_id' => $seller->id, 'name' => 'Round trip shop', 'slug' => 'round-trip-shop']);
    actingAsStoreUser($seller);
    $this->get(route('seller.shop.verify'))->assertOk()->assertSee('Country')->assertSee('Company')->assertSee('Markets')->assertSee('Registered')->assertSee('Licence')->assertSee('سوريا');
    $this->post(route('seller.shop.verify.store'), ['element_0' => 'NL', 'element_1' => 'Acme', 'element_2' => ['Retail', 'Wholesale'], 'element_3' => 'Yes'])->assertRedirectToRoute('seller.dashboard');
    expect(json_decode($shop->fresh()->verification_info, true))->toBe([
        ['type' => 'select', 'label' => 'Country', 'value' => 'NL'], ['type' => 'text', 'label' => 'Company', 'value' => 'Acme'],
        ['type' => 'multi_select', 'label' => 'Markets', 'value' => '["Retail","Wholesale"]'], ['type' => 'select', 'label' => 'Registered', 'value' => 'Yes'],
        ['type' => 'file', 'label' => 'Licence', 'value' => null],
    ]);
    $this->getJson('/temp/api/v2/business-settings')->assertOk()->assertJsonFragment(['type' => 'verification_form', 'value' => json_decode(sellerVerificationStoredJson(), true)]);
});

test('verification validation refuses bad types labels options and foreign keys without changing DB or cache', function (array $payload, string $error) {
    actingAsStoreUser(createStoreStaff('seller_verification_form_configuration'));
    DB::table('business_settings')->insert(['type' => 'verification_form', 'value' => '[]']);
    $before = DB::table('business_settings')->get()->toJson();
    Cache::put('business_settings', collect());
    $this->postJson(route('seller_verification_form.update'), $payload)->assertUnprocessable()->assertJsonValidationErrors($error);
    expect(DB::table('business_settings')->get()->toJson())->toBe($before)->and(Cache::has('business_settings'))->toBeTrue();
})->with([
    [[], 'fields'], [['fields' => null], 'fields'], [['fields' => 'text'], 'fields'], [['fields' => ['bad' => ['type' => 'text', 'label' => 'Name']]], 'fields'],
    [['fields' => [['type' => 'email', 'label' => 'Name']]], 'fields.0.type'], [['fields' => [['type' => [], 'label' => 'Name']]], 'fields.0.type'],
    [['fields' => [['type' => 'text', 'label' => '']]], 'fields.0.label'], [['fields' => [['type' => 'text', 'label' => str_repeat('x', 256)]]], 'fields.0.label'],
    [['fields' => [['type' => 'text', 'label' => ['Name']]]], 'fields.0.label'],
    [['fields' => [['type' => 'select', 'label' => 'Name']]], 'fields.0.options'], [['fields' => [['type' => 'multi_select', 'label' => 'Name', 'options' => []]]], 'fields.0.options'],
    [['fields' => [['type' => 'radio', 'label' => 'Name', 'options' => 'one']]], 'fields.0.options'],
    [['fields' => [['type' => 'select', 'label' => 'Name', 'options' => ['']]]], 'fields.0.options.0'],
    [['fields' => [['type' => 'select', 'label' => 'Name', 'options' => [str_repeat('x', 256)]]]], 'fields.0.options.0'],
    [['fields' => [['type' => 'select', 'label' => 'Name', 'options' => [['nested']]]]], 'fields.0.options.0'],
    [['fields' => [['type' => 'select', 'label' => 'Name', 'options' => ['key' => 'option']]]], 'fields.0.options'],
    [['fields' => [['type' => 'text', 'label' => 'Name', 'options' => ['stale']]]], 'fields.0.options'],
    [['fields' => [['type' => 'text', 'label' => 'Name', 'foreign' => 'bad']]], 'fields.0'],
    [['fields' => [], 'settings' => ['site_name' => 'forged']], 'settings'], [['fields' => [], 'types' => [['ar' => 'site_name']]], 'types'],
    [['fields' => array_fill(0, 101, ['type' => 'text', 'label' => 'Name'])], 'fields'],
    [['fields' => [['type' => 'select', 'label' => 'Name', 'options' => array_fill(0, 101, 'one')]]], 'fields.0.options'],
]);

test('verification accepts bounded label and option strings', function () {
    actingAsStoreUser(createStoreStaff('seller_verification_form_configuration'));
    $this->post(route('seller_verification_form.update'), ['fields' => [['type' => 'select', 'label' => str_repeat('x', 255), 'options' => [str_repeat('y', 255)]]]])->assertSessionHasNoErrors()->assertRedirectToRoute('seller_verification_form.index');
});

test('each verification configuration action refuses unauthorized callers without writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    DB::table('business_settings')->insert(['type' => 'verification_form', 'value' => '[]']);
    $before = DB::table('business_settings')->get()->toJson();
    assertStoreCallerRefused($this->get(route('seller_verification_form.index')), $caller);
    assertStoreCallerRefused($this->post(route('seller_verification_form.update'), ['fields' => sellerVerificationFields()]), $caller);
    expect(DB::table('business_settings')->get()->toJson())->toBe($before);
})->with('refused store admin callers');

test('WMS and commission-only staff have no verification configuration access', function (bool $wms) {
    $wms ? actingAsAdmin() : actingAsStoreUser(createStoreStaff('seller_commission_configuration'));
    foreach ([$this->get(route('seller_verification_form.index')), $this->post(route('seller_verification_form.update'), ['fields' => []])] as $response) {
        $wms ? $response->assertRedirectToRoute('shop.login') : $response->assertForbidden();
    }
    expect(DB::table('business_settings')->count())->toBe(0);
})->with([false, true]);

test('verification routes keep their names URIs and gates and the old view and root methods are gone', function () {
    expect(route('seller_verification_form.index', [], false))->toBe('/temp/admin/verification/form')
        ->and(route('seller_verification_form.update', [], false))->toBe('/temp/admin/verification/form')
        ->and(SellerVerificationFormController::middleware()[0]->middleware)->toBe('permission:seller_verification_form_configuration')
        ->and(view()->exists('shop.backend.sellers.seller_verification_form.index'))->toBeFalse()
        ->and(method_exists(BusinessSettingsController::class, 'seller_verification_form'))->toBeFalse()
        ->and(method_exists(BusinessSettingsController::class, 'seller_verification_form_update'))->toBeFalse();
});
