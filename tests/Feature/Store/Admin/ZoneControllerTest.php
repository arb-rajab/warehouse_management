<?php

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->refs = createStoreReferenceData();
});

test('the Zones list can be rendered', function () {
    actingAsStoreUser(createStoreStaff('manage_zones'));
    $this->get('/temp/admin/zones')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Setup/Zones/Index'));
});

test('the Zones list exposes exactly the narrow record', function () {
    actingAsStoreUser(createStoreStaff('manage_zones'));
    $this->get('/temp/admin/zones')->assertOk()->assertInertia(fn (Assert $page) => $page->has('zones.data', 1)->has('zones.data.0', fn (Assert $row) => $row
        ->where('id', $this->refs['zone'])
        ->where('name', 'Europe')
        ->where('status', true)
    ));
});

test('the Zones list pages without overlaps and validates page size', function () {
    actingAsStoreUser(createStoreStaff('manage_zones'));
    $row = (array) DB::table('zones')->where('id', $this->refs['zone'])->first();
    unset($row['id']);
    foreach (range(1, 22) as $number) {
        $row['name'] = 'Record '.$number;
        DB::table('zones')->insert($row);
    } assertPagesWithoutOverlap(fn (int $page) => $this->get('/temp/admin/zones?page='.$page), fn ($response) => collect($response->inertiaProps('zones.data'))->pluck('id')->all(), 23, 20);
    $this->get('/temp/admin/zones?per_page=10')->assertOk()->assertInertia(fn (Assert $page) => $page->has('zones.data', 10)->where('zones.meta.per_page', 10));
    $this->getJson('/temp/admin/zones?per_page=13')->assertUnprocessable()->assertJsonValidationErrors('per_page');
});

test('the Zones list refuses callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get('/temp/admin/zones'), $caller);
})->with('refused store admin callers');

test('the Zones create screen can be rendered', function () {
    actingAsStoreUser(createStoreStaff('manage_zones'));
    $this->get('/temp/admin/zones/create')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Setup/Zones/Create'));
});

test('the Zones create screen refuses callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get('/temp/admin/zones/create'), $caller);
})->with('refused store admin callers');

test('the Zones edit screen can be rendered', function () {
    actingAsStoreUser(createStoreStaff('manage_zones'));
    $this->get('/temp/admin/zones/'.$this->refs['zone'].'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Setup/Zones/Edit'));
});

test('the Zones edit screen refuses callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get('/temp/admin/zones/'.$this->refs['zone'].'/edit'), $caller);
})->with('refused store admin callers');

test('the Zones edit screen returns 404 for a missing id', function () {
    actingAsStoreUser(createStoreStaff('manage_zones'));
    $this->get('/temp/admin/zones/999999/edit')->assertNotFound();
});

test('the Zones store saves validated values', function () {
    actingAsStoreUser(createStoreStaff('manage_zones'));
    $this->post('/temp/admin/zones', ['name' => 'West', 'country_id' => [$this->refs['country']]])->assertRedirect(route('zones.index'));
    $this->assertDatabaseHas('zones', ['name' => 'West']);
});

test('the Zones store validates fields before writing', function () {
    actingAsStoreUser(createStoreStaff('manage_zones'));
    $this->postJson('/temp/admin/zones', [])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->assertDatabaseCount('zones', 1);
});

test('the Zones store refuses writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post('/temp/admin/zones', ['name' => 'West', 'country_id' => [$this->refs['country']]]), $caller);
    $this->assertDatabaseCount('zones', 1);
})->with('refused store admin callers');

test('the Zones update saves validated values', function () {
    actingAsStoreUser(createStoreStaff('manage_zones'));
    $this->put('/temp/admin/zones/'.$this->refs['zone'], ['name' => 'West', 'country_id' => [$this->refs['country']]])->assertRedirect(route('zones.index'));
    $this->assertDatabaseHas('zones', ['id' => $this->refs['zone'], 'name' => 'West']);
});

test('the Zones update validates fields without changing data', function () {
    actingAsStoreUser(createStoreStaff('manage_zones'));
    $this->putJson('/temp/admin/zones/'.$this->refs['zone'], [])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->assertDatabaseHas('zones', ['id' => $this->refs['zone'], 'name' => 'Europe']);
});

test('the Zones update refuses writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->put('/temp/admin/zones/'.$this->refs['zone'], ['name' => 'West', 'country_id' => [$this->refs['country']]]), $caller);
    $this->assertDatabaseHas('zones', ['id' => $this->refs['zone'], 'name' => 'Europe']);
})->with('refused store admin callers');

test('the Zones update returns 404 for a missing id', function () {
    actingAsStoreUser(createStoreStaff('manage_zones'));
    $this->put('/temp/admin/zones/999999', ['name' => 'West', 'country_id' => [$this->refs['country']]])->assertNotFound();
});

test('the Zones delete keeps the query and removes the record', function () {
    actingAsStoreUser(createStoreStaff('manage_zones'));
    $this->delete('/temp/admin/zones/'.$this->refs['zone'].'?page=2&per_page=10')->assertRedirect(route('zones.index', ['page' => 2, 'per_page' => 10]));
    $this->assertDatabaseMissing('zones', ['id' => $this->refs['zone']]);
});

test('the Zones delete refuses writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->delete('/temp/admin/zones/'.$this->refs['zone']), $caller);
    $this->assertDatabaseHas('zones', ['id' => $this->refs['zone']]);
})->with('refused store admin callers');

test('the Zones delete returns 404 for a missing id', function () {
    actingAsStoreUser(createStoreStaff('manage_zones'));
    $this->delete('/temp/admin/zones/999999')->assertNotFound();
});

test('the Zones routes do not accept a WMS identity', function () {
    actingAsAdmin();
    $this->get('/temp/admin/zones')->assertRedirect(route('shop.login'));
    $this->post('/temp/admin/zones', ['name' => 'West', 'country_id' => [$this->refs['country']]])->assertRedirect(route('shop.login'));
    $this->assertDatabaseCount('zones', 1);
});

test('zone writes reject inactive, assigned and duplicated country references', function () {
    actingAsStoreUser(createStoreStaff('manage_zones'));
    $assigned = DB::table('countries')->insertGetId(['name' => 'Assigned', 'code' => 'AA', 'status' => 1, 'zone_id' => $this->refs['zone']]);
    $inactive = DB::table('countries')->insertGetId(['name' => 'Inactive', 'code' => 'II', 'status' => 0, 'zone_id' => 0]);
    foreach ([$assigned, $inactive, 999999] as $id) {
        $this->postJson('/temp/admin/zones', ['name' => 'Invalid', 'country_id' => [$id]])->assertUnprocessable()->assertJsonValidationErrors('country_id.0');
    }
    $this->postJson('/temp/admin/zones', ['name' => 'Invalid', 'country_id' => [$this->refs['country'], $this->refs['country']]])->assertUnprocessable()->assertJsonValidationErrors('country_id.0');
    $this->assertDatabaseCount('zones', 1);
    $this->assertDatabaseHas('countries', ['id' => $assigned, 'zone_id' => $this->refs['zone']]);
});
test('zone update replaces its countries and deletion releases them', function () {
    actingAsStoreUser(createStoreStaff('manage_zones'));
    $old = DB::table('countries')->insertGetId(['name' => 'Old', 'code' => 'OO', 'status' => 1, 'zone_id' => $this->refs['zone']]);
    $this->put('/temp/admin/zones/'.$this->refs['zone'], ['name' => 'New zone', 'country_id' => [$this->refs['country']]])->assertRedirect(route('zones.index'));
    $this->assertDatabaseHas('countries', ['id' => $old, 'zone_id' => 0]);
    $this->assertDatabaseHas('countries', ['id' => $this->refs['country'], 'zone_id' => $this->refs['zone']]);
    $this->delete('/temp/admin/zones/'.$this->refs['zone'])->assertRedirect();
    $this->assertDatabaseHas('countries', ['id' => $this->refs['country'], 'zone_id' => 0]);
});
