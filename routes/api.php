<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CellController;
use App\Http\Controllers\Api\V1\CellStatusLogController;
use App\Http\Controllers\Api\V1\CellVerificationReportController;
use App\Http\Controllers\Api\V1\CellVerificationRoundController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\PalletController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\RowController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('api.v1.auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        Route::get('user', [AuthController::class, 'show'])->name('api.v1.user.show');

        Route::get('dashboard', [DashboardController::class, 'index'])->name('api.v1.dashboard.index');

        Route::get('rows', [RowController::class, 'index'])->middleware('cache.headers:private;etag')->name('api.v1.rows.index');
        Route::get('rows/full', [RowController::class, 'full'])->middleware('cache.headers:private;etag')->name('api.v1.rows.full');
        Route::get('rows/frozen', [RowController::class, 'frozen'])->middleware('cache.headers:private;etag')->name('api.v1.rows.frozen');
        Route::get('rows/{row}/cells', [CellController::class, 'index'])->name('api.v1.cells.index');
        Route::get('rows/{row}/cells/{cell:cell_number}/flats/{flatNumber}', [CellController::class, 'show'])
            ->whereNumber(['cell', 'flatNumber'])
            ->name('api.v1.cells.show');

        Route::get('products', [ProductController::class, 'index'])->name('api.v1.products.index');
        Route::get('products/{product}', [ProductController::class, 'show'])->whereNumber('product')->name('api.v1.products.show');
        Route::get('products/{product}/pallets', [ProductController::class, 'pallets'])->whereNumber('product')->name('api.v1.products.pallets');

        Route::post('pallets', [PalletController::class, 'store'])->name('api.v1.pallets.store');
        Route::get('pallets/{pallet}', [PalletController::class, 'show'])->name('api.v1.pallets.show');
        Route::post('pallets/{pallet}/open', [PalletController::class, 'open'])->name('api.v1.pallets.open');
        Route::post('pallets/{pallet}/remove-boxes', [PalletController::class, 'removeBoxes'])->name('api.v1.pallets.remove-boxes');
        Route::post('pallets/{pallet}/empty', [PalletController::class, 'empty'])->name('api.v1.pallets.empty');
        Route::post('pallets/{pallet}/transfer', [PalletController::class, 'transfer'])->name('api.v1.pallets.transfer');

        Route::get('cell-logs', [CellStatusLogController::class, 'index'])->name('api.v1.cell-logs.index');

        Route::get('cell-verification-rounds', [CellVerificationRoundController::class, 'index'])->name('api.v1.cell-verification-rounds.index');
        Route::post('cell-verification-rounds', [CellVerificationRoundController::class, 'store'])->name('api.v1.cell-verification-rounds.store');
        Route::get('cell-verification-rounds/{cellVerificationRound}', [CellVerificationRoundController::class, 'show'])->name('api.v1.cell-verification-rounds.show');
        Route::post('cell-verification-rounds/{cellVerificationRound}/complete', [CellVerificationRoundController::class, 'complete'])->name('api.v1.cell-verification-rounds.complete');

        Route::post('cell-verification-reports', [CellVerificationReportController::class, 'store'])->name('api.v1.cell-verification-reports.store');
    });
});
