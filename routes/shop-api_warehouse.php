<?php

use App\Http\Controllers\Api\V2\Warehouse\CategoryController;
use App\Http\Controllers\Api\V2\Warehouse\CustomerController;
use App\Http\Controllers\Api\V2\Warehouse\OrderController;
use App\Http\Controllers\Api\V2\Warehouse\ProductController;
use Illuminate\Support\Facades\Route;

// One group carries the throttle and wms.auth for every route here, so a route
// added to this file can't ship unauthenticated again: 4 of the original 11
// (getCustomersList, getRepsList, getCustomerDetails, customer_update) had no
// middleware at all, exposing customer PII and orders — see
// .ai/rules/legacy-albaraka-holland.md section 0.3. The throttle comes first so
// requests with a bad token are limited too (AppServiceProvider's `wms`).
Route::prefix('v2/warehouse')->middleware(['throttle:wms', 'wms.auth'])->group(function () {

    // Customers Section
    Route::controller(CustomerController::class)->group(function () {
        Route::get('/customers', 'getCustomersList');
        Route::get('/reps', 'getRepsList');
        Route::get('/customer/{id}', 'getCustomerDetails');
        Route::post('/customer/update/{id}', 'update');
    });

    // Orders Section
    Route::controller(OrderController::class)->group(function () {
        Route::get('/orders', 'getOrdersList');
        Route::get('/order/{id}', 'getOrderDetails');
        Route::get('/order_items/{id}', 'getOrderItems');
        Route::post('/update_order', 'updateOrderStatus');
        Route::post('/order/update-customer', 'customer_update');
    });

    // Products Section
    Route::get('/products', [ProductController::class, 'getProducts']);

    // Categories Section
    Route::get('/categories', [CategoryController::class, 'getCategories']);
});
