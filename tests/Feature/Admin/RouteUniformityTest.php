<?php

use App\Http\Controllers\Admin\CellController;
use App\Http\Controllers\Admin\CellVerificationReportController;
use App\Http\Controllers\Admin\CellVerificationRoundController;
use App\Http\Controllers\Admin\PalletController;
use App\Http\Controllers\Admin\ProductController;
use App\Models\Pallet;
use App\Models\Product;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

test('admin routes update with PUT, never PATCH', function () {
    $patchRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => str_starts_with($route->uri(), 'admin/') && in_array('PATCH', $route->methods(), true));

    expect($patchRoutes->map->uri()->all())->toBe([]);
});

test('admin routes resolve to the URI, verb and controller method their name promises', function (string $name, string $method, string $uri, string $controller, string $action) {
    $route = Route::getRoutes()->getByName($name);

    expect($route)->not->toBeNull()
        ->and($route->methods())->toContain($method)
        ->and($route->uri())->toBe($uri)
        ->and($route->getActionName())->toBe("{$controller}@{$action}");
})->with([
    'pallet update' => ['admin.pallets.update', 'PUT', 'admin/pallets/{pallet}', PalletController::class, 'update'],
    'pallet store' => ['admin.pallets.store', 'POST', 'admin/cells/{cell}/pallet', PalletController::class, 'store'],
    'box count' => ['admin.products.box-count.update', 'PUT', 'admin/products/{product}/box-count', ProductController::class, 'updateBoxCount'],
    'minimum pallets' => ['admin.products.minimum-pallets.update', 'PUT', 'admin/products/{product}/minimum-pallets', ProductController::class, 'updateMinimumPallets'],
    'barcode store' => ['admin.products.barcodes.store', 'POST', 'admin/products/{product}/barcodes', ProductController::class, 'storeBarcode'],
    'barcode destroy' => ['admin.products.barcodes.destroy', 'DELETE', 'admin/products/{product}/barcodes/{barcode}', ProductController::class, 'destroyBarcode'],
    'barcode download' => ['admin.products.barcodes.download', 'GET', 'admin/products/{product}/barcodes/{barcode}/download', ProductController::class, 'downloadBarcode'],
    'barcode download all' => ['admin.products.barcodes.download-all', 'GET', 'admin/products/{product}/barcodes/download', ProductController::class, 'downloadAllBarcodes'],
    'default barcode preview' => ['admin.products.export-qr.preview', 'GET', 'admin/products/{product}/export-qr/preview', ProductController::class, 'previewDefaultBarcode'],
    'cell qr code' => ['admin.cells.export-qr-code', 'GET', 'admin/cells/{cell}/export-qr-code', CellController::class, 'exportQrCode'],
    'round export' => ['admin.cell-verification-rounds.export-reports', 'GET', 'admin/cell-verification-rounds/{cellVerificationRound}/export', CellVerificationRoundController::class, 'exportReports'],
    'report accept' => ['admin.cell-verification-reports.accept', 'POST', 'admin/cell-verification-reports/{cellVerificationReport}/accept', CellVerificationReportController::class, 'accept'],
]);

test('the retired PATCH verbs and URIs no longer route', function () {
    actingAsAdmin();
    $product = Product::factory()->create();
    $pallet = Pallet::factory()->create();

    $this->patch("/admin/products/{$product->id}/box-count", ['boxes_count' => 30])->assertMethodNotAllowed();
    $this->patch("/admin/products/{$product->id}/minimum-pallets", ['minimum_pallets' => 5])->assertMethodNotAllowed();
    $this->put("/admin/pallets/{$pallet->id}/update", [])->assertNotFound();
    $this->get("/admin/cells/{$pallet->cell_id}/export-qr")->assertNotFound();
    $this->get('/admin/cells')->assertNotFound();
    $this->get("/admin/products/{$product->id}/image")->assertNotFound();
});
