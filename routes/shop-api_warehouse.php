<?php

use App\Http\Controllers\Api\V2\Warehouse\CategoryController;
use App\Http\Controllers\Api\V2\Warehouse\CustomerController;
use App\Http\Controllers\Api\V2\Warehouse\OrderController;
use App\Http\Controllers\Api\V2\Warehouse\ProductController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v2/warehouse'], function () {

    // Customers Section
    //
    // Every route in this file now carries wms.auth: 4 of these 11 routes
    // (getCustomersList, getRepsList, getCustomerDetails, customer_update)
    // shipped with no middleware at all, unauthenticated, exposing customer
    // PII and orders — see .ai/rules/legacy-albaraka-holland.md section 0.3.
    // This merge is the first time this file is reachable from this
    // codebase, so this is fixed here rather than carried over.
    Route::controller(CustomerController::class)->group(function () {
        Route::get('/customers', 'getCustomersList')->middleware('wms.auth');
        Route::get('/reps', 'getRepsList')->middleware('wms.auth');
        Route::get('/customer/{id}', 'getCustomerDetails')->middleware('wms.auth');
        Route::post('/customer/update/{id}', 'update')->middleware('wms.auth');
    });

    // Orders Section
    Route::controller(OrderController::class)->group(function () {
        Route::get('/orders', 'getOrdersList')->middleware('wms.auth');
        Route::get('/order/{id}', 'getOrderDetails')->middleware('wms.auth');
        Route::get('/order_items/{id}', 'getOrderItems')->middleware('wms.auth');
        Route::post('/update_order', 'updateOrderStatus')->middleware('wms.auth');
        Route::post('/order/update-customer', 'customer_update')->middleware('wms.auth');
    });

    // Products Section
    Route::controller(ProductController::class)->group(function () {
        Route::get('/products', 'getProducts')->middleware('wms.auth');
    });

    // Categories Section
    Route::controller(CategoryController::class)->group(function () {
        Route::get('/categories', 'getCategories')->middleware('wms.auth');
    });
});
