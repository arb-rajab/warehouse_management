<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Api\V2\BrandController as ApiBrandController;
use App\Http\Controllers\BrandController;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Regression test for RouteServiceProvider::keepLastRegisteredRouteNames() —
 * see that method's own docblock. Laravel 12 changed route-name resolution
 * so the FIRST route registered with a given name wins instead of the LAST,
 * which would silently repoint dozens of legacy route() calls and view links
 * at the wrong controller. This proves the workaround still keeps the last
 * registration reachable by name.
 */
test('keepLastRegisteredRouteNames keeps the last route registered with a duplicate name', function () {
    Route::get('/reflection-test-first', fn () => 'first')->name('reflection.dup');
    Route::get('/reflection-test-second', fn () => 'second')->name('reflection.dup');

    $provider = new RouteServiceProvider(app());
    (new ReflectionMethod($provider, 'keepLastRegisteredRouteNames'))->invoke($provider);

    // Mirrors the base RouteServiceProvider::register()'s own booted()
    // callback, which is what actually makes route() resolution see this
    // change in the real request lifecycle — see that method's docblock.
    Route::getRoutes()->refreshNameLookups();

    expect(route('reflection.dup'))->toEndWith('/reflection-test-second');
});

/**
 * The legacy app's own RouteNameResolutionTest pinned these, one per kind of
 * duplicate. Which registration keeps a duplicated name depends on the order
 * RouteServiceProvider::map() loads the route files in: with the api/v2 files
 * loaded after the admin one, every admin link to a name the API also
 * registers (brands.index, categories.index, customers.show, ...) pointed at
 * the JSON API instead.
 */
test('duplicated store route names resolve to the route the legacy app gave them', function () {
    expect(route('brands.index', [], false))->toBe('/temp/admin/brands')
        ->and(route('brands.destroy', 5, false))->toBe('/temp/admin/brands/destroy/5')
        ->and(route('brands.edit', 5, false))->toBe('/temp/admin/brands/edit/5')
        ->and(route('categories.index', [], false))->toBe('/temp/admin/categories')
        ->and(route('customers.show', 1, false))->toBe('/temp/admin/customers/1')
        ->and(route('customers.store', [], false))->toBe('/temp/admin/customer/store')
        ->and(route('languages.update', 3, false))->toBe('/temp/admin/languages/3/update')
        ->and(route('orders.destroy', 7, false))->toBe('/temp/admin/orders/destroy/7')
        ->and(route('payhere.checkout.testing', [], false))->toBe('/temp/payhere/wallet/testing');
});

test('the routes that lost a duplicated name are still registered', function () {
    $apiBrands = Route::getRoutes()->match(Request::create('/temp/api/v2/brands'));
    $resourceDestroy = Route::getRoutes()->match(Request::create('/temp/admin/brands/5', 'DELETE'));

    expect($apiBrands->getActionName())->toBe(ApiBrandController::class.'@index')
        ->and($apiBrands->getName())->toBeNull()
        ->and($resourceDestroy->getActionName())->toBe(BrandController::class.'@destroy')
        ->and($resourceDestroy->getName())->toBeNull();
});

test('no route name is registered twice', function () {
    $names = collect(Route::getRoutes()->getRoutes())
        ->map(fn (RoutingRoute $route): ?string => $route->getName())
        ->filter();

    expect($names->duplicates()->values()->all())->toBe([]);
});

/**
 * login, logout, admin.dashboard and password.update are this app's own
 * names and the legacy store's too. The store's copies are the ones renamed,
 * since this app's own code (and every route('login') the framework makes on
 * its behalf) must keep reaching its own routes.
 */
test("this app's own auth route names point at its own routes, and the store's shop. copies at /temp", function () {
    expect(route('login', [], false))->toBe('/admin/login')
        ->and(route('logout', [], false))->toBe('/logout')
        ->and(route('admin.dashboard', [], false))->toBe('/admin')
        ->and(route('password.update', [], false))->toBe('/password/change')
        ->and(route('shop.login', [], false))->toBe('/temp/login')
        ->and(route('shop.logout', [], false))->toBe('/temp/logout')
        ->and(route('shop.admin.dashboard', [], false))->toBe('/temp/admin')
        ->and(route('shop.password.update', [], false))->toBe('/temp/password/reset/email/submit');
});

/**
 * shop-web.php ends in the storefront's `/{slug}` custom-page catch-all, so
 * it has to load after every other store route file — loaded first, it
 * swallowed `/temp/admin` itself.
 */
test('the store admin dashboard is not swallowed by the storefront catch-all', function () {
    $route = Route::getRoutes()->match(Request::create('/temp/admin'));

    expect($route->getActionName())->toBe(AdminController::class.'@admin_dashboard');
});

test("the store's route provider does not register this app's own routes a second time", function () {
    $ownLogin = Route::getRoutes()->match(Request::create('/admin/login'));

    $provider = new RouteServiceProvider(app());
    (new ReflectionMethod($provider, 'loadRoutes'))->invoke($provider);

    expect(Route::getRoutes()->match(Request::create('/admin/login')))->toBe($ownLogin);
});

/**
 * The legacy controllers' HasMiddleware lists name spatie's `permission`
 * alias on 198 admin routes. The legacy app registered it in its HTTP kernel;
 * bootstrap/app.php had not, so each of those routes failed with "Target
 * class [permission] does not exist" before any permission was checked.
 */
test('every middleware a route names is a registered alias, a group or a class', function () {
    $router = app('router');

    $unresolvable = collect(Route::getRoutes()->getRoutes())
        ->flatMap(fn (RoutingRoute $route) => collect($route->gatherMiddleware())
            ->filter(fn (mixed $middleware): bool => is_string($middleware))
            ->map(fn (string $middleware): string => Str::before($middleware, ':'))
            ->reject(fn (string $name): bool => array_key_exists($name, $router->getMiddleware())
                || array_key_exists($name, $router->getMiddlewareGroups())
                || class_exists($name))
            ->map(fn (string $name): string => $name.' on '.$route->uri()))
        ->values()
        ->all();

    expect($unresolvable)->toBe([]);
});
