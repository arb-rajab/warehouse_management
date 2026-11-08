<?php

use App\Models\Warehouse;

test('an authenticated worker can list every warehouse by name with every property the app reads', function () {
    actingAsMobileUser();
    $annex = Warehouse::factory()->create(['name' => 'Annex', 'code' => 'ANX', 'address' => 'Industrieweg 12']);
    $default = Warehouse::query()->where('name', Warehouse::DEFAULT_NAME)->sole();

    $response = $this->getJson('/api/v1/warehouses');

    $response->assertOk();
    expect($response->json())->toEqual([
        ['id' => $annex->id, 'name' => 'Annex', 'code' => 'ANX', 'address' => 'Industrieweg 12'],
        ['id' => $default->id, 'name' => Warehouse::DEFAULT_NAME, 'code' => null, 'address' => null],
    ]);
});

test('the warehouse list answers a repeat request that already holds the current data with a 304', function () {
    actingAsMobileUser();

    $etag = $this->getJson('/api/v1/warehouses')->assertOk()->headers->get('ETag');

    $this->getJson('/api/v1/warehouses', ['If-None-Match' => $etag])->assertNotModified();
});

test('an unauthenticated caller cannot list warehouses', function () {
    $this->getJson('/api/v1/warehouses')->assertUnauthorized();
});
