<?php

use App\Models\Offer;
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
 * update with edit, and so on), and the pages with no permission of their own
 * (show, the secondary create/edit forms) share their module's. What is still
 * open is listed below, writes and reads apart, and can only shrink: a new
 * ungated route fails the first test, and so does an entry that has since been
 * gated.
 *
 * Writes that no permission covers yet, and why:
 * - modules with no permission at all in the legacy vocabulary (logistics:
 *   feature orders; the offers catalogue; representative packages and
 *   payments; visits): they need new permission names (the offer deals,
 *   notifications and the trips, trucks and drivers screens now have theirs,
 *   and are gated);
 * - self-service routes (the signed-in user's own profile and uploads);
 * - read-only AJAX helpers the product forms post to;
 * - routes whose controller method does not exist;
 * - env_key_update, which refuses every request.
 *
 * @var list<string>
 */
const ADMIN_WRITES_STILL_UNGATED = [
    // No permission for the module yet.

    // Self-service: the signed-in user's own profile and uploads.
    'ProfileController@update',

    // Read-only AJAX helpers the product forms post to.
    'ProductController@fetch',
    'ProductController@get_products_by_subcategory',
    'ProductController@search',
    'ProductController@sku_combination',
    'ProductController@sku_combination_edit',

    // The controller has no such method, so the request errors out.

    // Refuses every request (see LegacyEnvFileProtectionTest).
    'BusinessSettingsController@env_key_update',
];

/**
 * The GET pages that no permission covers yet, for the same reasons.
 *
 * @var list<string>
 */
const ADMIN_READS_STILL_UNGATED = [
    // No permission for the module yet.
    'NotificationController@index',

    // Self-service: the signed-in user's own dashboard, profile and uploads.
    'AdminController@admin_dashboard',
    'ProfileController@index',

    // Read-only AJAX helpers the product forms use.
    'ProductController@getDetails',

];

/**
 * The store admin's routes: every one, only the writes (POST, PUT, PATCH,
 * DELETE), or only the reads.
 *
 * @return list<RoutingRoute>
 */
function storeAdminRoutes(?bool $writes = null): array
{
    return array_values(array_filter(
        Route::getRoutes()->getRoutes(),
        fn (RoutingRoute $route) => str_starts_with($route->uri(), 'temp/admin')
            && ($writes === null || (array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== []) === $writes),
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

test('every store admin route is permission-gated or listed as still open', function (bool $writes, array $listed, int $atLeast) {
    $ungated = collect(storeAdminRoutes($writes))
        ->reject(fn (RoutingRoute $route) => isPermissionGated($route))
        ->map(fn (RoutingRoute $route) => adminActionName($route))
        ->unique()
        ->sort()
        ->values();

    // First: a route with no permission check that is not on the list.
    // Second: a listed route that is gated now, so it comes off the list.
    expect(count(storeAdminRoutes($writes)))->toBeGreaterThan($atLeast)
        ->and($ungated->diff($listed)->values()->all())->toBe([])
        ->and(collect($listed)->diff($ungated)->values()->all())->toBe([]);
})->with([
    'writes' => [true, ADMIN_WRITES_STILL_UNGATED, 150],
    'reads' => [false, ADMIN_READS_STILL_UNGATED, 200],
]);

test('a staff user with no permissions is refused on every permission-gated admin route', function () {
    actingAsStoreUser(createStoreUser('staff'));
    $notRefused = [];
    $checked = 0;

    foreach (storeAdminRoutes() as $route) {
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

    expect($checked)->toBeGreaterThan(340)
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

    $this->post(retiredSettingsWriterUri(), ['types' => ['planted_setting'], 'planted_setting' => 'x'])->assertNotFound();
    $this->post(route('business_settings.update.activation'), ['type' => 'maintenance_mode', 'value' => 1])->assertForbidden();

    $this->assertDatabaseMissing('business_settings', ['type' => 'planted_setting']);
    $this->assertDatabaseMissing('business_settings', ['type' => 'maintenance_mode', 'value' => 1]);
});

test('the offer deal pages and the notification pages ask for the permissions their buttons already name', function () {
    foreach (['view_all_offer_deals', 'add_offer_deal', 'edit_offer_deal', 'delete_offer_deal', 'add_notification', 'delete_notification'] as $name) {
        Permission::findOrCreate($name, 'store');
    }

    $offer = Offer::forceCreate(['title' => 'Permission fixture']);
    $staff = createStoreUser('staff');
    actingAsStoreUser($staff);

    $this->get(route('offer_deals.index'))->assertForbidden();
    $this->put(route('offer_deals.update_status', $offer), ['status' => 1])->assertForbidden();
    $this->delete(route('offer_deals.destroy', $offer))->assertForbidden();

    $staff->givePermissionTo('view_all_offer_deals');

    expect($this->get(route('offer_deals.index'))->getStatusCode())->not->toBe(403);
    $this->delete(route('offer_deals.destroy', $offer))->assertForbidden();

    $staff->givePermissionTo('delete_offer_deal');

    expect($this->delete(route('offer_deals.destroy', $offer))->getStatusCode())->not->toBe(403);
});
