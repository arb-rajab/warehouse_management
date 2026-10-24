<?php

use App\Http\Controllers\Admin\CellController;
use App\Http\Controllers\Admin\CellStatusLogController;
use App\Http\Controllers\Admin\CellVerificationReportController;
use App\Http\Controllers\Admin\CellVerificationRoundController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HelpController;
use App\Http\Controllers\Admin\PalletController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductImageReviewController;
use App\Http\Controllers\Admin\RowController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WarehouseMapController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PasswordChangeController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\WelcomeController;
use App\Http\Middleware\RestrictToAllowedIps;
use Illuminate\Support\Facades\Route;
use Spatie\Health\Http\Controllers\HealthCheckResultsController;

Route::get('/', WelcomeController::class)->name('welcome');

Route::get('robots.txt', RobotsController::class)->name('robots');

Route::post('locale/{locale}', [LocaleController::class, 'update'])->name('locale.update');

Route::get('health', HealthCheckResultsController::class)
    ->middleware(['can:viewHealth', RestrictToAllowedIps::class.':health.allowed_ips'])
    ->name('health');

Route::middleware('guest')->group(function () {
    Route::get('admin/login', [LoginController::class, 'create'])->name('login');
    Route::post('admin/login', [LoginController::class, 'store'])->middleware(['honeypot', 'throttle:login']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('password/change', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::put('password/change', [PasswordChangeController::class, 'update'])->middleware('throttle:password-change')->name('password.update');

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('rows', [RowController::class, 'index'])->name('rows.index');
        Route::get('rows/create', [RowController::class, 'create'])->name('rows.create');
        Route::post('rows', [RowController::class, 'store'])->name('rows.store');
        Route::get('rows/{row}', [RowController::class, 'show'])->name('rows.show');
        Route::get('rows/{row}/edit', [RowController::class, 'edit'])->name('rows.edit');
        Route::put('rows/{row}', [RowController::class, 'update'])->name('rows.update');
        Route::delete('rows/{row}', [RowController::class, 'destroy'])->name('rows.destroy');
        Route::get('rows/{row}/export-qr-codes', [RowController::class, 'exportQrCodes'])->name('rows.export-qr-codes');

        Route::get('cells/{cell}/export-qr-code', [CellController::class, 'exportQrCode'])->name('cells.export-qr-code');
        Route::post('cells/{cell}/toggle-active', [CellController::class, 'toggleActive'])->name('cells.toggle-active');

        Route::get('warehouse-map', [WarehouseMapController::class, 'index'])->name('warehouse-map.index');
        Route::get('warehouse-map/rows', [WarehouseMapController::class, 'rows'])
            ->middleware('cache.headers:private;etag')
            ->name('warehouse-map.rows');

        Route::get('pallets', [PalletController::class, 'index'])->name('pallets.index');
        Route::post('cells/{cell}/pallet', [PalletController::class, 'store'])->name('pallets.store');
        Route::put('pallets/{pallet}', [PalletController::class, 'update'])->name('pallets.update');
        Route::post('pallets/{pallet}/open', [PalletController::class, 'open'])->name('pallets.open');
        Route::post('pallets/{pallet}/remove-boxes', [PalletController::class, 'removeBoxes'])->name('pallets.remove-boxes');
        Route::post('pallets/{pallet}/empty', [PalletController::class, 'empty'])->name('pallets.empty');
        Route::post('pallets/{pallet}/transfer', [PalletController::class, 'transfer'])->name('pallets.transfer');

        Route::get('cell-logs', [CellStatusLogController::class, 'index'])->name('cell-logs.index');
        Route::post('cell-logs/{cellStatusLog}/acknowledge-flags', [CellStatusLogController::class, 'acknowledgeFlags'])->name('cell-logs.acknowledge-flags');

        Route::get('cell-verification-rounds', [CellVerificationRoundController::class, 'index'])->name('cell-verification-rounds.index');
        Route::get('cell-verification-rounds/{cellVerificationRound}', [CellVerificationRoundController::class, 'show'])->name('cell-verification-rounds.show');
        Route::post('cell-verification-rounds/{cellVerificationRound}/complete', [CellVerificationRoundController::class, 'complete'])->name('cell-verification-rounds.complete');
        Route::get('cell-verification-rounds/{cellVerificationRound}/export', [CellVerificationRoundController::class, 'exportReports'])->name('cell-verification-rounds.export-reports');
        Route::post('cell-verification-reports/{cellVerificationReport}/accept', [CellVerificationReportController::class, 'accept'])->name('cell-verification-reports.accept');

        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('products/search', [ProductController::class, 'search'])->middleware('cache.headers:private;etag')->name('products.search');
        Route::get('products/{product}/image-reviews', [ProductImageReviewController::class, 'show'])->name('products.image-reviews.show');
        Route::post('products/{product}/image-reviews', [ProductImageReviewController::class, 'store'])->name('products.image-reviews.store');
        Route::post('products/{product}/reference-image', [ProductImageReviewController::class, 'updateImage'])->name('products.reference-image.update');
        Route::get('products/{product}/export-qr', [ProductController::class, 'exportQr'])->name('products.export-qr');
        Route::get('products/{product}/export-qr/preview', [ProductController::class, 'previewDefaultBarcode'])->name('products.export-qr.preview');
        Route::put('products/{product}/box-count', [ProductController::class, 'updateBoxCount'])->name('products.box-count.update');
        Route::put('products/{product}/minimum-pallets', [ProductController::class, 'updateMinimumPallets'])->name('products.minimum-pallets.update');
        Route::post('products/{product}/barcodes', [ProductController::class, 'storeBarcode'])->name('products.barcodes.store');
        Route::get('products/{product}/barcodes/download', [ProductController::class, 'downloadAllBarcodes'])->name('products.barcodes.download-all');
        Route::get('products/{product}/barcodes/{barcode}/download', [ProductController::class, 'downloadBarcode'])->scopeBindings()->name('products.barcodes.download');
        Route::get('products/{product}/barcodes/{barcode}/preview', [ProductController::class, 'previewBarcode'])->scopeBindings()->name('products.barcodes.preview');
        Route::delete('products/{product}/barcodes/{barcode}', [ProductController::class, 'destroyBarcode'])->scopeBindings()->name('products.barcodes.destroy');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');

        Route::get('help', [HelpController::class, 'index'])->name('help.index');
        Route::get('help/{topic}', [HelpController::class, 'show'])->name('help.show');
    });
});
