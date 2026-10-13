<?php

use App\Http\Controllers\BusinessSettingsController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\Store\Admin\CountryController;
use Illuminate\Routing\Controllers\Middleware;

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
        // The write endpoints share their page's permission (LegacyAdminPermissionsTest).
        ->and($middleware[0]->only)->toBeNull();
});

test('a controller using the alternate $this->middleware($name, [\'only\' => [...]]) call shape converts correctly', function () {
    $middleware = ShopController::middleware();

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0]->middleware)->toBe('user')
        ->and($middleware[0]->only)->toBe(['index']);
});

test('a controller with dependency-injected constructor arguments keeps them alongside the converted middleware', function () {
    $middleware = ProductController::middleware();

    // Seven until the in-house and all-products lists moved to
    // Store\Admin\ProductController with their two list gates, five until
    // the create form moved there with its add_new_product gate.
    expect($middleware)->toHaveCount(4);

    // The constructor itself still wires up its services -- resolving the
    // controller out of the container is what would fail if that logic had
    // been lost during the conversion instead of just the middleware calls.
    $controller = app(ProductController::class);
    expect($controller)->toBeInstanceOf(ProductController::class);
});

test('the root settings controller keeps the gates of its remaining Blade and compatibility actions', function () {
    $middleware = collect(BusinessSettingsController::middleware())
        ->mapWithKeys(fn (Middleware $entry): array => [$entry->middleware => $entry->only]);

    // general_setting moved to Store\Admin\GeneralSettingsController with its
    // gate; the generic `update` writer and its four-permission gate retired
    // with the footer and appearance forms (store-backoffice-migration.md §20).
    expect($middleware->all())->toBe([
        'permission:seller_commission_configuration' => ['vendor_commission', 'vendor_commission_update'],
        'permission:seller_verification_form_configuration' => ['seller_verification_form', 'seller_verification_form_update'],
        'permission:features_activation' => ['updateSellerCommissionActivation'],
        'permission:google_map_setting' => ['google_map'],
    ])->and(method_exists(BusinessSettingsController::class, 'update'))->toBeFalse();
});
