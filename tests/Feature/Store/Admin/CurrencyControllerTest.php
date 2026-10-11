<?php

use App\Models\Currency;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function currencyPayload(array $overrides = []): array
{
    return ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR', 'exchange_rate' => 1.25, ...$overrides];
}

test('currencies render with settings and paginate', function () {
    actingAsStoreUser(createStoreStaff('currency_setup'));
    $lastId = null;
    foreach (range(1, 23) as $number) {
        $lastId = Currency::forceCreate(['name' => "Currency {$number}", 'symbol' => '$', 'code' => "C{$number}", 'exchange_rate' => 1, 'status' => 1])->id;
    }
    DB::table('business_settings')->insert(['type' => 'system_default_currency', 'value' => 1]);

    $this->get('/temp/admin/currency?per_page=10')->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Store/Admin/Setup/Currencies/Index')
            ->has('currencies.data', 10)
            ->has('currencies.data.0', fn (Assert $currency) => $currency
                ->where('id', $lastId)
                ->where('name', 'Currency 23')
                ->where('symbol', '$')
                ->where('code', 'C23')
                ->where('exchange_rate', '1')
                ->where('active', true))
            ->where('currencies.meta.total', 23)
            ->has('activeCurrencies', 23)
            ->where('settings.system_default_currency', 1)
    );
    $this->get('/temp/admin/currency?per_page=7')->assertSessionHasErrors('per_page');
});

test('currencies and their allowlisted settings can be updated', function () {
    actingAsStoreUser(createStoreStaff('currency_setup'));
    $this->post('/temp/admin/currency', currencyPayload(['code' => '']))->assertSessionHasErrors('code');
    $this->post('/temp/admin/currency', currencyPayload())->assertRedirect(route('currency.index'));
    $currency = Currency::query()->where('code', 'EUR')->firstOrFail();

    $this->put("/temp/admin/currency/{$currency->id}", currencyPayload(['name' => 'European euro']))->assertRedirect(route('currency.index'));
    $this->patch("/temp/admin/currency/{$currency->id}/status", ['status' => true])->assertRedirect();
    $this->put('/temp/admin/currency/default', ['system_default_currency' => $currency->id])->assertRedirect(route('currency.index'));
    $this->put('/temp/admin/currency/formats', ['symbol_format' => 4, 'decimal_separator' => 2, 'no_of_decimals' => 3])->assertRedirect(route('currency.index'));

    $this->assertDatabaseHas('currencies', ['id' => $currency->id, 'name' => 'European euro', 'status' => 1]);
    $this->assertDatabaseHas('business_settings', ['type' => 'symbol_format', 'value' => '4']);
    $this->patch("/temp/admin/currency/{$currency->id}/status", ['status' => false])
        ->assertRedirect()
        ->assertSessionHasErrors('status');
    $this->put('/temp/admin/currency/999999', currencyPayload())->assertNotFound();
    $this->patch('/temp/admin/currency/999999/status', ['status' => true])->assertNotFound();
});

test('currency routes refuse callers without permission and writes change nothing', function (string $caller) {
    $currency = Currency::forceCreate([...currencyPayload(), 'status' => 1]);
    actingAsRefusedStoreCaller($caller);

    assertStoreCallerRefused($this->get('/temp/admin/currency'), $caller);
    assertStoreCallerRefused($this->post('/temp/admin/currency', currencyPayload(['code' => 'USD'])), $caller);
    assertStoreCallerRefused($this->put("/temp/admin/currency/{$currency->id}", currencyPayload(['name' => 'Nope'])), $caller);
    assertStoreCallerRefused($this->patch("/temp/admin/currency/{$currency->id}/status", ['status' => false]), $caller);
    assertStoreCallerRefused($this->put('/temp/admin/currency/default', ['system_default_currency' => $currency->id]), $caller);
    assertStoreCallerRefused($this->put('/temp/admin/currency/formats', ['symbol_format' => 2, 'decimal_separator' => 2, 'no_of_decimals' => 3]), $caller);
    $this->assertDatabaseHas('currencies', ['id' => $currency->id, 'name' => 'Euro', 'status' => 1]);
    $this->assertDatabaseMissing('currencies', ['code' => 'USD']);
    $this->assertDatabaseMissing('business_settings', ['type' => 'system_default_currency']);
})->with('refused store admin callers');
