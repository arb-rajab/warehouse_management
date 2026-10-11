<?php

use App\Models\Zone;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('zones render and paginate without exposing the broken legacy status toggle', function () {
    actingAsStoreUser(createStoreStaff('manage_zones'));
    $lastId = null;
    foreach (range(1, 23) as $number) {
        $lastId = Zone::forceCreate(['name' => "Zone {$number}", 'status' => true])->id;
    }

    $this->get('/temp/admin/zones?per_page=10')->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Store/Admin/Shipping/Zones/Index')
            ->has('zones.data', 10)
            ->has('zones.data.0', fn (Assert $zone) => $zone
                ->where('id', $lastId)
                ->where('name', 'Zone 23'))
            ->where('zones.meta.total', 23)
    );
    $this->get('/temp/admin/zones?per_page=7')->assertSessionHasErrors('per_page');
});

test('zones assign countries transactionally and support edit update and delete', function () {
    actingAsStoreUser(createStoreStaff('manage_zones'));
    $countryId = DB::table('countries')->insertGetId(['code' => 'NL', 'name' => 'Netherlands', 'status' => 1]);

    $this->get('/temp/admin/zones/create')->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Store/Admin/Shipping/Zones/Create')->where('countries.0.id', $countryId)
    );
    $this->post('/temp/admin/zones', ['name' => '', 'country_id' => []])->assertSessionHasErrors(['name', 'country_id']);
    $this->post('/temp/admin/zones', ['name' => 'Europe', 'country_id' => [$countryId]])->assertRedirect(route('zones.index'));
    $zone = Zone::query()->where('name', 'Europe')->firstOrFail();
    $this->assertDatabaseHas('countries', ['id' => $countryId, 'zone_id' => $zone->id]);

    $this->get("/temp/admin/zones/{$zone->id}/edit")->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Store/Admin/Shipping/Zones/Edit')->where('countryIds', [$countryId])
    );
    $this->put("/temp/admin/zones/{$zone->id}", ['name' => 'EU', 'country_id' => [$countryId]])->assertRedirect(route('zones.index'));
    $this->delete("/temp/admin/zones/{$zone->id}")->assertRedirect(route('zones.index'));
    $this->assertDatabaseHas('countries', ['id' => $countryId, 'zone_id' => 0]);

    $this->get('/temp/admin/zones/999999/edit')->assertNotFound();
    $this->put('/temp/admin/zones/999999', ['name' => 'X', 'country_id' => [$countryId]])->assertNotFound();
    $this->delete('/temp/admin/zones/999999')->assertNotFound();
});

test('zone routes refuse callers without permission and writes change nothing', function (string $caller) {
    $zone = Zone::forceCreate(['name' => 'Europe', 'status' => true]);
    actingAsRefusedStoreCaller($caller);

    assertStoreCallerRefused($this->get('/temp/admin/zones'), $caller);
    assertStoreCallerRefused($this->get('/temp/admin/zones/create'), $caller);
    assertStoreCallerRefused($this->get("/temp/admin/zones/{$zone->id}/edit"), $caller);
    assertStoreCallerRefused($this->post('/temp/admin/zones', ['name' => 'Nope', 'country_id' => [1]]), $caller);
    assertStoreCallerRefused($this->put("/temp/admin/zones/{$zone->id}", ['name' => 'Nope', 'country_id' => [1]]), $caller);
    assertStoreCallerRefused($this->delete("/temp/admin/zones/{$zone->id}"), $caller);
    $this->assertDatabaseHas('zones', ['id' => $zone->id, 'name' => 'Europe']);
    $this->assertDatabaseMissing('zones', ['name' => 'Nope']);
})->with('refused store admin callers');
