<?php

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

test('every mobile API route is named api.v1.{resource}.{action}, nested resources api.v1.{resource}.{sub-resource}.{action}', function () {
    $mobileRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => str_starts_with($route->uri(), 'api/v1/') && ! str_starts_with($route->uri(), 'api/v1/store/'));

    expect($mobileRoutes)->not->toBeEmpty();

    $mobileRoutes->each(fn (RoutingRoute $route) => expect($route->getName())
        ->toMatch('/^api\.v1\.[a-z-]+(\.[a-z-]+)?\.[a-z-]+$/', $route->uri()));
    expect($mobileRoutes->map->getName()->unique())->toHaveCount($mobileRoutes->count());
});

test('naming the mobile API routes leaves their URIs unchanged', function (string $name, array $parameters, string $path) {
    expect(route($name, $parameters, false))->toBe($path);
})->with([
    'login' => ['api.v1.auth.login', [], '/api/v1/login'],
    'logout' => ['api.v1.auth.logout', [], '/api/v1/logout'],
    'user' => ['api.v1.user.show', [], '/api/v1/user'],
    'dashboard' => ['api.v1.dashboard.index', [], '/api/v1/dashboard'],
    'warehouses' => ['api.v1.warehouses.index', [], '/api/v1/warehouses'],
    'rows' => ['api.v1.rows.index', [], '/api/v1/rows'],
    'rows full' => ['api.v1.rows.full', [], '/api/v1/rows/full'],
    'rows frozen' => ['api.v1.rows.frozen', [], '/api/v1/rows/frozen'],
    'row cells' => ['api.v1.cells.index', ['row' => 'A'], '/api/v1/rows/A/cells'],
    'cell' => ['api.v1.cells.show', ['row' => 'A', 'cell' => 2, 'flatNumber' => 3], '/api/v1/rows/A/cells/2/flats/3'],
    'products' => ['api.v1.products.index', [], '/api/v1/products'],
    'product' => ['api.v1.products.show', ['product' => 7], '/api/v1/products/7'],
    'product pallets' => ['api.v1.products.pallets', ['product' => 7], '/api/v1/products/7/pallets'],
    'product barcodes store' => ['api.v1.product-barcodes.store', ['product' => 7], '/api/v1/products/7/barcodes'],
    'pallet store' => ['api.v1.pallets.store', [], '/api/v1/pallets'],
    'pallet' => ['api.v1.pallets.show', ['pallet' => 4], '/api/v1/pallets/4'],
    'pallet place' => ['api.v1.pallets.place', ['pallet' => 4], '/api/v1/pallets/4/place'],
    'pallets unplaced' => ['api.v1.pallets.unplaced', [], '/api/v1/pallets/unplaced'],
    'pallet open' => ['api.v1.pallets.open', ['pallet' => 4], '/api/v1/pallets/4/open'],
    'pallet remove boxes' => ['api.v1.pallets.remove-boxes', ['pallet' => 4], '/api/v1/pallets/4/remove-boxes'],
    'pallet empty' => ['api.v1.pallets.empty', ['pallet' => 4], '/api/v1/pallets/4/empty'],
    'pallet transfer' => ['api.v1.pallets.transfer', ['pallet' => 4], '/api/v1/pallets/4/transfer'],
    'cell logs' => ['api.v1.cell-logs.index', [], '/api/v1/cell-logs'],
    'rounds' => ['api.v1.cell-verification-rounds.index', [], '/api/v1/cell-verification-rounds'],
    'round store' => ['api.v1.cell-verification-rounds.store', [], '/api/v1/cell-verification-rounds'],
    'round' => ['api.v1.cell-verification-rounds.show', ['cellVerificationRound' => 9], '/api/v1/cell-verification-rounds/9'],
    'round complete' => ['api.v1.cell-verification-rounds.complete', ['cellVerificationRound' => 9], '/api/v1/cell-verification-rounds/9/complete'],
    'receipts' => ['api.v1.receipts.index', [], '/api/v1/receipts'],
    'receipt store' => ['api.v1.receipts.store', [], '/api/v1/receipts'],
    'receipt' => ['api.v1.receipts.show', ['receipt' => 5], '/api/v1/receipts/5'],
    'receipt pallets' => ['api.v1.receipts.pallets.update', ['receipt' => 5], '/api/v1/receipts/5/pallets'],
    'receipt finalize' => ['api.v1.receipts.finalize', ['receipt' => 5], '/api/v1/receipts/5/finalize'],
    'receipt file store' => ['api.v1.receipts.files.store', ['receipt' => 5], '/api/v1/receipts/5/files'],
    'receipt file' => ['api.v1.receipts.files.show', ['receipt' => 5, 'file' => 8], '/api/v1/receipts/5/files/8'],
    'receipt file destroy' => ['api.v1.receipts.files.destroy', ['receipt' => 5, 'file' => 8], '/api/v1/receipts/5/files/8'],
    'report store' => ['api.v1.cell-verification-reports.store', [], '/api/v1/cell-verification-reports'],
]);
