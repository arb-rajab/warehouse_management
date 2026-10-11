<?php

use App\Http\Controllers\ReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * The admin panel's history reports and its trip screens: the
 * report's seller guard was overridden by a request value, the driver actions
 * took any account by id (so a staff member could reset an admin's password),
 * and the trip screens had no permission at all. The staff and driver screens' cases,
 * which saved unvalidated input, half-written, for any role and any member,
 * moved to tests/Feature/Store/Admin with their modules.
 */
describe('commission and wallet reports', function () {
    test('a seller who is also sent a seller_id still gets only their own commission rows', function () {
        $seller = createStoreUser('seller');
        $other = createStoreUser('seller');
        actingAsStoreUser($seller);
        Auth::shouldUse('store');

        DB::enableQueryLog();
        try {
            (new ReportController)->commission_history(Request::create('/', 'GET', ['seller_id' => $other->id]));
        } catch (Throwable) {
            // The seller branch's view does not exist; only the query matters.
        }
        $bindings = collect(DB::getQueryLog())->pluck('bindings')->flatten()->all();

        expect($bindings)->toContain($seller->id)->not->toContain($other->id);
    });

    test('a malformed or reversed date range is refused on both reports', function (string $uri, string $range) {
        actingAsStoreUser(createStoreAdmin());

        $this->get("{$uri}?date_range=".urlencode($range))->assertSessionHasErrors('date_range');
    })->with([
        'commission, text' => ['/temp/admin/commission-log', 'yesterday'],
        'commission, reversed' => ['/temp/admin/commission-log', '2026-02-01 / 2026-01-01'],
        'commission, impossible day' => ['/temp/admin/commission-log', '2026-02-31 / 2026-03-01'],
        'wallet, text' => ['/temp/admin/wallet-history', 'yesterday'],
        'wallet, reversed' => ['/temp/admin/wallet-history', '2026-02-01 / 2026-01-01'],
    ]);

    test('a valid date range and account filter are accepted', function () {
        actingAsStoreUser(createStoreAdmin());

        $this->get('/temp/admin/commission-log?date_range='.urlencode('2026-01-01 / 2026-02-01').'&seller_id=1')->assertSessionDoesntHaveErrors();
        $this->get('/temp/admin/commission-log?seller_id=abc')->assertSessionHasErrors('seller_id');
    });

    test('a staff member without the report permission is refused', function () {
        actingAsStoreUser(createStoreUser('staff'));

        $this->get('/temp/admin/commission-log')->assertForbidden();
    });
});

describe('trip, driver and truck permissions', function () {
    test('a staff member needs the permission of each trip, driver and truck action', function (string $verb, string $uri, string $permission) {
        $staff = createStoreStaff();
        actingAsStoreUser($staff);

        expect($this->call($verb, $uri)->getStatusCode())->toBe(403);

        Permission::findOrCreate($permission, 'store');
        $staff->givePermissionTo($permission);

        expect($this->call($verb, $uri)->getStatusCode())->not->toBe(403);
    })->with([
        ['GET', '/temp/admin/trips/index', 'view_all_trips'],
        ['GET', '/temp/admin/trips/create', 'add_trip'],
        ['POST', '/temp/admin/trips/store', 'add_trip'],
        ['POST', '/temp/admin/trips/update', 'edit_trip'],
        ['POST', '/temp/admin/trips/update-trip-status', 'edit_trip'],
        ['POST', '/temp/admin/trips/delete/1', 'delete_trip'],
        ['POST', '/temp/admin/trips/bulk-trips-delete', 'delete_trip'],
    ]);

});
