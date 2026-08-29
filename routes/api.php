<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CellController;
use App\Http\Controllers\Api\V1\CellStatusLogController;
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
        Route::get('rows/{row}/cells', [CellController::class, 'index']);
        Route::get('rows/{row}/cells/{cellNumber}/flats/{flatNumber}', [CellController::class, 'show'])
            ->whereNumber(['cellNumber', 'flatNumber']);

        Route::get('products', [ProductController::class, 'index']);

        Route::post('pallets', [PalletController::class, 'store']);
        Route::get('pallets/{pallet}', [PalletController::class, 'show']);
        Route::post('pallets/{pallet}/open', [PalletController::class, 'open']);
        Route::post('pallets/{pallet}/remove-boxes', [PalletController::class, 'removeBoxes']);
        Route::post('pallets/{pallet}/empty', [PalletController::class, 'empty']);
        Route::post('pallets/{pallet}/transfer', [PalletController::class, 'transfer']);

        Route::get('cell-logs', [CellStatusLogController::class, 'index']);
    });
});
