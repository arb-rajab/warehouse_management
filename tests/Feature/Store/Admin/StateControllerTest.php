<?php

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->refs = createStoreReferenceData();
});

test('the States list can be rendered', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_states'));
    $this->get('/temp/admin/states')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Setup/States/Index'));
});

test('the States list exposes exactly the narrow record', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_states'));
    $this->get('/temp/admin/states')->assertOk()->assertInertia(fn (Assert $page) => $page->has('states.data', 1)->has('states.data.0', fn (Assert $row) => $row
        ->where('id', $this->refs['state'])
        ->where('name', 'North Holland')
        ->where('country_id', $this->refs['country'])
        ->where('country', ['id' => $this->refs['country'], 'name' => 'Netherlands'])
        ->where('status', true)
    ));
});

test('the States list pages without overlaps and validates page size', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_states'));
    $row = (array) DB::table('states')->where('id', $this->refs['state'])->first();
    unset($row['id']);
    foreach (range(1, 22) as $number) {
        $row['name'] = 'Record '.$number;
        DB::table('states')->insert($row);
    } assertPagesWithoutOverlap(fn (int $page) => $this->get('/temp/admin/states?page='.$page), fn ($response) => collect($response->inertiaProps('states.data'))->pluck('id')->all(), 23, 20);
    $this->get('/temp/admin/states?per_page=10')->assertOk()->assertInertia(fn (Assert $page) => $page->has('states.data', 10)->where('states.meta.per_page', 10));
    $this->getJson('/temp/admin/states?per_page=13')->assertUnprocessable()->assertJsonValidationErrors('per_page');
});

test('the States list refuses callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get('/temp/admin/states'), $caller);
})->with('refused store admin callers');

test('the States name filter excludes unrelated data and survives pagination', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_states'));
    $row = (array) DB::table('states')->where('id', $this->refs['state'])->first();
    unset($row['id']);
    $row['name'] = 'Unrelated';
    DB::table('states')->insert($row);
    $this->get('/temp/admin/states?sort_state='.urlencode('North Holland'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('states.data', 1)->where('states.data.0.id', $this->refs['state'])->where('filters.sort_state', 'North Holland'));
});

test('the States edit screen can be rendered', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_states'));
    $this->get('/temp/admin/states/'.$this->refs['state'].'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Setup/States/Edit'));
});

test('the States edit screen refuses callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get('/temp/admin/states/'.$this->refs['state'].'/edit'), $caller);
})->with('refused store admin callers');

test('the States edit screen returns 404 for a missing id', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_states'));
    $this->get('/temp/admin/states/999999/edit')->assertNotFound();
});

test('the States store saves validated values', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_states'));
    $this->post('/temp/admin/states', ['name' => 'South Holland', 'country_id' => $this->refs['country']])->assertRedirect(route('states.index'));
    $this->assertDatabaseHas('states', ['name' => 'South Holland']);
});

test('the States store validates fields before writing', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_states'));
    $this->postJson('/temp/admin/states', [])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->assertDatabaseCount('states', 1);
});

test('the States store refuses writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post('/temp/admin/states', ['name' => 'South Holland', 'country_id' => $this->refs['country']]), $caller);
    $this->assertDatabaseCount('states', 1);
})->with('refused store admin callers');

test('the States update saves validated values', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_states'));
    $this->put('/temp/admin/states/'.$this->refs['state'], ['name' => 'South Holland', 'country_id' => $this->refs['country']])->assertRedirect(route('states.index'));
    $this->assertDatabaseHas('states', ['id' => $this->refs['state'], 'name' => 'South Holland']);
});

test('the States update validates fields without changing data', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_states'));
    $this->putJson('/temp/admin/states/'.$this->refs['state'], [])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->assertDatabaseHas('states', ['id' => $this->refs['state'], 'name' => 'North Holland']);
});

test('the States update refuses writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->put('/temp/admin/states/'.$this->refs['state'], ['name' => 'South Holland', 'country_id' => $this->refs['country']]), $caller);
    $this->assertDatabaseHas('states', ['id' => $this->refs['state'], 'name' => 'North Holland']);
})->with('refused store admin callers');

test('the States update returns 404 for a missing id', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_states'));
    $this->put('/temp/admin/states/999999', ['name' => 'South Holland', 'country_id' => $this->refs['country']])->assertNotFound();
});

test('the States delete keeps the query and removes the record', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_states'));
    $this->delete('/temp/admin/states/'.$this->refs['state'].'?page=2&per_page=10')->assertRedirect(route('states.index', ['page' => 2, 'per_page' => 10]));
    $this->assertDatabaseMissing('states', ['id' => $this->refs['state']]);
});

test('the States delete refuses writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->delete('/temp/admin/states/'.$this->refs['state']), $caller);
    $this->assertDatabaseHas('states', ['id' => $this->refs['state']]);
})->with('refused store admin callers');

test('the States delete returns 404 for a missing id', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_states'));
    $this->delete('/temp/admin/states/999999')->assertNotFound();
});

test('the States status saves the posted value and keeps pagination', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_states'));
    $this->post('/temp/admin/states/'.$this->refs['state'].'/status'.'?page=2', ['status' => 0])->assertRedirect(route('states.index', ['page' => 2]));
    $this->assertDatabaseHas('states', ['id' => $this->refs['state'], 'status' => 0]);
});

test('the States status validates booleans without writing', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_states'));
    $this->postJson('/temp/admin/states/'.$this->refs['state'].'/status', ['status' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->assertDatabaseHas('states', ['id' => $this->refs['state'], 'status' => 1]);
});

test('the States status refuses writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post('/temp/admin/states/'.$this->refs['state'].'/status', ['status' => 0]), $caller);
    $this->assertDatabaseHas('states', ['id' => $this->refs['state'], 'status' => 1]);
})->with('refused store admin callers');

test('the States status returns 404 for a missing id', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_states'));
    $this->post('/temp/admin/states/999999/status', ['status' => 0])->assertNotFound();
});

test('the States routes do not accept a WMS identity', function () {
    actingAsAdmin();
    $this->get('/temp/admin/states')->assertRedirect(route('shop.login'));
    $this->post('/temp/admin/states', ['name' => 'South Holland', 'country_id' => $this->refs['country']])->assertRedirect(route('shop.login'));
    $this->assertDatabaseCount('states', 1);
});

test('state activation enables only its cities while deactivation leaves city statuses alone', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_states'));
    $refs = $this->refs;
    $otherState = DB::table('states')->insertGetId(['name' => 'Other', 'country_id' => $refs['country']]);
    $noise = DB::table('cities')->insertGetId(['name' => 'Noise', 'state_id' => $otherState, 'status' => 0]);
    DB::table('cities')->where('id', $refs['city'])->update(['status' => 0]);
    $this->post('/temp/admin/states/'.$refs['state'].'/status', ['status' => 1])->assertRedirect();
    $this->assertDatabaseHas('cities', ['id' => $refs['city'], 'status' => 1]);
    $this->assertDatabaseHas('cities', ['id' => $noise, 'status' => 0]);
    $this->post('/temp/admin/states/'.$refs['state'].'/status', ['status' => 0])->assertRedirect();
    $this->assertDatabaseHas('cities', ['id' => $refs['city'], 'status' => 1]);
});
test('states validate active countries and filter by country', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_states'));
    $noise = DB::table('countries')->insertGetId(['name' => 'Inactive', 'code' => 'XX', 'status' => 0]);
    DB::table('states')->insert(['name' => 'Other state', 'country_id' => $noise]);
    $this->get('/temp/admin/states?sort_country='.$this->refs['country'])->assertOk()->assertInertia(fn (Assert $page) => $page->has('states.data', 1)->where('countries', [['id' => $this->refs['country'], 'name' => 'Netherlands']]));
    $this->postJson('/temp/admin/states', ['name' => 'Invalid', 'country_id' => $noise])->assertUnprocessable()->assertJsonValidationErrors('country_id');
    $this->assertDatabaseMissing('states', ['name' => 'Invalid']);
});
