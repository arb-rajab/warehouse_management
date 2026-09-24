<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V2\Warehouse\CustomerController;
use App\Http\Controllers\Api\V2\Warehouse\OrderController;
use App\Http\Controllers\Api\V2\Warehouse\ProductController;
use App\Http\Controllers\Api\V2\Warehouse\CategoryController;

Route::group(['prefix' => 'v2/warehouse'], function () {

    // Customers Section
    Route::controller(CustomerController::class)->group(function () {
        Route::get('/customers', 'getCustomersList');
        Route::get('/reps', 'getRepsList');
        Route::get('/customer/{id}', 'getCustomerDetails');
        Route::post('/customer/update/{id}', 'update')->middleware('wms.auth');
    });

    // Orders Section
    Route::controller(OrderController::class)->group(function () {
        Route::get('/orders', 'getOrdersList');
        Route::get('/order/{id}', 'getOrderDetails');
        Route::get('/order_items/{id}', 'getOrderItems');
        Route::post('/update_order', 'updateOrderStatus')->middleware('wms.auth');
        Route::post('/order/update-customer', 'customer_update');
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
