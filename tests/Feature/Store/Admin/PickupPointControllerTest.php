<?php

use App\Models\PickupPoint;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function pickupStaffId(): int
{
    return createStaffRow(createStoreUser('staff'))->id;
}

test('pickup points render narrow translated records and paginate', function () {
    actingAsStoreUser(createStoreStaff('pickup_point_setup'));
    $staffId = pickupStaffId();
    $managerName = Staff::query()->with('user')->findOrFail($staffId)->user?->name;
    $lastId = null;
    foreach (range(1, 23) as $number) {
        $lastId = PickupPoint::forceCreate(['staff_id' => $staffId, 'name' => "Point {$number}", 'address' => 'Street', 'phone' => '123', 'pick_up_status' => 1])->id;
    }

    $this->get('/temp/admin/pick_up_points?per_page=10')->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Store/Admin/Setup/PickupPoints/Index')
            ->has('pickupPoints.data', 10)
            ->has('pickupPoints.data.0', fn (Assert $point) => $point
                ->where('id', $lastId)
                ->where('staff_id', $staffId)
                ->where('name', 'Point 23')
                ->where('address', 'Street')
                ->where('phone', '123')
                ->where('active', true)
                ->has('manager', fn (Assert $manager) => $manager
                    ->where('id', $staffId)
                    ->where('name', $managerName)))
            ->where('pickupPoints.meta.total', 23)
    );
    $this->get('/temp/admin/pick_up_points?per_page=7')->assertSessionHasErrors('per_page');
});

test('pickup points support validated translated CRUD and missing ids', function () {
    actingAsStoreUser(createStoreStaff('pickup_point_setup'));
    $staffId = pickupStaffId();
    DB::table('languages')->insert(['name' => 'English', 'code' => 'en']);

    $this->get('/temp/admin/pick_up_points/create')->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Store/Admin/Setup/PickupPoints/Create')->where('staffOptions.0.id', $staffId)
    );
    $this->post('/temp/admin/pick_up_points', [])->assertSessionHasErrors(['name', 'address', 'phone', 'staff_id']);
    $payload = ['name' => 'Depot', 'address' => 'Main street', 'phone' => '123', 'staff_id' => $staffId, 'pick_up_status' => true];
    $this->post('/temp/admin/pick_up_points', $payload)->assertRedirect(route('pick_up_points.index'));
    $point = PickupPoint::query()->where('name', 'Depot')->firstOrFail();
    $this->assertDatabaseHas('pickup_point_translations', ['pickup_point_id' => $point->id, 'lang' => 'en']);

    $this->get("/temp/admin/pick_up_points/{$point->id}/edit?lang=en")->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Store/Admin/Setup/PickupPoints/Edit')->where('translatedName', 'Depot')
    );
    $this->put("/temp/admin/pick_up_points/{$point->id}", [...$payload, 'name' => 'Depot 2', 'lang' => 'en'])->assertRedirect(route('pick_up_points.index'));
    $this->delete("/temp/admin/pick_up_points/{$point->id}")->assertRedirect(route('pick_up_points.index'));
    $this->assertDatabaseMissing('pickup_points', ['id' => $point->id]);

    $this->get('/temp/admin/pick_up_points/999999/edit')->assertNotFound();
    $this->put('/temp/admin/pick_up_points/999999', [...$payload, 'lang' => 'en'])->assertNotFound();
    $this->delete('/temp/admin/pick_up_points/999999')->assertNotFound();
});

test('pickup point routes refuse callers without permission and writes change nothing', function (string $caller) {
    $staffId = pickupStaffId();
    $point = PickupPoint::forceCreate(['staff_id' => $staffId, 'name' => 'Depot', 'address' => 'Street', 'phone' => '123']);
    actingAsRefusedStoreCaller($caller);

    $payload = ['name' => 'Nope', 'address' => 'Elsewhere', 'phone' => '456', 'staff_id' => $staffId, 'pick_up_status' => true, 'lang' => 'en'];

    assertStoreCallerRefused($this->get('/temp/admin/pick_up_points'), $caller);
    assertStoreCallerRefused($this->get('/temp/admin/pick_up_points/create'), $caller);
    assertStoreCallerRefused($this->get("/temp/admin/pick_up_points/{$point->id}/edit"), $caller);
    assertStoreCallerRefused($this->post('/temp/admin/pick_up_points', $payload), $caller);
    assertStoreCallerRefused($this->put("/temp/admin/pick_up_points/{$point->id}", $payload), $caller);
    assertStoreCallerRefused($this->delete("/temp/admin/pick_up_points/{$point->id}"), $caller);
    $this->assertDatabaseHas('pickup_points', ['id' => $point->id, 'name' => 'Depot', 'address' => 'Street']);
    $this->assertDatabaseMissing('pickup_points', ['name' => 'Nope']);
})->with('refused store admin callers');
