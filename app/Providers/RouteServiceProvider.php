<?php

namespace App\Providers;

use App\Http\Middleware\HttpsProtocol;
use App\Http\Middleware\Language;
use App\Http\Middleware\UseStoreAuthGuard;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Mount the legacy store's routes under /temp, per
     * .ai/rules/legacy-albaraka-holland.md requirement 5 — the store's own
     * top-level route files (web.php, admin.php, api.php, ...) would
     * otherwise collide with this app's own routes/web.php and routes/api.php,
     * so they were moved to routes/shop-*.php.
     *
     * configureRateLimiting() from the legacy version of this class is
     * deliberately dropped: it redefined the 'api' RateLimiter, which would
     * have silently replaced this app's own — already correct — definition
     * in AppServiceProvider (see that class's docblock on the guard/IP
     * fallback bug it fixes).
     *
     * CountWebRouteVisits (the legacy web-only visit counter) is also
     * dropped: it makes a live, unconditionally-timed-out outbound HTTP call
     * to api.ipgeolocation.io on every single request, which is not
     * something to put in the request path of newly-mounted routes CI
     * exercises — nothing currently depends on the counter it updates.
     *
     * @return void
     */
    public function map()
    {
        Route::prefix('temp')->group(function () {
            // UseStoreAuthGuard first, before Language/HttpsProtocol or any
            // route-level auth/role middleware runs — see that class's own
            // docblock for why every one of these three route files needs it.
            Route::middleware(['web', UseStoreAuthGuard::class, Language::class, HttpsProtocol::class])
                ->group(base_path('routes/shop-web.php'));

            Route::middleware(['web', UseStoreAuthGuard::class, Language::class, HttpsProtocol::class])
                ->group(base_path('routes/shop-admin.php'));

            Route::middleware(['web', UseStoreAuthGuard::class, Language::class, HttpsProtocol::class])
                ->group(base_path('routes/shop-seller.php'));

            Route::prefix('api')->middleware('api')->group(base_path('routes/shop-api.php'));
            Route::prefix('api')->middleware('api')->group(base_path('routes/shop-api_seller.php'));

            // No outer middleware group, matching the legacy app's own
            // mapApiWareHouseRoutes()/mapApiTripRoutes(): each route in these
            // two files carries its own inline middleware (wms.auth,
            // auth:sanctum, driver).
            Route::prefix('api')->group(base_path('routes/shop-api_warehouse.php'));
            Route::prefix('api')->group(base_path('routes/shop-api_trip.php'));
        });

        $this->keepLastRegisteredRouteNames();
    }

    /**
     * Leave each route name on the last route registered with it.
     *
     * 58 names are registered twice, mostly a Route::resource() followed by an
     * explicit override such as Route::get('/brands/destroy/{id}')->name('brands.destroy'),
     * or an api/v2 resource sharing a name with an admin page. Up to Laravel 11 the
     * last registration won; from Laravel 12 the first one does, which would point
     * route('brands.index') at the JSON API and every GET delete link at a DELETE
     * route. Removing the name from the earlier routes keeps the old targets, and
     * lets route:cache compile, which refuses duplicate names.
     */
    protected function keepLastRegisteredRouteNames(): void
    {
        $routes = Route::getRoutes()->getRoutes();
        $lastIndexByName = [];

        foreach ($routes as $index => $route) {
            if ($name = $route->getName()) {
                $lastIndexByName[$name] = $index;
            }
        }

        foreach ($routes as $index => $route) {
            $name = $route->getName();

            if ($name && $lastIndexByName[$name] !== $index) {
                $action = $route->getAction();
                unset($action['as']);
                $route->setAction($action);
            }
        }
    }
}
