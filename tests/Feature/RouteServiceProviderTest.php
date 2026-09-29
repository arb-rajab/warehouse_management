<?php

use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Route;

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
