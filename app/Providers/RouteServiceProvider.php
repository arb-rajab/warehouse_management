<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RouteServiceProvider extends ServiceProvider
{
  /**
   * This namespace is applied to your controller routes.
   *
   * In addition, it is set as the URL generator's root namespace.
   *
   * @var string
   */
   protected $namespace = null;

  /**
   * Define your route model bindings, pattern filters, etc.
   *
   * @return void
   */
  public function boot()
  {
    //

    parent::boot();

    $this->configureRateLimiting();
  }

  /**
   * Define the routes for the application.
   *
   * @return void
   */
  public function map()
  {
     $this->mapApiRoutes();

     $this->mapApiSellerRoutes();

     $this->mapAdminRoutes();

     $this->mapSellerRoutes();

     $this->mapApiWareHouseRoutes();

     $this->mapApiTripRoutes();

     $this->mapWebRoutes();

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

  /**
   * Define the "web" routes for the application.
   *
   * These routes all receive session state, CSRF protection, etc.
   *
   * @return void
   */
  protected function mapWebRoutes()
  {
    Route::middleware(['web', \App\Http\Middleware\CountWebRouteVisits::class])
       ->namespace($this->namespace)
       ->group(base_path('routes/web.php'));
  }

  /**
   * Define the "admin" routes for the application.
   *
   * These routes all receive session state, CSRF protection, etc.
   *
   * @return void
   */
  protected function mapAdminRoutes()
  {
    Route::middleware('web')
       ->namespace($this->namespace)
       ->group(base_path('routes/admin.php'));
  }

  /**
   * Define the "seller" routes for the application.
   *
   * These routes all receive session state, CSRF protection, etc.
   *
   * @return void
   */
  protected function mapSellerRoutes()
  {
    Route::middleware('web')
       ->namespace($this->namespace)
       ->group(base_path('routes/seller.php'));
  }

  /**
   * Define the "api" routes for the application.
   *
   * These routes are typically stateless.
   *
   * @return void
   */
  protected function mapApiSellerRoutes()
  {
    Route::prefix('api')
       ->middleware('api')
       ->namespace($this->namespace)
       ->group(base_path('routes/api_seller.php'));
  }

/**
   * Define the "api" routes for the warehouse.
   *
   * These routes are typically stateless.
   *
   * @return void
   */

  protected function mapApiWareHouseRoutes(){
    Route::prefix('api')
    ->namespace($this->namespace)
    ->group(base_path('routes/api_warehouse.php'));
  }

/**
   * Define the "api" routes for the warehouse.
   *
   * These routes are typically stateless.
   *
   * @return void
   */

  protected function mapApiTripRoutes(){
    Route::prefix('api')
    ->namespace($this->namespace)
    ->group(base_path('routes/api_trip.php'));
  }

  /**
   * Define the "api" routes for the application.
   *
   * These routes are typically stateless.
   *
   * @return void
   */
  protected function mapApiRoutes()
  {
    Route::prefix('api')
       ->middleware('api')
       ->namespace($this->namespace)
       ->group(base_path('routes/api.php'));
  }

  /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });
    }

}
