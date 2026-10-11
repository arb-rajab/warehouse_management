<?php

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('countries render as a narrow paginated Inertia payload with filters', function () {
    actingAsStoreUser(createStoreStaff('shipping_country_setting'));
    $firstId = null;
    foreach (range(1, 23) as $number) {
        $id = DB::table('countries')->insertGetId([
            'code' => sprintf('%02d', $number),
            'name' => "Country {$number}",
            'status' => $number % 2,
        ]);
        $firstId ??= $id;
    }

    $this->get('/temp/admin/countries?search=Country&per_page=10')->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Store/Admin/Shipping/Countries/Index')
            ->where('filters', ['search' => 'Country', 'per_page' => 10])
            ->has('countries.data', 10)
            ->has('countries.data.0', fn (Assert $country) => $country
                ->where('id', $firstId)
                ->where('code', '01')
                ->where('name', 'Country 1')
                ->where('zone_id', 0)
                ->where('active', true))
            ->where('countries.meta.total', 23)
    );

    $this->get('/temp/admin/countries?per_page=7')->assertSessionHasErrors('per_page');
});

test('a country status is validated, updated and missing ids return 404', function () {
    actingAsStoreUser(createStoreStaff('shipping_country_setting'));
    $id = DB::table('countries')->insertGetId(['code' => 'NL', 'name' => 'Netherlands', 'status' => 1]);

    $this->patch("/temp/admin/countries/{$id}/status", ['status' => false])->assertRedirect(route('countries.index'));
    $this->assertDatabaseHas('countries', ['id' => $id, 'status' => 0]);
    $this->patch("/temp/admin/countries/{$id}/status", ['status' => 'invalid'])->assertSessionHasErrors('status');
    $this->patch('/temp/admin/countries/999999/status', ['status' => true])->assertNotFound();
});

test('country routes refuse callers without the permission and change nothing', function (string $caller) {
    $id = DB::table('countries')->insertGetId(['code' => 'NL', 'name' => 'Netherlands', 'status' => 1]);
    actingAsRefusedStoreCaller($caller);

    assertStoreCallerRefused($this->get('/temp/admin/countries'), $caller);
    assertStoreCallerRefused($this->patch("/temp/admin/countries/{$id}/status", ['status' => false]), $caller);
    $this->assertDatabaseHas('countries', ['id' => $id, 'status' => 1]);
})->with('refused store admin callers');
