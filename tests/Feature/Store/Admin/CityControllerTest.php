<?php

use App\Models\City;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function cityState(): int
{
    $countryId = DB::table('countries')->insertGetId(['code' => 'NL', 'name' => 'Netherlands', 'status' => 1]);

    return DB::table('states')->insertGetId(['name' => 'North', 'country_id' => $countryId, 'status' => 1]);
}

test('cities render translated narrow records and paginate', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_cities'));
    $stateId = cityState();
    $firstId = null;
    foreach (range(1, 23) as $number) {
        $id = DB::table('cities')->insertGetId(['name' => "City {$number}", 'state_id' => $stateId, 'cost' => 2.5, 'status' => 1]);
        $firstId ??= $id;
    }

    $this->get('/temp/admin/cities?per_page=10')->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Store/Admin/Shipping/Cities/Index')
            ->has('cities.data', 10)
            ->has('cities.data.0', fn (Assert $city) => $city
                ->where('id', $firstId)
                ->where('name', 'City 1')
                ->where('state_id', $stateId)
                ->where('cost', '2.5')
                ->where('active', true)
                ->has('state', fn (Assert $state) => $state
                    ->where('id', $stateId)
                    ->where('name', 'North')
                    ->where('country_id', 1)
                    ->where('active', true)))
            ->where('cities.meta.total', 23)
    );
    $this->get('/temp/admin/cities?per_page=7')->assertSessionHasErrors('per_page');
});

test('cities can be created, translated, toggled and deleted', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_cities'));
    $stateId = cityState();
    DB::table('languages')->insert(['name' => 'English', 'code' => 'en']);

    $this->post('/temp/admin/cities', ['name' => '', 'state_id' => 999999, 'cost' => -1])->assertSessionHasErrors(['name', 'state_id', 'cost']);
    $this->post('/temp/admin/cities', ['name' => 'Utrecht', 'state_id' => $stateId, 'cost' => 3])->assertRedirect(route('cities.index'));
    $city = City::query()->where('name', 'Utrecht')->firstOrFail();

    $this->get("/temp/admin/cities/{$city->id}/edit?lang=en")->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Store/Admin/Shipping/Cities/Edit')->where('name', 'Utrecht')
    );
    $this->put("/temp/admin/cities/{$city->id}", ['name' => 'Nieuwegein', 'state_id' => $stateId, 'cost' => 4, 'lang' => 'en'])->assertRedirect(route('cities.index'));
    $this->assertDatabaseHas('city_translations', ['city_id' => $city->id, 'lang' => 'en', 'name' => 'Nieuwegein']);
    $this->patch("/temp/admin/cities/{$city->id}/status", ['status' => false])->assertRedirect();
    $this->delete("/temp/admin/cities/{$city->id}")->assertRedirect(route('cities.index'));
    $this->assertDatabaseMissing('cities', ['id' => $city->id]);

    $this->get('/temp/admin/cities/999999/edit')->assertNotFound();
    $this->put('/temp/admin/cities/999999', ['name' => 'X', 'state_id' => $stateId, 'cost' => 1, 'lang' => 'en'])->assertNotFound();
    $this->delete('/temp/admin/cities/999999')->assertNotFound();
});

test('city routes refuse callers without permission and writes change nothing', function (string $caller) {
    $stateId = cityState();
    $city = City::forceCreate(['name' => 'Utrecht', 'state_id' => $stateId, 'cost' => 0]);
    actingAsRefusedStoreCaller($caller);

    $payload = ['name' => 'Nope', 'state_id' => $stateId, 'cost' => 9, 'lang' => 'en'];

    assertStoreCallerRefused($this->get('/temp/admin/cities'), $caller);
    assertStoreCallerRefused($this->get("/temp/admin/cities/{$city->id}/edit"), $caller);
    assertStoreCallerRefused($this->post('/temp/admin/cities', $payload), $caller);
    assertStoreCallerRefused($this->put("/temp/admin/cities/{$city->id}", $payload), $caller);
    assertStoreCallerRefused($this->patch("/temp/admin/cities/{$city->id}/status", ['status' => false]), $caller);
    assertStoreCallerRefused($this->delete("/temp/admin/cities/{$city->id}"), $caller);
    $this->assertDatabaseHas('cities', ['id' => $city->id, 'name' => 'Utrecht', 'cost' => 0]);
    $this->assertDatabaseMissing('cities', ['name' => 'Nope']);
})->with('refused store admin callers');
