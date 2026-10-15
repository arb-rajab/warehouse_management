<?php

use App\Models\Carrier;
use App\Models\CarrierRangePrice;
use App\Models\Upload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->fixture = createStoreCarrierTariff();
    $this->carrier = $this->fixture['carrier'];
    $this->payload = $this->fixture['payload'];
});

foreach (['index' => '/temp/admin/carriers', 'create' => '/temp/admin/carriers/create', 'edit' => '/temp/admin/carriers/1/edit'] as $page => $uri) {
    test("the carrier $page screen can be rendered", function () use ($page, $uri) {
        actingAsStoreUser(createStoreStaff('manage_carriers'));
        $this->get($uri)->assertOk()->assertInertia(fn (Assert $view) => $view->component('Store/Admin/Setup/Carriers/'.ucfirst($page)));
    });
    test("the carrier $page refuses unauthorized callers", function (string $caller) use ($uri) {
        actingAsRefusedStoreCaller($caller);
        assertStoreCallerRefused($this->get($uri), $caller);
    })->with('refused store admin callers');
}

test('carrier list exposes the complete narrow record and filters', function () {
    actingAsStoreUser(createStoreStaff('manage_carriers'));
    $this->get('/temp/admin/carriers')->assertInertia(fn (Assert $view) => $view
        ->has('carriers.data', 1)->has('carriers.data.0', fn (Assert $row) => $row
        ->where('id', $this->carrier->id)->where('name', 'Parcel')->where('transit_time', '2–3 days')
        ->where('logo', null)->where('free_shipping', false)->where('status', true))
        ->has('filters', fn (Assert $filters) => $filters->where('search', '')->where('per_page', 20)));
});

test('carrier search excludes noise and pages without overlaps', function () {
    actingAsStoreUser(createStoreStaff('manage_carriers'));
    foreach (range(1, 22) as $n) {
        DB::table('carriers')->insert(['name' => "Express $n", 'transit_time' => '1', 'free_shipping' => 1, 'status' => 0]);
    }
    $this->get('/temp/admin/carriers?search=Parcel')->assertInertia(fn (Assert $view) => $view->has('carriers.data', 1)->where('carriers.data.0.id', $this->carrier->id));
    assertPagesWithoutOverlap(fn (int $page) => $this->get("/temp/admin/carriers?per_page=20&page=$page"), fn ($response) => array_column($response->inertiaProps('carriers.data'), 'id'), 23, 20);
    $this->get('/temp/admin/carriers?per_page=11')->assertSessionHasErrors('per_page');
    $this->get('/temp/admin/carriers?search='.str_repeat('x', 256))->assertSessionHasErrors('search');
});

test('carrier edit round trips every range and zone price with narrow options', function () {
    actingAsStoreUser(createStoreStaff('manage_carriers'));
    $this->get('/temp/admin/carriers/'.$this->carrier->id.'/edit')->assertInertia(fn (Assert $view) => $view
        ->has('carrier', fn (Assert $row) => $row->where('id', $this->carrier->id)->where('name', 'Parcel')
            ->where('transit_time', '2–3 days')->where('logo', null)->where('free_shipping', false)->where('status', true)
            ->where('ranges', [
                ['billing_type' => 'weight_based', 'delimiter1' => '0', 'delimiter2' => '10', 'prices' => [$this->fixture['zone'] => '5', $this->fixture['otherZone'] => '7']],
                ['billing_type' => 'weight_based', 'delimiter1' => '10', 'delimiter2' => '20', 'prices' => [$this->fixture['zone'] => '8', $this->fixture['otherZone'] => '9']],
            ]))
        ->has('zones', 2)->has('zones.0', fn (Assert $zone) => $zone->where('id', $this->fixture['zone'])->where('name', 'Europe'))
        ->where('initialFiles', []));
    $this->put('/temp/admin/carriers/'.$this->carrier->id, $this->payload)->assertRedirect(route('carriers.index'));
    expect(DB::table('carrier_ranges')->where('carrier_id', $this->carrier->id)->count())->toBe(2)
        ->and(DB::table('carrier_range_prices')->where('carrier_id', $this->carrier->id)->pluck('price')->map(fn ($v) => (float) $v)->all())->toBe([5.0, 7.0, 8.0, 9.0]);
});

test('paid carrier creation preserves both billing modes and zone prices', function (string $mode) {
    actingAsStoreUser(createStoreStaff('manage_carriers'));
    $this->post('/temp/admin/carriers', [...$this->payload, 'carrier_name' => 'Express', 'billing_type' => $mode])->assertRedirect(route('carriers.index'));
    $id = DB::table('carriers')->where('name', 'Express')->value('id');
    $this->assertDatabaseHas('carrier_ranges', ['carrier_id' => $id, 'billing_type' => $mode, 'delimiter1' => 10, 'delimiter2' => 20]);
    $this->assertDatabaseHas('carrier_range_prices', ['carrier_id' => $id, 'zone_id' => $this->fixture['otherZone'], 'price' => 9]);
    $this->assertDatabaseHas('carrier_ranges', ['carrier_id' => $this->carrier->id, 'billing_type' => 'weight_based']);
})->with(['weight_based', 'price_based']);

test('free carrier creation ignores tariff inputs and retains free shipping on edit', function () {
    actingAsStoreUser(createStoreStaff('manage_carriers'));
    $this->post('/temp/admin/carriers', [...$this->payload, 'carrier_name' => 'Free', 'shipping_type' => 1])->assertRedirect(route('carriers.index'));
    $id = DB::table('carriers')->where('name', 'Free')->value('id');
    $this->assertDatabaseHas('carriers', ['id' => $id, 'free_shipping' => 1]);
    $this->assertDatabaseMissing('carrier_ranges', ['carrier_id' => $id]);
    $this->get("/temp/admin/carriers/$id/edit")->assertInertia(fn (Assert $view) => $view->where('carrier.free_shipping', true)->where('carrier.ranges', []));
});

test('carrier update replaces changed and removed ranges zones and billing mode without touching noise', function () {
    actingAsStoreUser(createStoreStaff('manage_carriers'));
    $oldRanges = DB::table('carrier_ranges')->pluck('id')->all();
    $noise = DB::table('carrier_ranges')->insertGetId(['carrier_id' => 999, 'billing_type' => 'weight_based', 'delimiter1' => 0, 'delimiter2' => 5]);
    $this->put('/temp/admin/carriers/'.$this->carrier->id, [...$this->payload,
        'billing_type' => 'price_based', 'delimiter1' => ['0'], 'delimiter2' => ['30'],
        'zones' => [$this->fixture['zone']], 'carrier_price' => [$this->fixture['zone'] => ['4.25']],
    ])->assertRedirect(route('carriers.index'));
    expect(DB::table('carrier_ranges')->whereIn('id', $oldRanges)->count())->toBe(0);
    $this->assertDatabaseHas('carrier_ranges', ['id' => $noise]);
    $this->assertDatabaseHas('carrier_ranges', ['carrier_id' => $this->carrier->id, 'billing_type' => 'price_based', 'delimiter2' => 30]);
    $this->assertDatabaseMissing('carrier_range_prices', ['carrier_id' => $this->carrier->id, 'zone_id' => $this->fixture['otherZone']]);
    $this->assertDatabaseHas('carrier_range_prices', ['carrier_id' => $this->carrier->id, 'price' => 4.25]);
    $this->put('/temp/admin/carriers/'.$this->carrier->id, [...$this->payload, 'shipping_type' => 1])->assertRedirect();
    $this->assertDatabaseMissing('carrier_ranges', ['carrier_id' => $this->carrier->id]);
    $this->assertDatabaseMissing('carrier_range_prices', ['carrier_id' => $this->carrier->id]);
    $this->put('/temp/admin/carriers/'.$this->carrier->id, $this->payload)->assertRedirect();
    $this->assertDatabaseHas('carriers', ['id' => $this->carrier->id, 'free_shipping' => 0]);
    expect(DB::table('carrier_ranges')->where('carrier_id', $this->carrier->id)->count())->toBe(2);
});

foreach (['store', 'update', 'destroy', 'status'] as $action) {
    test("carrier $action refuses writes without changing any tariff data", function (string $caller) use ($action) {
        $before = collect(['carriers', 'carrier_ranges', 'carrier_range_prices'])->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toArray()])->all();
        actingAsRefusedStoreCaller($caller);
        $response = match ($action) {
            'store' => $this->post('/temp/admin/carriers', $this->payload),
            'update' => $this->put('/temp/admin/carriers/'.$this->carrier->id, [...$this->payload, 'carrier_name' => 'Changed']),
            'destroy' => $this->delete('/temp/admin/carriers/'.$this->carrier->id),
            'status' => $this->post('/temp/admin/carriers/update_status', ['id' => $this->carrier->id, 'status' => 0]),
        };
        assertStoreCallerRefused($response, $caller);
        foreach ($before as $table => $rows) {
            expect(DB::table($table)->orderBy('id')->get()->toArray())->toEqual($rows);
        }
    })->with('refused store admin callers');
}

test('every carrier action isolates the WMS guard and preserves tariff data', function () {
    actingAsAdmin();
    foreach ([['get', '/temp/admin/carriers'], ['get', '/temp/admin/carriers/create'], ['get', '/temp/admin/carriers/'.$this->carrier->id.'/edit'], ['post', '/temp/admin/carriers'], ['put', '/temp/admin/carriers/'.$this->carrier->id], ['delete', '/temp/admin/carriers/'.$this->carrier->id], ['post', '/temp/admin/carriers/update_status']] as [$method, $url]) {
        $this->$method($url, [...$this->payload, 'id' => $this->carrier->id, 'status' => 0])->assertRedirect(route('shop.login'));
    }
    $this->assertDatabaseHas('carriers', ['id' => $this->carrier->id, 'name' => 'Parcel', 'status' => 1]);
    $this->assertDatabaseCount('carriers', 1);
    $this->assertDatabaseCount('carrier_ranges', 2);
    $this->assertDatabaseCount('carrier_range_prices', 4);
});

test('carrier status and deletion preserve filters and deletion removes only its tariff', function () {
    actingAsStoreUser(createStoreStaff('manage_carriers'));
    $this->post('/temp/admin/carriers/update_status?page=2&search=Parcel', ['id' => $this->carrier->id, 'status' => false])->assertRedirect(route('carriers.index', ['page' => 2, 'search' => 'Parcel']));
    $this->assertDatabaseHas('carriers', ['id' => $this->carrier->id, 'status' => 0]);
    $noise = DB::table('carriers')->insertGetId(['name' => 'Noise', 'transit_time' => '1']);
    $this->delete('/temp/admin/carriers/'.$this->carrier->id.'?page=2&per_page=10')->assertRedirect(route('carriers.index', ['page' => 2, 'per_page' => 10]));
    $this->assertDatabaseMissing('carriers', ['id' => $this->carrier->id]);
    $this->assertDatabaseHas('carriers', ['id' => $noise]);
    $this->assertDatabaseCount('carrier_ranges', 0);
    $this->assertDatabaseCount('carrier_range_prices', 0);
});

test('carrier edit update delete and status return 404 for missing records', function () {
    actingAsStoreUser(createStoreStaff('manage_carriers'));
    $this->get('/temp/admin/carriers/999999/edit')->assertNotFound();
    $this->put('/temp/admin/carriers/999999', $this->payload)->assertNotFound();
    $this->delete('/temp/admin/carriers/999999')->assertNotFound();
    $this->post('/temp/admin/carriers/update_status', ['id' => 999999, 'status' => 1])->assertNotFound();
});

test('carrier writes reject invalid nested tariffs without changing existing data', function (string $field, mixed $value, string $error) {
    actingAsStoreUser(createStoreStaff('manage_carriers'));
    foreach (['post' => '/temp/admin/carriers', 'put' => '/temp/admin/carriers/'.$this->carrier->id] as $method => $url) {
        $this->$method($url, [...$this->payload, $field => $value], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors($error);
    }
    $this->assertDatabaseCount('carriers', 1);
    $this->assertDatabaseCount('carrier_ranges', 2);
    $this->assertDatabaseCount('carrier_range_prices', 4);
    $this->assertDatabaseHas('carriers', ['id' => $this->carrier->id, 'name' => 'Parcel']);
})->with([
    ['carrier_name', '', 'carrier_name'], ['transit_time', str_repeat('x', 256), 'transit_time'],
    ['shipping_type', 'bogus', 'shipping_type'], ['billing_type', 'invented', 'billing_type'],
    ['delimiter1', ['0', '11'], 'delimiter1.1'], ['delimiter2', ['0', '20'], 'delimiter2.0'],
    ['delimiter2', ['10'], 'delimiter2'], ['delimiter1', ['0.001', '10'], 'delimiter1.0'],
    ['delimiter1', ['999999999999999999999999', '10'], 'delimiter1.0'],
    ['zones', [999999], 'zones.0'], ['zones', [1, 1], 'zones.0'],
    ['carrier_price', [1 => ['5']], 'carrier_price'], ['carrier_price', [1 => ['5', '8'], 2 => ['7']], 'carrier_price.2'],
    ['carrier_price', [1 => ['1000000', '8'], 2 => ['7', '9']], 'carrier_price.1.0'],
    ['carrier_price', [1 => ['5', '8'], 2 => ['7', '9'], 999 => ['1', '2']], 'carrier_price'],
    ['carrier_price', [1 => ['5', '8'], 2 => ['bad', '9']], 'carrier_price.2.0'],
    ['delimiter1', [1 => '0', 2 => '10'], 'delimiter1'],
    ['delimiter1', 'invalid', 'delimiter1'], ['delimiter1', [['invalid'], '10'], 'delimiter1.0'],
    ['carrier_price', 'invalid', 'carrier_price'], ['carrier_price', [1 => [['bad'], '8'], 2 => ['7', '9']], 'carrier_price.1.0'],
    ['logo', ['invalid'], 'logo'],
]);

test('carrier upload references use the canonical ownership and library permissions', function () {
    $staff = createStoreStaff('manage_carriers');
    actingAsStoreUser($staff);
    $owner = createStoreUser('customer');
    $image = Upload::factory()->create(['user_id' => $owner->id, 'type' => 'image']);
    $notImage = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'document']);
    foreach ([$image->id, $notImage->id, 999999, $image->id.','.$notImage->id] as $id) {
        $this->postJson('/temp/admin/carriers', [...$this->payload, 'logo' => $id])->assertUnprocessable()->assertJsonValidationErrors('logo');
    }
    $own = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image']);
    $this->put('/temp/admin/carriers/'.$this->carrier->id, [...$this->payload, 'logo' => $own->id])->assertRedirect();
    $this->get('/temp/admin/carriers/'.$this->carrier->id.'/edit')->assertInertia(fn (Assert $view) => $view->where('carrier.logo', $own->id)->has('initialFiles', 1)->where('initialFiles.0.id', $own->id));
    $library = createStoreStaff('manage_carriers', 'view_uploads');
    actingAsStoreUser($library);
    $this->put('/temp/admin/carriers/'.$this->carrier->id, [...$this->payload, 'logo' => $image->id])->assertRedirect();
});

test('carrier status validates its payload', function () {
    actingAsStoreUser(createStoreStaff('manage_carriers'));
    $this->postJson('/temp/admin/carriers/update_status', ['id' => $this->carrier->id, 'status' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->assertDatabaseHas('carriers', ['id' => $this->carrier->id, 'status' => 1]);
});

foreach (['store', 'update', 'destroy'] as $action) {
    test("carrier $action rolls back the whole tariff on persistence failure", function () use ($action) {
        actingAsStoreUser(createStoreStaff('manage_carriers'));
        $before = collect(['carriers', 'carrier_ranges', 'carrier_range_prices'])->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toArray()])->all();
        if ($action === 'destroy') {
            Carrier::deleting(fn () => throw new RuntimeException('simulated carrier delete failure'));
        } else {
            $insertions = 0;
            CarrierRangePrice::creating(function () use (&$insertions) {
                if (++$insertions === 2) {
                    throw new RuntimeException('simulated tariff write failure');
                }
            });
        }
        $this->withoutExceptionHandling();
        try {
            expect(fn () => match ($action) {
                'store' => $this->post('/temp/admin/carriers', [...$this->payload, 'carrier_name' => 'Failed']),
                'update' => $this->put('/temp/admin/carriers/'.$this->carrier->id, [...$this->payload, 'carrier_name' => 'Failed']),
                'destroy' => $this->delete('/temp/admin/carriers/'.$this->carrier->id),
            })->toThrow(RuntimeException::class);
            foreach ($before as $table => $rows) {
                expect(DB::table($table)->orderBy('id')->get()->toArray())->toEqual($rows);
            }
        } finally {
            Carrier::flushEventListeners();
            CarrierRangePrice::flushEventListeners();
        }
    });
}

test('carrier routes have no broken show consumer and retain the API carrier action', function () {
    expect(Route::has('carriers.show'))->toBeFalse();
    $routes = Route::getRoutes();
    $api = collect($routes->getRoutes())->first(fn ($route) => str_ends_with($route->uri(), '/carriers') && str_contains($route->getActionName(), 'Api\\V2\\CarrierController'));
    expect($api?->getActionName())->toBe('App\\Http\\Controllers\\Api\\V2\\CarrierController@index');
});

test('carrier update preserves an existing foreign logo without allowing another foreign reference or malformed input', function () {
    $staff = createStoreStaff('manage_carriers');
    actingAsStoreUser($staff);
    $foreign = createStoreUser('customer');
    $old = Upload::factory()->create(['user_id' => $foreign->id, 'type' => 'image']);
    $other = Upload::factory()->create(['user_id' => $foreign->id, 'type' => 'image']);
    $this->carrier->logo = $old->id;
    $this->carrier->save();
    $this->put('/temp/admin/carriers/'.$this->carrier->id, [...$this->payload, 'logo' => $old->id])->assertRedirect();
    $this->get('/temp/admin/carriers/'.$this->carrier->id.'/edit')->assertInertia(fn (Assert $view) => $view->where('carrier.logo', $old->id)->where('initialFiles', []));
    foreach ([$other->id, ['invalid']] as $value) {
        $this->putJson('/temp/admin/carriers/'.$this->carrier->id, [...$this->payload, 'logo' => $value])->assertUnprocessable()->assertJsonValidationErrors('logo');
    }
    $this->assertDatabaseHas('carriers', ['id' => $this->carrier->id, 'logo' => $old->id]);
});
