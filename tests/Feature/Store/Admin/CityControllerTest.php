<?php

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->refs = createStoreReferenceData();
});

test('the Cities list can be rendered', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_cities'));
    $this->get('/temp/admin/cities')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Setup/Cities/Index'));
});

test('the Cities list exposes exactly the narrow record', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_cities'));
    $this->get('/temp/admin/cities')->assertOk()->assertInertia(fn (Assert $page) => $page->has('cities.data', 1)->has('cities.data.0', fn (Assert $row) => $row
        ->where('id', $this->refs['city'])
        ->where('name', 'Amsterdam')
        ->where('state_id', $this->refs['state'])
        ->where('state', ['id' => $this->refs['state'], 'name' => 'North Holland'])
        ->where('cost', '2.5')
        ->where('status', true)
    ));
});

test('the Cities list pages without overlaps and validates page size', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_cities'));
    $row = (array) DB::table('cities')->where('id', $this->refs['city'])->first();
    unset($row['id']);
    foreach (range(1, 22) as $number) {
        $row['name'] = 'Record '.$number;
        DB::table('cities')->insert($row);
    } assertPagesWithoutOverlap(fn (int $page) => $this->get('/temp/admin/cities?page='.$page), fn ($response) => collect($response->inertiaProps('cities.data'))->pluck('id')->all(), 23, 20);
    $this->get('/temp/admin/cities?per_page=10')->assertOk()->assertInertia(fn (Assert $page) => $page->has('cities.data', 10)->where('cities.meta.per_page', 10));
    $this->getJson('/temp/admin/cities?per_page=13')->assertUnprocessable()->assertJsonValidationErrors('per_page');
});

test('the Cities list refuses callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get('/temp/admin/cities'), $caller);
})->with('refused store admin callers');

test('the Cities name filter excludes unrelated data and survives pagination', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_cities'));
    $row = (array) DB::table('cities')->where('id', $this->refs['city'])->first();
    unset($row['id']);
    $row['name'] = 'Unrelated';
    DB::table('cities')->insert($row);
    $this->get('/temp/admin/cities?sort_city='.urlencode('Amsterdam'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('cities.data', 1)->where('cities.data.0.id', $this->refs['city'])->where('filters.sort_city', 'Amsterdam'));
});

test('the Cities edit screen can be rendered', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_cities'));
    $this->get('/temp/admin/cities/'.$this->refs['city'].'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Setup/Cities/Edit'));
});

test('the Cities edit screen refuses callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get('/temp/admin/cities/'.$this->refs['city'].'/edit'), $caller);
})->with('refused store admin callers');

test('the Cities edit screen returns 404 for a missing id', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_cities'));
    $this->get('/temp/admin/cities/999999/edit')->assertNotFound();
});

test('the Cities store saves validated values', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_cities'));
    $this->post('/temp/admin/cities', ['name' => 'Rotterdam', 'state_id' => $this->refs['state'], 'cost' => '3.25'])->assertRedirect(route('cities.index'));
    $this->assertDatabaseHas('cities', ['name' => 'Rotterdam']);
});

test('the Cities store validates fields before writing', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_cities'));
    $this->postJson('/temp/admin/cities', [])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->assertDatabaseCount('cities', 1);
});

test('the Cities store refuses writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post('/temp/admin/cities', ['name' => 'Rotterdam', 'state_id' => $this->refs['state'], 'cost' => '3.25']), $caller);
    $this->assertDatabaseCount('cities', 1);
})->with('refused store admin callers');

test('the Cities update saves validated values', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_cities'));
    $this->put('/temp/admin/cities/'.$this->refs['city'], ['name' => 'Rotterdam', 'state_id' => $this->refs['state'], 'cost' => '3.25'] + ['lang' => 'en'])->assertRedirect(route('cities.index'));
    $this->assertDatabaseHas('cities', ['id' => $this->refs['city'], 'name' => 'Rotterdam']);
});

test('the Cities update validates fields without changing data', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_cities'));
    $this->putJson('/temp/admin/cities/'.$this->refs['city'], [])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->assertDatabaseHas('cities', ['id' => $this->refs['city'], 'name' => 'Amsterdam']);
});

test('the Cities update refuses writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->put('/temp/admin/cities/'.$this->refs['city'], ['name' => 'Rotterdam', 'state_id' => $this->refs['state'], 'cost' => '3.25'] + ['lang' => 'en']), $caller);
    $this->assertDatabaseHas('cities', ['id' => $this->refs['city'], 'name' => 'Amsterdam']);
})->with('refused store admin callers');

test('the Cities update returns 404 for a missing id', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_cities'));
    $this->put('/temp/admin/cities/999999', ['name' => 'Rotterdam', 'state_id' => $this->refs['state'], 'cost' => '3.25'] + ['lang' => 'en'])->assertNotFound();
});

test('the Cities delete keeps the query and removes the record', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_cities'));
    $this->delete('/temp/admin/cities/'.$this->refs['city'].'?page=2&per_page=10')->assertRedirect(route('cities.index', ['page' => 2, 'per_page' => 10]));
    $this->assertDatabaseMissing('cities', ['id' => $this->refs['city']]);
});

test('the Cities delete refuses writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->delete('/temp/admin/cities/'.$this->refs['city']), $caller);
    $this->assertDatabaseHas('cities', ['id' => $this->refs['city']]);
})->with('refused store admin callers');

test('the Cities delete returns 404 for a missing id', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_cities'));
    $this->delete('/temp/admin/cities/999999')->assertNotFound();
});

test('the Cities status saves the posted value and keeps pagination', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_cities'));
    $this->post('/temp/admin/cities/'.$this->refs['city'].'/status'.'?page=2', ['status' => 0])->assertRedirect(route('cities.index', ['page' => 2]));
    $this->assertDatabaseHas('cities', ['id' => $this->refs['city'], 'status' => 0]);
});

test('the Cities status validates booleans without writing', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_cities'));
    $this->postJson('/temp/admin/cities/'.$this->refs['city'].'/status', ['status' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->assertDatabaseHas('cities', ['id' => $this->refs['city'], 'status' => 1]);
});

test('the Cities status refuses writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post('/temp/admin/cities/'.$this->refs['city'].'/status', ['status' => 0]), $caller);
    $this->assertDatabaseHas('cities', ['id' => $this->refs['city'], 'status' => 1]);
})->with('refused store admin callers');

test('the Cities status returns 404 for a missing id', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_cities'));
    $this->post('/temp/admin/cities/999999/status', ['status' => 0])->assertNotFound();
});

test('the Cities routes do not accept a WMS identity', function () {
    actingAsAdmin();
    $this->get('/temp/admin/cities')->assertRedirect(route('shop.login'));
    $this->post('/temp/admin/cities', ['name' => 'Rotterdam', 'state_id' => $this->refs['state'], 'cost' => '3.25'])->assertRedirect(route('shop.login'));
    $this->assertDatabaseCount('cities', 1);
});

test('city edits preserve the base name in Arabic and delete translations with the record', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_cities'));
    DB::table('languages')->insert(['code' => 'ar', 'name' => 'Arabic', 'rtl' => 1]);
    $this->put('/temp/admin/cities/'.$this->refs['city'], ['name' => 'أمستردام', 'lang' => 'ar', 'state_id' => $this->refs['state'], 'cost' => '5.25'])->assertRedirect(route('cities.index'));
    $this->assertDatabaseHas('cities', ['id' => $this->refs['city'], 'name' => 'Amsterdam', 'cost' => '5.25']);
    $this->get('/temp/admin/cities/'.$this->refs['city'].'/edit?lang=ar')->assertOk()->assertInertia(fn (Assert $page) => $page->where('lang', 'ar')->where('name', 'أمستردام'));
    $this->withSession(['locale' => 'ar'])->get('/temp/admin/cities')->assertOk()->assertInertia(fn (Assert $page) => $page->where('cities.data.0.name', 'أمستردام'));
    $this->delete('/temp/admin/cities/'.$this->refs['city'])->assertRedirect();
    $this->assertDatabaseMissing('city_translations', ['city_id' => $this->refs['city']]);
});
test('cities validate state, cost and language and filter by state', function () {
    actingAsStoreUser(createStoreStaff('manage_shipping_cities'));
    $noise = DB::table('states')->insertGetId(['name' => 'Inactive', 'country_id' => $this->refs['country'], 'status' => 0]);
    DB::table('cities')->insert(['name' => 'Noise', 'state_id' => $noise]);
    $this->get('/temp/admin/cities?sort_state='.$this->refs['state'])->assertOk()->assertInertia(fn (Assert $page) => $page->has('cities.data', 1)->where('states', [['id' => $this->refs['state'], 'name' => 'North Holland']]));
    $this->putJson('/temp/admin/cities/'.$this->refs['city'], ['name' => 'Invalid', 'state_id' => $noise, 'cost' => -1, 'lang' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors(['state_id', 'cost', 'lang']);
    $this->assertDatabaseHas('cities', ['id' => $this->refs['city'], 'name' => 'Amsterdam']);
});
