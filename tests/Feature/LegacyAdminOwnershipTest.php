<?php

use Spatie\Permission\Models\Permission;

/**
 * The admin panel's history reports and its trip screens: the
 * report's seller guard was overridden by a request value, the driver actions
 * took any account by id (so a staff member could reset an admin's password),
 * and the trip screens had no permission at all. The staff and driver screens' cases,
 * which saved unvalidated input, half-written, for any role and any member,
 * moved to tests/Feature/Store/Admin with their modules, as did the trip
 * lists' and their status and delete actions' (TripControllerTest).
 */
describe('trip, driver and truck permissions', function () {
    test('a staff member needs the permission of each trip, driver and truck action', function (string $verb, string $uri, string $permission) {
        $staff = createStoreStaff();
        actingAsStoreUser($staff);

        expect($this->call($verb, $uri)->getStatusCode())->toBe(403);

        Permission::findOrCreate($permission, 'store');
        $staff->givePermissionTo($permission);

        expect($this->call($verb, $uri)->getStatusCode())->not->toBe(403);
    })->with([
        ['GET', '/temp/admin/trips/create', 'add_trip'],
        ['POST', '/temp/admin/trips/store', 'add_trip'],
        ['POST', '/temp/admin/trips/update', 'edit_trip'],
    ]);

});
