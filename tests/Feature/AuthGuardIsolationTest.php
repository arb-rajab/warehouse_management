<?php

use App\Http\Middleware\RestrictToAllowedIps;
use App\Http\Middleware\UseStoreAuthGuard;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StoreUser;
use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Exceptions\GuardDoesNotMatch;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * The legacy store (StoreUser, guard `store`/`store_api`, `users`) and this
 * app's own admin panel and API (User, guard `web`/`sanctum`, `wms_users`)
 * share one session driver, one token table and one set of spatie tables.
 * Nothing but the guard tells their callers apart, so each seam is pinned
 * here: sessions, tokens, spatie roles and gates, and which routes carry which
 * guard's middleware. See .ai/rules/legacy-albaraka-holland.md, "Auth guards".
 */

/**
 * The route's `auth`/`auth:*` middleware entries.
 *
 * @return list<string>
 */
function authMiddlewareOf(RoutingRoute $route): array
{
    return array_values(array_filter(
        $route->gatherMiddleware(),
        fn ($middleware) => is_string($middleware) && ($middleware === 'auth' || str_starts_with($middleware, 'auth:')),
    ));
}

function isStoreRoute(RoutingRoute $route): bool
{
    return $route->uri() === 'temp' || str_starts_with($route->uri(), 'temp/');
}

test('a store session does not sign anyone into the admin panel, even for a matching id', function () {
    $storeAdmin = createStoreAdmin();
    User::factory()->create(['id' => $storeAdmin->id]);
    actingAsStoreUser($storeAdmin);

    $this->get('/admin')->assertRedirect(route('login'));
    $this->get('/temp/admin/roles/create')->assertOk();
});

test('an admin panel session does not sign anyone into the store, even for a matching id', function () {
    $storeAdmin = createStoreAdmin();
    $admin = User::factory()->create(['id' => $storeAdmin->id]);
    $this->actingAs($admin);

    // /admin first: a store request leaves Auth::shouldUse('store') on the one
    // app instance a test serves every request from, where production builds a
    // fresh container per request, so /admin would then run on the store guard.
    $this->followingRedirects()->get('/admin')->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Dashboard/Index')
    );
    $this->get('/temp/admin/roles/create')->assertRedirect(route('shop.login'));
});

/**
 * Both apps' tokens are rows of `wms_personal_access_tokens`, told apart only
 * by tokenable_type, so a route's guard has to check the model.
 *
 * `Auth::forgetGuards()` between requests: a Sanctum guard caches its user for
 * the life of the guard instance, and a test serves every request from one.
 */
test('a store token is refused by the warehouse api, which accepts a warehouse token', function () {
    $storeToken = storeApiToken(createStoreUser('customer'));
    $warehouseToken = User::factory()->mobileUser()->create()->createToken('mobile')->plainTextToken;

    $this->withToken($storeToken)->getJson('/api/v1/products')->assertUnauthorized();
    Auth::forgetGuards();

    $this->withToken($warehouseToken)->getJson('/api/v1/products')->assertOk();
});

test('a warehouse token is refused by the store api, which accepts a store token', function () {
    $customer = createStoreUser('customer');
    $storeToken = storeApiToken($customer);
    $warehouseToken = User::factory()->mobileUser()->create()->createToken('mobile')->plainTextToken;

    $this->withToken($warehouseToken)->getJson('/temp/api/v2/auth/user')->assertUnauthorized();
    Auth::forgetGuards();

    $this->withToken($storeToken)->getJson('/temp/api/v2/auth/user')
        ->assertOk()
        ->assertJsonPath('id', $customer->id);
});

test('the same role name on each guard is two roles, and a user only ever holds its own', function () {
    $warehouseAdminRole = SpatieRole::findOrCreate('admin', 'web');
    $storeAdminRole = SpatieRole::findOrCreate('admin', 'store');
    $storeStaff = createStoreUser('staff');
    $warehouseUser = User::factory()->mobileUser()->create();

    expect(fn () => $storeStaff->assignRole($warehouseAdminRole))->toThrow(GuardDoesNotMatch::class)
        ->and(fn () => $warehouseUser->assignRole($storeAdminRole))->toThrow(GuardDoesNotMatch::class);

    $storeStaff->assignRole('admin');

    expect($storeStaff->roles->modelKeys())->toBe([$storeAdminRole->id])
        ->and($storeStaff->hasRole('admin', 'web'))->toBeFalse()
        ->and($warehouseUser->fresh()->isAdmin())->toBeFalse()
        ->and(DB::table('wms_model_has_roles')->where('role_id', $storeAdminRole->id)->pluck('model_type')->all())->toBe([StoreUser::class])
        ->and(DB::table('wms_model_has_roles')->where('role_id', $warehouseAdminRole->id)->count())->toBe(0);

    $warehouseUser->assignRole('admin');

    expect($warehouseUser->fresh()->roles->modelKeys())->toBe([$warehouseAdminRole->id])
        ->and($warehouseUser->fresh()->hasRole('admin', 'store'))->toBeFalse()
        ->and($warehouseUser->fresh()->isAdmin())->toBeTrue();
});

test('a store staff member holding the store admin role cannot reach the admin panel', function () {
    SpatieRole::findOrCreate('admin', 'web');
    SpatieRole::findOrCreate('admin', 'store');
    $storeStaff = createStoreUser('staff');
    $storeStaff->assignRole('admin');
    actingAsStoreUser($storeStaff);

    $this->get('/admin')->assertRedirect(route('login'));
});

test('a warehouse user holding a role named Super Admin gets none of the store gate\'s grants', function () {
    $warehouseUser = User::factory()->mobileUser()->create();
    $warehouseUser->assignRole(SpatieRole::findOrCreate('Super Admin', 'web'));

    expect($warehouseUser->can('view_staff_roles'))->toBeFalse()
        ->and(createStoreAdmin()->can('view_staff_roles'))->toBeTrue();
});

test('roles and permissions created through the store models are store-guard whichever guard is the default', function () {
    Auth::shouldUse('web');
    SpatieRole::findOrCreate('Clerk', 'web');
    SpatiePermission::findOrCreate('view_clerks', 'web');

    $role = Role::create(['name' => 'Clerk']);
    $permission = Permission::create(['name' => 'view_clerks', 'section' => 'clerks']);

    expect($role->guard_name)->toBe('store')
        ->and($permission->guard_name)->toBe('store')
        ->and(Role::where('name', 'Clerk')->pluck('id')->all())->toBe([$role->id])
        ->and(Permission::where('name', 'view_clerks')->pluck('id')->all())->toBe([$permission->id]);
});

test('the store role screens list only store roles, and cannot edit, rename or delete a warehouse role', function () {
    $warehouseAdminRole = SpatieRole::findOrCreate('admin', 'web');
    foreach (range(1, 11) as $number) {
        SpatieRole::findOrCreate("Store Role $number", 'store');
    }
    actingAsStoreUser(createStoreAdmin());

    $this->get('/temp/admin/roles')
        ->assertOk()
        ->assertViewHas('roles', fn ($roles) => $roles->total() === 11
            && $roles->count() === 10
            && $roles->pluck('guard_name')->unique()->all() === ['store']);

    $this->get("/temp/admin/roles/edit/{$warehouseAdminRole->id}")->assertNotFound();
    $this->put("/temp/admin/roles/{$warehouseAdminRole->id}", ['name' => 'hijacked', 'lang' => 'en'])->assertNotFound();
    $this->post("/temp/admin/roles/destroy/{$warehouseAdminRole->id}")->assertNotFound();

    $this->assertDatabaseHas('wms_roles', ['id' => $warehouseAdminRole->id, 'name' => 'admin', 'guard_name' => 'web']);
});

test('the store staff screens offer only store roles', function () {
    SpatieRole::findOrCreate('admin', 'web');
    SpatieRole::findOrCreate('Store Clerk', 'store');
    actingAsStoreUser(createStoreAdmin());

    $this->get('/temp/admin/staffs/create')
        ->assertOk()
        ->assertViewHas('roles', fn ($roles) => $roles->pluck('name')->all() === ['Store Clerk']);

    $this->post('/temp/admin/staffs', [
        'name' => 'New Staff',
        'email' => 'new-staff@example.com',
        'mobile' => '5551234567',
        'password' => 'correct-password',
        'role_id' => SpatieRole::findByName('admin', 'web')->id,
    ])->assertNotFound();

    $this->assertDatabaseMissing('users', ['email' => 'new-staff@example.com']);
});

test('every store route that authenticates does so on a store guard, and no other route uses one', function () {
    $routes = collect(Route::getRoutes()->getRoutes());
    $storeRoutes = $routes->filter(fn (RoutingRoute $route) => isStoreRoute($route));
    $warehouseRoutes = $routes->reject(fn (RoutingRoute $route) => isStoreRoute($route));

    $misguarded = $storeRoutes->filter(function (RoutingRoute $route): bool {
        $auth = authMiddlewareOf($route);

        if ($auth === []) {
            return false;
        }

        $viaStoreApi = array_diff($auth, ['auth:store_api']) === [];
        $viaStoreSession = in_array(UseStoreAuthGuard::class, $route->gatherMiddleware(), true)
            && array_diff($auth, ['auth', 'auth:store']) === [];

        return ! $viaStoreApi && ! $viaStoreSession;
    });

    $leaking = $warehouseRoutes->filter(fn (RoutingRoute $route) => in_array(UseStoreAuthGuard::class, $route->gatherMiddleware(), true)
        || array_filter(authMiddlewareOf($route), fn (string $middleware) => str_starts_with($middleware, 'auth:store')) !== []);

    expect($storeRoutes->filter(fn (RoutingRoute $route) => authMiddlewareOf($route) !== [])->count())->toBeGreaterThan(100)
        ->and($warehouseRoutes->filter(fn (RoutingRoute $route) => authMiddlewareOf($route) !== [])->count())->toBeGreaterThan(0)
        ->and($misguarded->map->uri()->values()->all())->toBe([])
        ->and($leaking->map->uri()->values()->all())->toBe([]);
});

test('the warehouse admin panel\'s role:admin and the IP allow-list stay off every store route', function () {
    $routes = collect(Route::getRoutes()->getRoutes());
    $withWarehouseOnlyMiddleware = fn (RoutingRoute $route) => array_filter(
        $route->gatherMiddleware(),
        fn ($middleware) => $middleware === 'role:admin'
            || (is_string($middleware) && str_starts_with($middleware, RestrictToAllowedIps::class)),
    ) !== [];

    expect($routes->filter(fn (RoutingRoute $route) => ! isStoreRoute($route) && $withWarehouseOnlyMiddleware($route))->count())->toBeGreaterThan(0)
        ->and($routes->filter(fn (RoutingRoute $route) => isStoreRoute($route) && $withWarehouseOnlyMiddleware($route))->map->uri()->values()->all())->toBe([]);
});
