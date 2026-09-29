<?php

use App\Http\Controllers\CountryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShopController;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Regression coverage for converting 41 legacy controllers' constructor-based
 * $this->middleware(...) calls to Laravel 11+'s HasMiddleware interface — see
 * app/Http/Controllers/Auth/LoginController.php's middleware() docblock for
 * why: the old pattern throws "Call to undefined method ::middleware()" the
 * instant the controller is instantiated, on every single request, whether
 * or not the acting user would have passed the permission check at all.
 *
 * Full route-level integration coverage (an authenticated StoreUser with the
 * right spatie permission actually reaching one of these actions) isn't
 * attempted here — that needs its own permission/role fixtures per
 * controller and is a much larger, separate testing task. This instead
 * proves the mechanical conversion itself is structurally sound: each
 * middleware() method returns real Middleware definitions with the original
 * permission names and only()/except() scoping intact, and the controllers
 * that had extra constructor logic (dependency injection) or a different
 * call shape ($this->middleware('x', ['only' => [...]])) kept working.
 */
test('a simple permission-gated controller\'s middleware() preserves the original permission name and scoping', function () {
    $middleware = CountryController::middleware();

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(Middleware::class)
        ->and($middleware[0]->middleware)->toBe('permission:shipping_country_setting')
        ->and($middleware[0]->only)->toBe(['index']);
});

test('a controller using the alternate $this->middleware($name, [\'only\' => [...]]) call shape converts correctly', function () {
    $middleware = ShopController::middleware();

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0]->middleware)->toBe('user')
        ->and($middleware[0]->only)->toBe(['index']);
});

test('a controller with dependency-injected constructor arguments keeps them alongside the converted middleware', function () {
    $middleware = ProductController::middleware();

    expect($middleware)->toHaveCount(7);

    // The constructor itself still wires up its services -- resolving the
    // controller out of the container is what would fail if that logic had
    // been lost during the conversion instead of just the middleware calls.
    $controller = app(ProductController::class);
    expect($controller)->toBeInstanceOf(ProductController::class);
});

test('CountryController::index() still returns its view, unaffected by the middleware conversion', function () {
    // Deliberately calls the action directly rather than the real route:
    // the admin layout (shop/backend/inc/admin_nav.blade.php) has its own,
    // separate rendering issues under a synthetic auth context (the same
    // Language-row null-safety gap fixed elsewhere for the frontend layout,
    // plus user-relation accessors that assume a real request cycle) --
    // real bugs, but a distinct, unscoped investigation from this pass,
    // which only touched the controllers' own constructors.
    $response = app(CountryController::class)->index(request());

    expect($response)->toBeInstanceOf(View::class)
        ->and($response->getName())->toBe('shop.backend.setup_configurations.countries.index');
});
