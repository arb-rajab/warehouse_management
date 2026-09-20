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
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);

        Route::get('dashboard', [DashboardController::class, 'index']);

        Route::get('rows', [RowController::class, 'index']);
        Route::get('rows/full', [RowController::class, 'full']);
        Route::get('rows/frozen', [RowController::class, 'frozen']);
        Route::get('rows/{row}/cells', [CellController::class, 'index']);
        Route::get('rows/{row}/cells/{cellNumber}/flats/{flatNumber}', [CellController::class, 'show'])
            ->whereNumber(['cellNumber', 'flatNumber']);

        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{product}', [ProductController::class, 'show'])->whereNumber('product');

        Route::post('pallets', [PalletController::class, 'store']);
        Route::get('pallets/{pallet}', [PalletController::class, 'show']);
        Route::post('pallets/{pallet}/open', [PalletController::class, 'open']);
        Route::post('pallets/{pallet}/remove-boxes', [PalletController::class, 'removeBoxes']);
        Route::post('pallets/{pallet}/empty', [PalletController::class, 'empty']);
        Route::post('pallets/{pallet}/transfer', [PalletController::class, 'transfer']);

        Route::get('cell-logs', [CellStatusLogController::class, 'index']);

        Route::get('cell-verification-rounds', [CellVerificationRoundController::class, 'index']);
        Route::post('cell-verification-rounds', [CellVerificationRoundController::class, 'store']);
        Route::get('cell-verification-rounds/{cellVerificationRound}', [CellVerificationRoundController::class, 'show']);
        Route::post('cell-verification-rounds/{cellVerificationRound}/complete', [CellVerificationRoundController::class, 'complete']);

        Route::post('cell-verification-reports', [CellVerificationReportController::class, 'store']);
    });
});
