<?php

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->refs = createStoreReferenceData();
});

test('the Currencies list can be rendered', function () {
    actingAsStoreUser(createStoreStaff('currency_setup'));
    $this->get('/temp/admin/currency')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Setup/Currencies/Index'));
});

test('the Currencies list exposes exactly the narrow record', function () {
    actingAsStoreUser(createStoreStaff('currency_setup'));
    $this->get('/temp/admin/currency')->assertOk()->assertInertia(fn (Assert $page) => $page->has('currencies.data', 1)->has('currencies.data.0', fn (Assert $row) => $row
        ->where('id', $this->refs['currency'])
        ->where('name', 'Euro')
        ->where('symbol', '€')
        ->where('code', 'EUR')
        ->where('exchange_rate', '1.23')
        ->where('status', true)
    ));
});

test('the Currencies list pages without overlaps and validates page size', function () {
    actingAsStoreUser(createStoreStaff('currency_setup'));
    $row = (array) DB::table('currencies')->where('id', $this->refs['currency'])->first();
    unset($row['id']);
    foreach (range(1, 22) as $number) {
        $row['name'] = 'Record '.$number;
        DB::table('currencies')->insert($row);
    } assertPagesWithoutOverlap(fn (int $page) => $this->get('/temp/admin/currency?page='.$page), fn ($response) => collect($response->inertiaProps('currencies.data'))->pluck('id')->all(), 23, 20);
    $this->get('/temp/admin/currency?per_page=10')->assertOk()->assertInertia(fn (Assert $page) => $page->has('currencies.data', 10)->where('currencies.meta.per_page', 10));
    $this->getJson('/temp/admin/currency?per_page=13')->assertUnprocessable()->assertJsonValidationErrors('per_page');
});

test('the Currencies list refuses callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get('/temp/admin/currency'), $caller);
})->with('refused store admin callers');

test('the Currencies name filter excludes unrelated data and survives pagination', function () {
    actingAsStoreUser(createStoreStaff('currency_setup'));
    $row = (array) DB::table('currencies')->where('id', $this->refs['currency'])->first();
    unset($row['id']);
    $row['name'] = 'Unrelated';
    DB::table('currencies')->insert($row);
    $this->get('/temp/admin/currency?search='.urlencode('Euro'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('currencies.data', 1)->where('currencies.data.0.id', $this->refs['currency'])->where('filters.search', 'Euro'));
});

test('the Currencies create screen can be rendered', function () {
    actingAsStoreUser(createStoreStaff('currency_setup'));
    $this->get('/temp/admin/currency/create')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Setup/Currencies/Index')->where('dialog', 'create'));
});

test('the Currencies create screen refuses callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get('/temp/admin/currency/create'), $caller);
})->with('refused store admin callers');

test('the Currencies edit screen can be rendered', function () {
    actingAsStoreUser(createStoreStaff('currency_setup'));
    $this->get('/temp/admin/currency/'.$this->refs['currency'].'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Setup/Currencies/Index'));
});

test('the Currencies edit screen refuses callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get('/temp/admin/currency/'.$this->refs['currency'].'/edit'), $caller);
})->with('refused store admin callers');

test('the Currencies edit screen returns 404 for a missing id', function () {
    actingAsStoreUser(createStoreStaff('currency_setup'));
    $this->get('/temp/admin/currency/999999/edit')->assertNotFound();
});

test('the Currencies store saves validated values', function () {
    actingAsStoreUser(createStoreStaff('currency_setup'));
    $this->post('/temp/admin/currency/store', ['name' => 'Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => '1.00001'])->assertRedirect(route('currency.index'));
    $this->assertDatabaseHas('currencies', ['name' => 'Dollar']);
});

test('the Currencies store validates fields before writing', function () {
    actingAsStoreUser(createStoreStaff('currency_setup'));
    $this->postJson('/temp/admin/currency/store', [])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->assertDatabaseCount('currencies', 1);
});

test('the Currencies store refuses writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post('/temp/admin/currency/store', ['name' => 'Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => '1.00001']), $caller);
    $this->assertDatabaseCount('currencies', 1);
})->with('refused store admin callers');

test('the Currencies update saves validated values', function () {
    actingAsStoreUser(createStoreStaff('currency_setup'));
    $this->put('/temp/admin/your-currency/'.$this->refs['currency'], ['name' => 'Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => '1.00001'])->assertRedirect(route('currency.index'));
    $this->assertDatabaseHas('currencies', ['id' => $this->refs['currency'], 'name' => 'Dollar']);
});

test('the Currencies update validates fields without changing data', function () {
    actingAsStoreUser(createStoreStaff('currency_setup'));
    $this->putJson('/temp/admin/your-currency/'.$this->refs['currency'], [])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->assertDatabaseHas('currencies', ['id' => $this->refs['currency'], 'name' => 'Euro']);
});

test('the Currencies update refuses writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->put('/temp/admin/your-currency/'.$this->refs['currency'], ['name' => 'Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => '1.00001']), $caller);
    $this->assertDatabaseHas('currencies', ['id' => $this->refs['currency'], 'name' => 'Euro']);
})->with('refused store admin callers');

test('the Currencies update returns 404 for a missing id', function () {
    actingAsStoreUser(createStoreStaff('currency_setup'));
    $this->put('/temp/admin/your-currency/999999', ['name' => 'Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => '1.00001'])->assertNotFound();
});

test('the Currencies status saves the posted value and keeps pagination', function () {
    actingAsStoreUser(createStoreStaff('currency_setup'));
    $this->post('/temp/admin/currency/'.$this->refs['currency'].'/status'.'?page=2', ['status' => 0])->assertRedirect(route('currency.index', ['page' => 2]));
    $this->assertDatabaseHas('currencies', ['id' => $this->refs['currency'], 'status' => 0]);
});

test('the Currencies status validates booleans without writing', function () {
    actingAsStoreUser(createStoreStaff('currency_setup'));
    $this->postJson('/temp/admin/currency/'.$this->refs['currency'].'/status', ['status' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->assertDatabaseHas('currencies', ['id' => $this->refs['currency'], 'status' => 1]);
});

test('the Currencies status refuses writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post('/temp/admin/currency/'.$this->refs['currency'].'/status', ['status' => 0]), $caller);
    $this->assertDatabaseHas('currencies', ['id' => $this->refs['currency'], 'status' => 1]);
})->with('refused store admin callers');

test('the Currencies status returns 404 for a missing id', function () {
    actingAsStoreUser(createStoreStaff('currency_setup'));
    $this->post('/temp/admin/currency/999999/status', ['status' => 0])->assertNotFound();
});

test('the Currencies routes do not accept a WMS identity', function () {
    actingAsAdmin();
    $this->get('/temp/admin/currency')->assertRedirect(route('shop.login'));
    $this->post('/temp/admin/currency/store', ['name' => 'Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => '1.00001'])->assertRedirect(route('shop.login'));
    $this->assertDatabaseCount('currencies', 1);
});

test('the default currency cannot be disabled and positive exchange rates are required', function () {
    actingAsStoreUser(createStoreStaff('currency_setup'));
    DB::table('business_settings')->insert(['type' => 'system_default_currency', 'value' => $this->refs['currency']]);
    $this->post('/temp/admin/currency/'.$this->refs['currency'].'/status', ['status' => 0])->assertForbidden();
    $this->assertDatabaseHas('currencies', ['id' => $this->refs['currency'], 'status' => 1]);
    $this->putJson('/temp/admin/your-currency/'.$this->refs['currency'], ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR', 'exchange_rate' => 0])->assertUnprocessable()->assertJsonValidationErrors('exchange_rate');
    $this->assertDatabaseHas('currencies', ['id' => $this->refs['currency'], 'exchange_rate' => 1.23]);
});
test('currency settings save independently with active-currency validation and specific cache invalidation', function () {
    actingAsStoreUser(createStoreStaff('currency_setup'));
    Cache::put('business_settings', collect());
    Cache::put('system_default_currency', []);
    Cache::put('wms-sentinel', 'retained');
    $this->post('/temp/admin/currency/settings', ['system_default_currency' => $this->refs['currency'], 'header_script' => 'Unrelated'])->assertRedirect(route('currency.index'));
    $this->assertDatabaseHas('business_settings', ['type' => 'system_default_currency', 'value' => (string) $this->refs['currency']]);
    $this->assertDatabaseMissing('business_settings', ['type' => 'header_script']);
    expect(Cache::get('wms-sentinel'))->toBe('retained')->and(Cache::has('business_settings'))->toBeFalse()->and(Cache::has('system_default_currency'))->toBeFalse();
    $this->post('/temp/admin/currency/settings', ['symbol_format' => 4, 'decimal_separator' => 2, 'no_of_decimals' => 0])->assertRedirect(route('currency.index'));
    $this->assertDatabaseHas('business_settings', ['type' => 'symbol_format', 'value' => '4']);
    $this->assertDatabaseHas('business_settings', ['type' => 'decimal_separator', 'value' => '2']);
    $this->assertDatabaseHas('business_settings', ['type' => 'no_of_decimals', 'value' => '0']);
    $inactive = DB::table('currencies')->insertGetId(['name' => 'Inactive', 'symbol' => 'X', 'code' => 'XX', 'exchange_rate' => 1, 'status' => 0]);
    $this->postJson('/temp/admin/currency/settings', ['system_default_currency' => $inactive])->assertUnprocessable()->assertJsonValidationErrors('system_default_currency');
    $this->postJson('/temp/admin/currency/settings', ['symbol_format' => 5, 'decimal_separator' => 3, 'no_of_decimals' => 4])->assertUnprocessable()->assertJsonValidationErrors(['symbol_format', 'decimal_separator', 'no_of_decimals']);
    $this->assertDatabaseHas('business_settings', ['type' => 'system_default_currency', 'value' => (string) $this->refs['currency']]);
});
test('currency configuration refuses writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post('/temp/admin/currency/settings', ['system_default_currency' => $this->refs['currency']]), $caller);
    $this->assertDatabaseMissing('business_settings', ['type' => 'system_default_currency']);
})->with('refused store admin callers');
test('updating a currency invalidates the cached public default without clearing other cache', function () {
    actingAsStoreUser(createStoreStaff('currency_setup'));
    Cache::put('system_default_currency', []);
    Cache::put('wms-sentinel', 'retained');
    $this->put('/temp/admin/your-currency/'.$this->refs['currency'], ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR', 'exchange_rate' => '1.25'])->assertRedirect();
    expect(Cache::has('system_default_currency'))->toBeFalse()->and(Cache::get('wms-sentinel'))->toBe('retained');
});
test('storefront currency switching remains a public server action', function () {
    $this->post(route('currency.change'), ['currency_code' => 'EUR'])->assertSuccessful()->assertSessionHas('currency_code', 'EUR')->assertSessionHas('currency_symbol', '€')->assertSessionHas('currency_exchange_rate', 1.23);
});

test('an inactive default currency can be enabled again', function () {
    actingAsStoreUser(createStoreStaff('currency_setup'));
    DB::table('business_settings')->insert(['type' => 'system_default_currency', 'value' => (string) $this->refs['currency']]);
    DB::table('currencies')->where('id', $this->refs['currency'])->update(['status' => 0]);
    $this->post(route('currency.update_status', $this->refs['currency']), ['status' => true])->assertRedirectToRoute('currency.index');
    $this->assertDatabaseHas('currencies', ['id' => $this->refs['currency'], 'status' => 1]);
});
