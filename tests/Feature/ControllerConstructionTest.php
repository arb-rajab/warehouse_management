<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

test('every route controller can be built before any migrated table exists', function () {
    // The deploy boots the new release (route:list, wayfinder:generate) against the
    // previous release's database, before its migrations have run — so no
    // controller constructor may read a table.
    Schema::dropAllTables();

    $queryFailures = [];

    foreach (Route::getRoutes() as $route) {
        if ($route->getControllerClass() === null) {
            continue;
        }

        try {
            $route->getController();
        } catch (QueryException $exception) {
            $queryFailures[$route->getControllerClass()] = $exception->getMessage();
        }
    }

    expect($queryFailures)->toBe([]);
});
