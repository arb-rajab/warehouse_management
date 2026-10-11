<?php

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->refs = createStoreReferenceData();
});

test('the Countries list can be rendered', function () {
    actingAsStoreUser(createStoreStaff('shipping_country_setting'));
    $this->get('/temp/admin/countries')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Setup/Countries/Index'));
});

test('the Countries list exposes exactly the narrow record', function () {
    actingAsStoreUser(createStoreStaff('shipping_country_setting'));
    $this->get('/temp/admin/countries')->assertOk()->assertInertia(fn (Assert $page) => $page->has('countries.data', 1)->has('countries.data.0', fn (Assert $row) => $row
        ->where('id', $this->refs['country'])
        ->where('name', 'Netherlands')
        ->where('code', 'NL')
        ->where('status', true)
    ));
});

test('the Countries list pages without overlaps and validates page size', function () {
    actingAsStoreUser(createStoreStaff('shipping_country_setting'));
    $row = (array) DB::table('countries')->where('id', $this->refs['country'])->first();
    unset($row['id']);
    foreach (range(1, 22) as $number) {
        $row['name'] = 'Record '.$number;
        DB::table('countries')->insert($row);
    } assertPagesWithoutOverlap(fn (int $page) => $this->get('/temp/admin/countries?page='.$page), fn ($response) => collect($response->inertiaProps('countries.data'))->pluck('id')->all(), 23, 20);
    $this->get('/temp/admin/countries?per_page=10')->assertOk()->assertInertia(fn (Assert $page) => $page->has('countries.data', 10)->where('countries.meta.per_page', 10));
    $this->getJson('/temp/admin/countries?per_page=13')->assertUnprocessable()->assertJsonValidationErrors('per_page');
});

test('the Countries list refuses callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get('/temp/admin/countries'), $caller);
})->with('refused store admin callers');

test('the Countries name filter excludes unrelated data and survives pagination', function () {
    actingAsStoreUser(createStoreStaff('shipping_country_setting'));
    $row = (array) DB::table('countries')->where('id', $this->refs['country'])->first();
    unset($row['id']);
    $row['name'] = 'Unrelated';
    DB::table('countries')->insert($row);
    $this->get('/temp/admin/countries?sort_country='.urlencode('Netherlands'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('countries.data', 1)->where('countries.data.0.id', $this->refs['country'])->where('filters.sort_country', 'Netherlands'));
});

test('the Countries status saves the posted value and keeps pagination', function () {
    actingAsStoreUser(createStoreStaff('shipping_country_setting'));
    $this->post('/temp/admin/countries/'.$this->refs['country'].'/status'.'?page=2', ['status' => 0])->assertRedirect(route('countries.index', ['page' => 2]));
    $this->assertDatabaseHas('countries', ['id' => $this->refs['country'], 'status' => 0]);
});

test('the Countries status validates booleans without writing', function () {
    actingAsStoreUser(createStoreStaff('shipping_country_setting'));
    $this->postJson('/temp/admin/countries/'.$this->refs['country'].'/status', ['status' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->assertDatabaseHas('countries', ['id' => $this->refs['country'], 'status' => 1]);
});

test('the Countries status refuses writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post('/temp/admin/countries/'.$this->refs['country'].'/status', ['status' => 0]), $caller);
    $this->assertDatabaseHas('countries', ['id' => $this->refs['country'], 'status' => 1]);
})->with('refused store admin callers');

test('the Countries status returns 404 for a missing id', function () {
    actingAsStoreUser(createStoreStaff('shipping_country_setting'));
    $this->post('/temp/admin/countries/999999/status', ['status' => 0])->assertNotFound();
});

test('the Countries routes do not accept a WMS identity', function () {
    actingAsAdmin();
    $this->get('/temp/admin/countries')->assertRedirect(route('shop.login'));
    $this->post('/temp/admin/countries/'.$this->refs['country'].'/status', ['status' => 0])->assertRedirect(route('shop.login'));
    $this->assertDatabaseHas('countries', ['id' => $this->refs['country'], 'status' => 1]);
});
