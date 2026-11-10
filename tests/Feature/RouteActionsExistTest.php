<?php

use App\Http\Controllers\WelcomeController;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/**
 * A route whose controller method does not exist answers every request with a
 * BadMethodCallException (a 500). `Route::resource()` without `only()`, a
 * misspelt action and a handler dropped in a merge all produced them. Every
 * route of this application must point at a method that exists.
 */
test('every route of the application points at a controller method that exists', function () {
    $missing = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => str_starts_with($route->getActionName(), 'App\\'))
        ->reject(function (RoutingRoute $route) {
            [$class, $method] = array_pad(explode('@', $route->getActionName(), 2), 2, '__invoke');

            return class_exists($class) && method_exists($class, $method);
        })
        ->map(fn (RoutingRoute $route) => $route->methods()[0].' /'.$route->uri().' -> '.$route->getActionName())
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

test('the guard recognises a route whose method is missing', function () {
    Route::get('/__missing-action', [WelcomeController::class, 'doesNotExist']);

    $missing = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => $route->uri() === '__missing-action')
        ->reject(fn (RoutingRoute $route) => method_exists(...explode('@', $route->getActionName(), 2)))
        ->count();

    expect($missing)->toBe(1);
});
