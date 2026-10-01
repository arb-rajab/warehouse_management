<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

/**
 * The legacy admin panel (/temp/admin) is reachable by every admin, staff and
 * accountant account, and what a staff account may do is decided by spatie
 * permissions. Its controllers gated the *pages* (index, create, edit, ...) and
 * left the endpoints that write (store, update, the status toggles, the bulk
 * deletes) with no check, so a staff member without the permission to open the
 * edit form could still post it: business_settings.update upserted any
 * settings row and toggled maintenance mode, and the settings screens rewrote
 * the server's .env.
 *
 * The write endpoints now share their page's permission (store with create,
 * update with edit, and so on). What is still open is listed below and can only
 * shrink: a new ungated write route fails the first test, and so does an entry
 * here that has since been gated.
 *
 * Writes that no permission covers yet, and why:
 * - modules with no permission at all in the legacy vocabulary (logistics,
 *   offers, representative packages, notifications): they need names from the
 *   production permission list, which is not in the repository;
 * - self-service routes (the signed-in user's own profile and uploads);
 * - read-only AJAX helpers the product forms post to;
 * - routes whose controller method does not exist;
 * - env_key_update, which refuses every request.
 *
 * Reads (GET) are not covered here: a number of admin listings are still open
 * to every staff account.
 *
 * @var list<string>
 */
const ADMIN_WRITES_STILL_UNGATED = [
    // No permission for the module yet.
    'AdminNotificationController@markAsRead',
    'AdminNotificationController@sendFcmNotification',
    'DriverController@store',
    'DriverController@update',
    'FeatureOrderController@store',
    'FeatureOrderTripController@newStore',
    'FeatureOrderTripController@newUpdate',
    'FeatureOrderTripController@store',
    'OfferController@brand_discount_edit',
    'OfferController@product_discount_edit',
    'OfferController@update',
    'OfferController@update_multiple_usage',
    'OfferController@update_repeatable',
    'OfferController@update_status',
    'OffersCatalogController@store',
    'RepresentativePackageController@package_modal',
    'RepresentativePackageController@set_package',
    'RepresentativePackageController@store',
    'RepresentativePackageController@update',
    'TripController@bulk_change_delivery_status',
    'TripController@bulk_trip_delete',
    'TripController@store',
    'TripController@store_from_map',
    'TripController@update',
    'TripController@updateInvoiceNumber',
    'TripController@updateOrderStatusToDelivered',
    'TripController@update_checkpoint_notes',
    'TripController@update_checkpoint_status',
    'TripController@update_trip_notes',
    'TripController@update_trip_status',
    'TruckController@bulk_truck_delete',
    'TruckController@store',
    'TruckController@update',
    'VisitController@bulk_visit_delete',

    // Self-service: the signed-in user's own profile and uploads.
    'AizUploadController@bulk_uploaded_files_delete',
    'AizUploadController@destroy',
    'AizUploadController@file_info',
    'AizUploadController@store',
    'AizUploadController@update',
    'ProfileController@destroy',
    'ProfileController@store',
    'ProfileController@update',

    // Read-only AJAX helpers the product forms post to.
    'ProductController@add_more_choice_option',
    'ProductController@fetch',
    'ProductController@get_products_by_subcategory',
    'ProductController@search',
    'ProductController@sku_combination',
    'ProductController@sku_combination_edit',

    // The controller has no such method, so the request errors out.
    'CurrencyController@updateCurrency',
    'OrderController@bulk_order_status',

    // Refuses every request (see LegacyEnvFileProtectionTest).
    'BusinessSettingsController@env_key_update',
];

/**
 * The store admin's write routes (POST, PUT, PATCH, DELETE).
 *
 * @return list<RoutingRoute>
 */
function storeAdminWriteRoutes(): array
{
    return array_values(array_filter(
        Route::getRoutes()->getRoutes(),
        fn (RoutingRoute $route) => str_starts_with($route->uri(), 'temp/admin')
            && array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== [],
    ));
}

function isPermissionGated(RoutingRoute $route): bool
{
    foreach ($route->gatherMiddleware() as $middleware) {
        if (is_string($middleware) && (str_contains($middleware, 'permission:') || str_contains($middleware, 'RaizerSuperAdmin'))) {
            return true;
        }
    }

    return false;
}

function adminActionName(RoutingRoute $route): string
{
    [$controller, $method] = array_pad(explode('@', $route->getActionName()), 2, '__invoke');

    return class_basename($controller).'@'.$method;
}

test('every store admin write route is permission-gated or listed as still open', function () {
    $ungated = collect(storeAdminWriteRoutes())
        ->reject(fn (RoutingRoute $route) => isPermissionGated($route))
        ->map(fn (RoutingRoute $route) => adminActionName($route))
        ->unique()
        ->sort()
        ->values();

    // First: a write route with no permission check that is not on the list.
    // Second: a listed route that is gated now, so it comes off the list.
    expect(count(storeAdminWriteRoutes()))->toBeGreaterThan(150)
        ->and($ungated->diff(ADMIN_WRITES_STILL_UNGATED)->values()->all())->toBe([])
        ->and(collect(ADMIN_WRITES_STILL_UNGATED)->diff($ungated)->values()->all())->toBe([]);
});

test('a staff user with no permissions is refused on every permission-gated admin write route', function () {
    actingAsStoreUser(createStoreUser('staff'));
    $notRefused = [];
    $checked = 0;

    foreach (storeAdminWriteRoutes() as $route) {
        if (! isPermissionGated($route)) {
            continue;
        }

        $verb = array_values(array_diff($route->methods(), ['HEAD']))[0];
        $status = $this->call($verb, '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri()))->getStatusCode();
        $checked++;

        // Laravel resolves a bound model before the permission middleware, so a
        // route taking one answers 404 for an id that does not exist.
        $takesBoundModel = $route->signatureParameters(['subClass' => Model::class]) !== [];

        if ($status !== 403 && ! ($status === 404 && $takesBoundModel)) {
            $notRefused[] = "$status $verb {$route->uri()}";
        }
    }

    expect($checked)->toBeGreaterThan(140)
        ->and($notRefused)->toBe([]);
});

test('the permission that gates a page also lets its write endpoint through, and another permission does not', function () {
    Permission::findOrCreate('add_brand', 'store');
    Permission::findOrCreate('edit_brand', 'store');

    $staff = createStoreUser('staff');
    $staff->givePermissionTo('edit_brand');
    actingAsStoreUser($staff);

    $this->post('/temp/admin/brands', ['name' => 'Acme'])->assertForbidden();
    $this->assertDatabaseMissing('brands', ['name' => 'Acme']);

    $staff->givePermissionTo('add_brand');

    expect($this->post('/temp/admin/brands', ['name' => 'Acme'])->getStatusCode())->not->toBe(403);
});

test('a staff user cannot change a business setting or take the site down', function () {
    actingAsStoreUser(createStoreUser('staff'));

    $this->post(route('business_settings.update'), ['types' => ['planted_setting'], 'planted_setting' => 'x'])->assertForbidden();
    $this->post(route('business_settings.update.activation'), ['type' => 'maintenance_mode', 'value' => 1])->assertForbidden();

    $this->assertDatabaseMissing('business_settings', ['type' => 'planted_setting']);
    $this->assertDatabaseMissing('business_settings', ['type' => 'maintenance_mode', 'value' => 1]);
});
