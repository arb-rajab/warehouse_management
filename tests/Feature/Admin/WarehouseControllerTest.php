<?php

use App\Models\Row;
use App\Models\Warehouse;
use Inertia\Testing\AssertableInertia as Assert;

test('the warehouses screen can be rendered', function () {
    actingAsAdmin();

    $this->get('/admin/warehouses')->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Warehouses/Index')
    );
});

test('the warehouse list shows every property the table renders, with the row count', function () {
    actingAsAdmin();
    $warehouse = Warehouse::factory()->create(['name' => 'Annex', 'code' => 'ANX', 'address' => 'Industrieweg 12']);
    Row::factory()->for($warehouse)->count(2)->create(['cells_count' => 1, 'flats_count' => 1]);

    $response = $this->get('/admin/warehouses');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Warehouses/Index')
            ->has('warehouses.data', 2)
            // Ordered by name: "Annex" before "Default".
            ->has('warehouses.data.0', fn (Assert $item) => $item
                ->where('id', $warehouse->id)
                ->where('name', 'Annex')
                ->where('code', 'ANX')
                ->where('address', 'Industrieweg 12')
                ->where('rows_count', 2)
            )
            ->where('warehouses.data.1.name', Warehouse::DEFAULT_NAME)
            ->where('warehouses.data.1.rows_count', 0)
            ->where('filters', ['per_page' => 20])
    );
});

test('the warehouse list paginates instead of returning everything at once', function () {
    actingAsAdmin();
    Warehouse::factory()->count(25)->create();

    $this->get('/admin/warehouses')->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('warehouses.data', 20)->where('warehouses.meta.total', 26)
    );
});

test('a mobile app user cannot view the warehouse list', function () {
    actingAsMobilePanelUser();

    $this->get('/admin/warehouses')->assertForbidden();
});

test('an unauthenticated caller cannot view the warehouse list', function () {
    $this->get('/admin/warehouses')->assertRedirect(route('login'));
});

test('the warehouse search returns matching warehouses as id/name options, paginated', function () {
    actingAsAdmin();
    $match = Warehouse::factory()->create(['name' => 'Rotterdam Port', 'code' => 'RTM']);
    Warehouse::factory()->create(['name' => 'Utrecht', 'code' => 'UTR']);

    $response = $this->getJson('/admin/warehouses/search?q=rott');

    $response->assertOk();
    expect($response->json('data'))->toEqual([['id' => $match->id, 'name' => 'Rotterdam Port']])
        ->and($response->json('meta.per_page'))->toBe(20);
});

test('the warehouse search lists every warehouse by name without a term', function () {
    actingAsAdmin();
    Warehouse::factory()->create(['name' => 'Annex']);

    $response = $this->getJson('/admin/warehouses/search');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('name')->all())->toBe(['Annex', Warehouse::DEFAULT_NAME]);
});

test('a mobile app user cannot search warehouses', function () {
    actingAsMobilePanelUser();

    $this->getJson('/admin/warehouses/search')->assertForbidden();
});

test('an unauthenticated caller cannot search warehouses', function () {
    $this->get('/admin/warehouses/search')->assertRedirect(route('login'));
});

test('the create warehouse screen can be rendered', function () {
    actingAsAdmin();

    $this->get('/admin/warehouses/create')->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Warehouses/Create')
    );
});

test('a mobile app user cannot view the create warehouse screen', function () {
    actingAsMobilePanelUser();

    $this->get('/admin/warehouses/create')->assertForbidden();
});

test('an unauthenticated caller cannot view the create warehouse screen', function () {
    $this->get('/admin/warehouses/create')->assertRedirect(route('login'));
});

test('an admin can create a warehouse', function () {
    actingAsAdmin();

    $response = $this->post('/admin/warehouses', ['name' => 'Annex', 'code' => 'ANX', 'address' => 'Industrieweg 12']);

    $response->assertRedirect(route('admin.warehouses.index'));
    $response->assertInertiaFlash('success', 'Warehouse has been created successfully');
    $warehouse = Warehouse::query()->where('name', 'Annex')->sole();
    expect($warehouse->code)->toBe('ANX')
        ->and($warehouse->address)->toBe('Industrieweg 12');
});

test('a warehouse needs only a name', function () {
    actingAsAdmin();

    $this->post('/admin/warehouses', ['name' => 'Annex'])->assertRedirect(route('admin.warehouses.index'));

    expect(Warehouse::query()->where('name', 'Annex')->sole()->code)->toBeNull();
});

test('creating a warehouse without a name is rejected and nothing changes', function () {
    actingAsAdmin();
    $before = Warehouse::count();

    $response = $this->post('/admin/warehouses', ['code' => 'ANX']);

    $response->assertSessionHasErrors('name');
    $response->assertInertiaFlashMissing('success');
    expect(Warehouse::count())->toBe($before);
});

test('creating a warehouse with a taken name or code is rejected and nothing changes', function () {
    actingAsAdmin();
    Warehouse::factory()->create(['name' => 'Annex', 'code' => 'ANX']);
    $before = Warehouse::count();

    $this->post('/admin/warehouses', ['name' => 'Annex'])->assertSessionHasErrors('name');
    $this->post('/admin/warehouses', ['name' => 'Other', 'code' => 'ANX'])->assertSessionHasErrors('code');

    expect(Warehouse::count())->toBe($before);
});

test('a mobile app user cannot create a warehouse and nothing changes', function () {
    actingAsMobilePanelUser();
    $before = Warehouse::count();

    $this->post('/admin/warehouses', ['name' => 'Annex'])->assertForbidden();

    expect(Warehouse::count())->toBe($before);
});

test('an unauthenticated caller cannot create a warehouse and nothing changes', function () {
    $before = Warehouse::count();

    $this->post('/admin/warehouses', ['name' => 'Annex'])->assertRedirect(route('login'));

    expect(Warehouse::count())->toBe($before);
});

test('the edit warehouse screen shows every property of the warehouse', function () {
    actingAsAdmin();
    $warehouse = Warehouse::factory()->create(['name' => 'Annex', 'code' => 'ANX', 'address' => 'Industrieweg 12']);

    $this->get("/admin/warehouses/{$warehouse->id}/edit")->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Warehouses/Edit')
            ->has('warehouse', fn (Assert $item) => $item
                ->where('id', $warehouse->id)
                ->where('name', 'Annex')
                ->where('code', 'ANX')
                ->where('address', 'Industrieweg 12')
                ->where('rows_count', 0)
            )
    );
});

test('viewing the edit page for a non-existent warehouse returns a 404', function () {
    actingAsAdmin();

    $this->get('/admin/warehouses/999999/edit')->assertNotFound();
});

test('a mobile app user cannot view the edit warehouse screen', function () {
    actingAsMobilePanelUser();
    $warehouse = Warehouse::factory()->create();

    $this->get("/admin/warehouses/{$warehouse->id}/edit")->assertForbidden();
});

test('an unauthenticated caller cannot view the edit warehouse screen', function () {
    $warehouse = Warehouse::factory()->create();

    $this->get("/admin/warehouses/{$warehouse->id}/edit")->assertRedirect(route('login'));
});

test('an admin can update a warehouse, keeping its own name', function () {
    actingAsAdmin();
    $warehouse = Warehouse::factory()->create(['name' => 'Annex', 'code' => 'ANX']);

    $response = $this->put("/admin/warehouses/{$warehouse->id}", ['name' => 'Annex', 'code' => 'ANX-2', 'address' => 'Havenweg 1']);

    $response->assertRedirect(route('admin.warehouses.index'));
    $response->assertInertiaFlash('success', 'Warehouse has been updated successfully');
    expect($warehouse->fresh()->only(['name', 'code', 'address']))->toBe(['name' => 'Annex', 'code' => 'ANX-2', 'address' => 'Havenweg 1']);
});

test('updating a warehouse to another warehouse\'s name is rejected and nothing changes', function () {
    actingAsAdmin();
    Warehouse::factory()->create(['name' => 'Taken']);
    $warehouse = Warehouse::factory()->create(['name' => 'Annex']);

    $this->put("/admin/warehouses/{$warehouse->id}", ['name' => 'Taken'])->assertSessionHasErrors('name');

    expect($warehouse->fresh()->name)->toBe('Annex');
});

test('updating a non-existent warehouse returns a 404', function () {
    actingAsAdmin();

    $this->put('/admin/warehouses/999999', ['name' => 'Annex'])->assertNotFound();
});

test('a mobile app user cannot update a warehouse and nothing changes', function () {
    actingAsMobilePanelUser();
    $warehouse = Warehouse::factory()->create(['name' => 'Annex']);

    $this->put("/admin/warehouses/{$warehouse->id}", ['name' => 'Renamed'])->assertForbidden();

    expect($warehouse->fresh()->name)->toBe('Annex');
});

test('an unauthenticated caller cannot update a warehouse and nothing changes', function () {
    $warehouse = Warehouse::factory()->create(['name' => 'Annex']);

    $this->put("/admin/warehouses/{$warehouse->id}", ['name' => 'Renamed'])->assertRedirect(route('login'));

    expect($warehouse->fresh()->name)->toBe('Annex');
});

test('an admin can delete a warehouse without rows, keeping the list\'s query string', function () {
    actingAsAdmin();
    $warehouse = Warehouse::factory()->create();

    $response = $this->delete("/admin/warehouses/{$warehouse->id}?page=2");

    $response->assertRedirect(route('admin.warehouses.index', ['page' => 2]));
    $response->assertInertiaFlash('success', 'Warehouse has been deleted successfully');
    expect(Warehouse::query()->whereKey($warehouse->id)->exists())->toBeFalse();
});

test('a warehouse that still has rows is not deleted', function () {
    actingAsAdmin();
    $warehouse = Warehouse::factory()->create();
    Row::factory()->for($warehouse)->create(['cells_count' => 1, 'flats_count' => 1]);

    $response = $this->delete("/admin/warehouses/{$warehouse->id}");

    $response->assertRedirect(route('admin.warehouses.index'));
    $response->assertSessionHasErrors(['warehouse' => 'Cannot delete a warehouse that still has rows.']);
    $response->assertInertiaFlashMissing('success');
    expect(Warehouse::query()->whereKey($warehouse->id)->exists())->toBeTrue()
        ->and($warehouse->rows()->count())->toBe(1);
});

test('deleting a non-existent warehouse returns a 404', function () {
    actingAsAdmin();

    $this->delete('/admin/warehouses/999999')->assertNotFound();
});

test('a mobile app user cannot delete a warehouse and nothing changes', function () {
    actingAsMobilePanelUser();
    $warehouse = Warehouse::factory()->create();

    $this->delete("/admin/warehouses/{$warehouse->id}")->assertForbidden();

    expect(Warehouse::query()->whereKey($warehouse->id)->exists())->toBeTrue();
});

test('an unauthenticated caller cannot delete a warehouse and nothing changes', function () {
    $warehouse = Warehouse::factory()->create();

    $this->delete("/admin/warehouses/{$warehouse->id}")->assertRedirect(route('login'));

    expect(Warehouse::query()->whereKey($warehouse->id)->exists())->toBeTrue();
});
