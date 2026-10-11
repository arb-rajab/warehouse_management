<?php

use App\Models\State;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function stateCountry(): int
{
    return DB::table('countries')->insertGetId(['code' => 'NL', 'name' => 'Netherlands', 'status' => 1]);
}

test('states render with their country and paginate', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_states'));
    $countryId = stateCountry();
    $firstId = null;
    foreach (range(1, 23) as $number) {
        $id = DB::table('states')->insertGetId(['name' => "State {$number}", 'country_id' => $countryId, 'status' => 1]);
        $firstId ??= $id;
    }

    $this->get("/temp/admin/states?country_id={$countryId}&per_page=10")->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Store/Admin/Shipping/States/Index')
            ->has('states.data', 10)
            ->has('states.data.0', fn (Assert $state) => $state
                ->where('id', $firstId)
                ->where('name', 'State 1')
                ->where('country_id', $countryId)
                ->where('active', true)
                ->has('country', fn (Assert $country) => $country
                    ->where('id', $countryId)
                    ->where('code', 'NL')
                    ->where('name', 'Netherlands')
                    ->where('zone_id', 0)
                    ->where('active', true)))
            ->where('states.meta.total', 23)
            ->where('filters.per_page', 10)
    );
    $this->get('/temp/admin/states?per_page=7')->assertSessionHasErrors('per_page');
});

test('states can be created, edited, updated, toggled and deleted with validation', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_states'));
    $countryId = stateCountry();

    $this->post('/temp/admin/states', ['name' => '', 'country_id' => 999999])->assertSessionHasErrors(['name', 'country_id']);
    $this->post('/temp/admin/states', ['name' => 'North', 'country_id' => $countryId])->assertRedirect(route('states.index'));
    $state = State::query()->where('name', 'North')->firstOrFail();

    $this->get("/temp/admin/states/{$state->id}/edit")->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Store/Admin/Shipping/States/Edit')->where('state.name', 'North')
    );
    $this->put("/temp/admin/states/{$state->id}", ['name' => 'South', 'country_id' => $countryId])->assertRedirect(route('states.index'));
    $this->patch("/temp/admin/states/{$state->id}/status", ['status' => true])->assertRedirect();
    $this->delete("/temp/admin/states/{$state->id}")->assertRedirect(route('states.index'));
    $this->assertDatabaseMissing('states', ['id' => $state->id]);

    $this->get('/temp/admin/states/999999/edit')->assertNotFound();
    $this->put('/temp/admin/states/999999', ['name' => 'X', 'country_id' => $countryId])->assertNotFound();
    $this->delete('/temp/admin/states/999999')->assertNotFound();
});

test('state routes refuse callers without the permission and writes change nothing', function (string $caller) {
    $countryId = stateCountry();
    $state = State::forceCreate(['name' => 'North', 'country_id' => $countryId]);
    actingAsRefusedStoreCaller($caller);

    assertStoreCallerRefused($this->get('/temp/admin/states'), $caller);
    assertStoreCallerRefused($this->get("/temp/admin/states/{$state->id}/edit"), $caller);
    assertStoreCallerRefused($this->post('/temp/admin/states', ['name' => 'Nope', 'country_id' => $countryId]), $caller);
    assertStoreCallerRefused($this->put("/temp/admin/states/{$state->id}", ['name' => 'Nope', 'country_id' => $countryId]), $caller);
    assertStoreCallerRefused($this->patch("/temp/admin/states/{$state->id}/status", ['status' => false]), $caller);
    assertStoreCallerRefused($this->delete("/temp/admin/states/{$state->id}"), $caller);
    $this->assertDatabaseHas('states', ['id' => $state->id, 'name' => 'North']);
    $this->assertDatabaseMissing('states', ['name' => 'Nope']);
})->with('refused store admin callers');
