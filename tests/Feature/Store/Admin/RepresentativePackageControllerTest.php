<?php

use App\Models\RepresentativePackage;
use App\Models\Role;
use App\Models\StoreUser;
use Inertia\Testing\AssertableInertia as Assert;

function representativePackage(array $attributes = []): RepresentativePackage
{
    return RepresentativePackage::create(['name' => 'Gold', 'percentage' => 12.5, ...$attributes]);
}

function packageRepresentative(array $attributes = []): StoreUser
{
    $user = createStoreUser('customer');
    $user->forceFill(['is_rep' => true, ...$attributes])->save();

    return $user;
}

test('the representative package index renders', function () {
    actingAsStoreUser(createStoreStaff('view_representative_packages'));
    $this->get('/temp/admin/representatives/packages/index')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Representatives/Packages/Index'));
});

test('the representative package index returns a narrow complete record and paginates', function () {
    actingAsStoreUser(createStoreStaff('view_representative_packages'));
    $package = representativePackage();
    foreach (range(1, 21) as $i) {
        representativePackage(['name' => 'Package '.$i]);
    }
    $this->get('/temp/admin/representatives/packages/index')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('packages.data', 20)->where('packages.meta.total', 22)
        ->where('filters', ['per_page' => 20])
        ->has('packages.data.0', fn (Assert $item) => $item->where('id', $package->id)->where('name', 'Gold')->where('percentage', '12.5')));
    $this->get('/temp/admin/representatives/packages/index?per_page=10')->assertOk()->assertInertia(fn (Assert $page) => $page->has('packages.data', 10));
    $this->getJson('/temp/admin/representatives/packages/index?per_page=999')->assertUnprocessable()->assertJsonValidationErrors('per_page');
});

test('the package creation page renders', function () {
    actingAsStoreUser(createStoreStaff('add_representative_package'));
    $this->get('/temp/admin/representatives/packages/create')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Representatives/Packages/Create'));
});

test('a package can be created with percentage boundaries', function ($percentage) {
    actingAsStoreUser(createStoreStaff('add_representative_package'));
    $this->post('/temp/admin/representatives/packages/store', ['name' => 'Boundary', 'percentage' => $percentage])->assertRedirect(route('rep.packages.index'));
    $this->assertDatabaseHas('representative_packages', ['name' => 'Boundary', 'percentage' => $percentage]);
})->with([0, 100, 12.5]);

test('package creation validates name and percentage without writes', function (array $form, string $error) {
    actingAsStoreUser(createStoreStaff('add_representative_package'));
    $this->postJson('/temp/admin/representatives/packages/store', $form)->assertUnprocessable()->assertJsonValidationErrors($error);
    $this->assertDatabaseCount('representative_packages', 0);
})->with([
    'missing name' => [['percentage' => 10], 'name'],
    'long name' => [['name' => str_repeat('a', 51), 'percentage' => 10], 'name'],
    'negative percentage' => [['name' => 'Bad', 'percentage' => -1], 'percentage'],
    'over percentage' => [['name' => 'Bad', 'percentage' => 101], 'percentage'],
    'not numeric' => [['name' => 'Bad', 'percentage' => 'free'], 'percentage'],
]);

test('the package edit page renders', function () {
    actingAsStoreUser(createStoreStaff('edit_representative_package'));
    $package = representativePackage();
    $this->get('/temp/admin/representatives/packages/'.$package->id.'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Representatives/Packages/Edit')->has('package', fn (Assert $item) => $item->where('id', $package->id)->where('name', 'Gold')->where('percentage', '12.5')));
});

test('updating a package updates only its representatives discounts', function () {
    actingAsStoreUser(createStoreStaff('edit_representative_package'));
    $package = representativePackage();
    $rep = packageRepresentative(['rep_package_id' => $package->id, 'rep_discount_percentage' => 12.5]);
    $other = packageRepresentative(['rep_package_id' => representativePackage()->id, 'rep_discount_percentage' => 5]);
    $this->put('/temp/admin/representatives/packages/'.$package->id, ['name' => 'Platinum', 'percentage' => 25])->assertRedirect(route('rep.packages.index'));
    expect($rep->fresh()->rep_discount_percentage)->toEqual(25)->and($other->fresh()->rep_discount_percentage)->toEqual(5);
    $this->assertDatabaseHas('representative_packages', ['id' => $package->id, 'name' => 'Platinum', 'percentage' => 25]);
});

test('invalid package update changes neither package nor assigned discount', function () {
    actingAsStoreUser(createStoreStaff('edit_representative_package'));
    $package = representativePackage();
    $rep = packageRepresentative(['rep_package_id' => $package->id, 'rep_discount_percentage' => 12.5]);
    $this->putJson('/temp/admin/representatives/packages/'.$package->id, ['name' => '', 'percentage' => 101])->assertUnprocessable()->assertJsonValidationErrors(['name', 'percentage']);
    expect($package->fresh()->name)->toBe('Gold')->and($rep->fresh()->rep_discount_percentage)->toEqual(12.5);
});

test('deleting a package clears assigned discounts and preserves unrelated assignments and query', function () {
    actingAsStoreUser(createStoreStaff('delete_representative_package'));
    $package = representativePackage();
    $rep = packageRepresentative(['rep_package_id' => $package->id, 'rep_discount_percentage' => 12.5]);
    $otherPackage = representativePackage();
    $other = packageRepresentative(['rep_package_id' => $otherPackage->id, 'rep_discount_percentage' => 5]);
    $this->delete('/temp/admin/representatives/packages/delete/'.$package->id.'?page=2&per_page=10')->assertRedirect(route('rep.packages.index', ['page' => 2, 'per_page' => 10]));
    expect($rep->fresh()->rep_package_id)->toBeNull()->and($rep->fresh()->rep_discount_percentage)->toEqual(0)->and($other->fresh()->rep_package_id)->toBe($otherPackage->id);
    $this->assertDatabaseMissing('representative_packages', ['id' => $package->id]);
});

test('a package can be assigned only to an active representative and keeps percentage synchronized', function () {
    actingAsStoreUser(createStoreStaff('assign_representative_package'));
    $package = representativePackage();
    $rep = packageRepresentative();
    $noise = packageRepresentative();
    $this->post('/temp/admin/representatives/packages/set_package', ['user_id' => $rep->id, 'package_id' => $package->id])->assertRedirect(route('representatives.index'));
    expect($rep->fresh()->rep_package_id)->toBe($package->id)->and($rep->fresh()->rep_discount_percentage)->toEqual(12.5)->and($noise->fresh()->rep_package_id)->toBeNull();
});

test('assignment rejects customers staff deleted representatives and missing references', function (string $kind) {
    actingAsStoreUser(createStoreStaff('assign_representative_package'));
    $package = representativePackage();
    $user = match ($kind) {
        'customer' => createStoreUser('customer'),
        'staff' => createStoreUser('staff'),
        'deleted' => packageRepresentative(['is_delete' => 1]),
        default => packageRepresentative(),
    };
    $this->postJson('/temp/admin/representatives/packages/set_package', ['user_id' => $kind === 'missing user' ? 999999 : $user->id, 'package_id' => $kind === 'missing package' ? 999999 : $package->id])
        ->assertUnprocessable()->assertJsonValidationErrors($kind === 'missing package' ? 'package_id' : 'user_id');
    expect($user->fresh()->rep_package_id)->toBeNull();
})->with(['customer', 'staff', 'deleted', 'missing user', 'missing package']);

test('missing packages return 404 for edit update and destroy', function () {
    actingAsStoreUser(createStoreStaff('edit_representative_package', 'delete_representative_package'));
    $this->get('/temp/admin/representatives/packages/999999/edit')->assertNotFound();
    $this->put('/temp/admin/representatives/packages/999999', ['name' => 'Gone', 'percentage' => 2])->assertNotFound();
    $this->delete('/temp/admin/representatives/packages/delete/999999')->assertNotFound();
});

foreach (['index' => '/temp/admin/representatives/packages/index', 'create' => '/temp/admin/representatives/packages/create', 'edit' => null] as $action => $url) {
    test('package '.$action.' refuses unauthorized callers', function (string $caller) use ($url) {
        $package = representativePackage();
        actingAsRefusedStoreCaller($caller);
        assertStoreCallerRefused($this->get($url ?? '/temp/admin/representatives/packages/'.$package->id.'/edit'), $caller);
    })->with('refused store admin callers');
}

test('package store refuses unauthorized callers without writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post('/temp/admin/representatives/packages/store', ['name' => 'Refused', 'percentage' => 10]), $caller);
    $this->assertDatabaseCount('representative_packages', 0);
})->with('refused store admin callers');

test('package update refuses unauthorized callers without writes', function (string $caller) {
    $package = representativePackage();
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->put('/temp/admin/representatives/packages/'.$package->id, ['name' => 'Refused', 'percentage' => 10]), $caller);
    expect($package->fresh()->name)->toBe('Gold')->and($package->fresh()->percentage)->toEqual(12.5);
})->with('refused store admin callers');

test('package destroy refuses unauthorized callers without writes', function (string $caller) {
    $package = representativePackage();
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->delete('/temp/admin/representatives/packages/delete/'.$package->id), $caller);
    $this->assertDatabaseHas('representative_packages', ['id' => $package->id]);
})->with('refused store admin callers');

test('package assignment refuses unauthorized callers without writes', function (string $caller) {
    $package = representativePackage();
    $rep = packageRepresentative();
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post('/temp/admin/representatives/packages/set_package', ['user_id' => $rep->id, 'package_id' => $package->id]), $caller);
    expect($rep->fresh()->rep_package_id)->toBeNull();
})->with('refused store admin callers');

test('read package permission does not allow writes', function () {
    actingAsStoreUser(createStoreStaff('view_representative_packages'));
    $package = representativePackage();
    $rep = packageRepresentative();
    $this->post('/temp/admin/representatives/packages/store', ['name' => 'No', 'percentage' => 1])->assertForbidden();
    $this->put('/temp/admin/representatives/packages/'.$package->id, ['name' => 'No', 'percentage' => 1])->assertForbidden();
    $this->delete('/temp/admin/representatives/packages/delete/'.$package->id)->assertForbidden();
    $this->post('/temp/admin/representatives/packages/set_package', ['user_id' => $rep->id, 'package_id' => $package->id])->assertForbidden();
    expect($package->fresh()->name)->toBe('Gold')->and($rep->fresh()->rep_package_id)->toBeNull();
    $this->assertDatabaseCount('representative_packages', 1);
});

test('package assignment refuses customer typed Super Admin representatives without writes', function () {
    actingAsStoreUser(createStoreStaff('assign_representative_package'));
    $rep = packageRepresentative();
    $rep->assignRole(Role::findOrCreate('Super Admin', 'store'));
    $package = representativePackage();
    $this->post('/temp/admin/representatives/packages/set_package', ['user_id' => $rep->id, 'package_id' => $package->id])->assertForbidden();
    expect($rep->fresh()->rep_package_id)->toBeNull()->and($rep->fresh()->rep_discount_percentage)->toEqual(0);
});
