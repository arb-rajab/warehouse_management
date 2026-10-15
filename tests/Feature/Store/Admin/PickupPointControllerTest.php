<?php

use App\Models\StoreUser;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->refs = createStoreReferenceData();
});

test('the PickupPoints list can be rendered', function () {
    actingAsStoreUser(createStoreStaff('pickup_point_setup'));
    $this->get('/temp/admin/pick_up_points')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Setup/PickupPoints/Index'));
});

beforeEach(function () {
    DB::table((new StoreUser)->getTable())->where('id', DB::table('staff')->where('id', $this->refs['staff'])->value('user_id'))->update(['name' => 'Manager']);
});

test('the PickupPoints list exposes exactly the narrow record', function () {
    actingAsStoreUser(createStoreStaff('pickup_point_setup'));
    $this->get('/temp/admin/pick_up_points')->assertOk()->assertInertia(fn (Assert $page) => $page->has('pickupPoints.data', 1)->has('pickupPoints.data.0', fn (Assert $row) => $row
        ->where('id', $this->refs['pickup'])
        ->where('name', 'Warehouse')
        ->where('address', 'Main Street')
        ->where('phone', '0612345678')
        ->where('staff_id', $this->refs['staff'])
        ->where('staff', ['id' => $this->refs['staff'], 'name' => 'Manager'])
        ->where('pick_up_status', true)
    ));
});

test('the PickupPoints list pages without overlaps and validates page size', function () {
    actingAsStoreUser(createStoreStaff('pickup_point_setup'));
    $row = (array) DB::table('pickup_points')->where('id', $this->refs['pickup'])->first();
    unset($row['id']);
    foreach (range(1, 22) as $number) {
        $row['name'] = 'Record '.$number;
        DB::table('pickup_points')->insert($row);
    } assertPagesWithoutOverlap(fn (int $page) => $this->get('/temp/admin/pick_up_points?page='.$page), fn ($response) => collect($response->inertiaProps('pickupPoints.data'))->pluck('id')->all(), 23, 20);
    $this->get('/temp/admin/pick_up_points?per_page=10')->assertOk()->assertInertia(fn (Assert $page) => $page->has('pickupPoints.data', 10)->where('pickupPoints.meta.per_page', 10));
    $this->getJson('/temp/admin/pick_up_points?per_page=13')->assertUnprocessable()->assertJsonValidationErrors('per_page');
});

test('the PickupPoints list refuses callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get('/temp/admin/pick_up_points'), $caller);
})->with('refused store admin callers');

test('the PickupPoints name filter excludes unrelated data and survives pagination', function () {
    actingAsStoreUser(createStoreStaff('pickup_point_setup'));
    $row = (array) DB::table('pickup_points')->where('id', $this->refs['pickup'])->first();
    unset($row['id']);
    $row['name'] = 'Unrelated';
    DB::table('pickup_points')->insert($row);
    $this->get('/temp/admin/pick_up_points?search='.urlencode('Warehouse'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('pickupPoints.data', 1)->where('pickupPoints.data.0.id', $this->refs['pickup'])->where('filters.search', 'Warehouse'));
});

test('the PickupPoints create screen can be rendered', function () {
    actingAsStoreUser(createStoreStaff('pickup_point_setup'));
    $this->get('/temp/admin/pick_up_points/create')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Setup/PickupPoints/Create'));
});

test('the PickupPoints create screen refuses callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get('/temp/admin/pick_up_points/create'), $caller);
})->with('refused store admin callers');

test('the PickupPoints edit screen can be rendered', function () {
    actingAsStoreUser(createStoreStaff('pickup_point_setup'));
    $this->get('/temp/admin/pick_up_points/'.$this->refs['pickup'].'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Setup/PickupPoints/Edit'));
});

test('the PickupPoints edit screen refuses callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get('/temp/admin/pick_up_points/'.$this->refs['pickup'].'/edit'), $caller);
})->with('refused store admin callers');

test('the PickupPoints edit screen returns 404 for a missing id', function () {
    actingAsStoreUser(createStoreStaff('pickup_point_setup'));
    $this->get('/temp/admin/pick_up_points/999999/edit')->assertNotFound();
});

test('the PickupPoints store saves validated values', function () {
    actingAsStoreUser(createStoreStaff('pickup_point_setup'));
    $this->post('/temp/admin/pick_up_points', ['name' => 'Dock', 'address' => 'Dock Street', 'phone' => '0612222222', 'staff_id' => $this->refs['staff'], 'pick_up_status' => 0])->assertRedirect(route('pick_up_points.index'));
    $this->assertDatabaseHas('pickup_points', ['name' => 'Dock']);
});

test('the PickupPoints store validates fields before writing', function () {
    actingAsStoreUser(createStoreStaff('pickup_point_setup'));
    $this->postJson('/temp/admin/pick_up_points', [])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->assertDatabaseCount('pickup_points', 1);
});

test('the PickupPoints store refuses writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post('/temp/admin/pick_up_points', ['name' => 'Dock', 'address' => 'Dock Street', 'phone' => '0612222222', 'staff_id' => $this->refs['staff'], 'pick_up_status' => 0]), $caller);
    $this->assertDatabaseCount('pickup_points', 1);
})->with('refused store admin callers');

test('the PickupPoints update saves validated values', function () {
    actingAsStoreUser(createStoreStaff('pickup_point_setup'));
    $this->put('/temp/admin/pick_up_points/'.$this->refs['pickup'], ['name' => 'Dock', 'address' => 'Dock Street', 'phone' => '0612222222', 'staff_id' => $this->refs['staff'], 'pick_up_status' => 0] + ['lang' => 'en'])->assertRedirect(route('pick_up_points.index'));
    $this->assertDatabaseHas('pickup_points', ['id' => $this->refs['pickup'], 'name' => 'Dock']);
});

test('the PickupPoints update validates fields without changing data', function () {
    actingAsStoreUser(createStoreStaff('pickup_point_setup'));
    $this->putJson('/temp/admin/pick_up_points/'.$this->refs['pickup'], [])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->assertDatabaseHas('pickup_points', ['id' => $this->refs['pickup'], 'name' => 'Warehouse']);
});

test('the PickupPoints update refuses writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->put('/temp/admin/pick_up_points/'.$this->refs['pickup'], ['name' => 'Dock', 'address' => 'Dock Street', 'phone' => '0612222222', 'staff_id' => $this->refs['staff'], 'pick_up_status' => 0] + ['lang' => 'en']), $caller);
    $this->assertDatabaseHas('pickup_points', ['id' => $this->refs['pickup'], 'name' => 'Warehouse']);
})->with('refused store admin callers');

test('the PickupPoints update returns 404 for a missing id', function () {
    actingAsStoreUser(createStoreStaff('pickup_point_setup'));
    $this->put('/temp/admin/pick_up_points/999999', ['name' => 'Dock', 'address' => 'Dock Street', 'phone' => '0612222222', 'staff_id' => $this->refs['staff'], 'pick_up_status' => 0] + ['lang' => 'en'])->assertNotFound();
});

test('the PickupPoints delete keeps the query and removes the record', function () {
    actingAsStoreUser(createStoreStaff('pickup_point_setup'));
    $this->delete('/temp/admin/pick_up_points/'.$this->refs['pickup'].'?page=2&per_page=10')->assertRedirect(route('pick_up_points.index', ['page' => 2, 'per_page' => 10]));
    $this->assertDatabaseMissing('pickup_points', ['id' => $this->refs['pickup']]);
});

test('the PickupPoints delete refuses writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->delete('/temp/admin/pick_up_points/'.$this->refs['pickup']), $caller);
    $this->assertDatabaseHas('pickup_points', ['id' => $this->refs['pickup']]);
})->with('refused store admin callers');

test('the PickupPoints delete returns 404 for a missing id', function () {
    actingAsStoreUser(createStoreStaff('pickup_point_setup'));
    $this->delete('/temp/admin/pick_up_points/999999')->assertNotFound();
});

test('the PickupPoints routes do not accept a WMS identity', function () {
    actingAsAdmin();
    $this->get('/temp/admin/pick_up_points')->assertRedirect(route('shop.login'));
    $this->post('/temp/admin/pick_up_points', ['name' => 'Dock', 'address' => 'Dock Street', 'phone' => '0612222222', 'staff_id' => $this->refs['staff'], 'pick_up_status' => 0])->assertRedirect(route('shop.login'));
    $this->assertDatabaseCount('pickup_points', 1);
});

test('pickup translations preserve base content and deletion removes translations', function () {
    actingAsStoreUser(createStoreStaff('pickup_point_setup'));
    DB::table('languages')->insert(['code' => 'ar', 'name' => 'Arabic', 'rtl' => 1]);
    $this->put('/temp/admin/pick_up_points/'.$this->refs['pickup'], ['name' => 'المستودع', 'address' => 'الشارع الرئيسي', 'phone' => '0612345678', 'staff_id' => $this->refs['staff'], 'pick_up_status' => 1, 'lang' => 'ar'])->assertRedirect();
    $this->assertDatabaseHas('pickup_points', ['id' => $this->refs['pickup'], 'name' => 'Warehouse', 'address' => 'Main Street']);
    $this->get('/temp/admin/pick_up_points/'.$this->refs['pickup'].'/edit?lang=ar')->assertOk()->assertInertia(fn (Assert $page) => $page->where('name', 'المستودع')->where('address', 'الشارع الرئيسي'));
    $this->delete('/temp/admin/pick_up_points/'.$this->refs['pickup'])->assertRedirect();
    $this->assertDatabaseMissing('pickup_point_translations', ['pickup_point_id' => $this->refs['pickup']]);
});
test('pickup form rejects a missing manager and overlong translated name or phone', function () {
    actingAsStoreUser(createStoreStaff('pickup_point_setup'));
    $this->postJson('/temp/admin/pick_up_points', ['name' => str_repeat('x', 51), 'address' => 'Address', 'phone' => str_repeat('1', 16), 'staff_id' => 999999, 'pick_up_status' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors(['name', 'phone', 'staff_id', 'pick_up_status']);
    $this->assertDatabaseCount('pickup_points', 1);
});
