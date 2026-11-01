<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1/trips/', 'middleware' => ['app_language']], function () {

    Route::middleware(['auth:store_api', 'driver'])->group(function () {
        Route::get('get-trips-history', 'App\Http\Controllers\Api\V1\Trip\DriverController@getTripsHistory');
        Route::get('get-active-trip', 'App\Http\Controllers\Api\V1\Trip\DriverController@getActiveTrip');
        Route::post('update-checkpoint', 'App\Http\Controllers\Api\V1\Trip\DriverController@updateCheckpoint');
        Route::post('update-checkpoint-notes', 'App\Http\Controllers\Api\V1\Trip\DriverController@updateCheckpointNotes');
        Route::post('update-trip', 'App\Http\Controllers\Api\V1\Trip\DriverController@updateTrip');
        Route::post('assign-trip-by-qr', 'App\Http\Controllers\Api\V1\Trip\DriverController@assignTruckByQrScan');
        Route::get('notifications', 'App\Http\Controllers\Api\V1\Trip\DriverNotificationController@getAllNotification');
        Route::get('get-available-vehicles', 'App\Http\Controllers\Api\V1\Trip\DriverController@getAvailableVehicles')->name('getAvailableVehicles');

    });
});

Route::group(['prefix' => 'v1/driver/', 'middleware' => ['app_language']], function () {

    Route::middleware('auth:store_api', 'driver')->group(function () {
        Route::get('notifications', 'App\Http\Controllers\Api\V1\Trip\DriverNotificationController@getAllNotification');
    });
});

Route::get('/get-user-coordinates/{id}', 'App\Http\Controllers\Api\V1\Trip\DriverController@getLongLatitude')->name('test')->middleware(['auth:store_api', 'driver']);

Route::fallback(function () {
    return response()->json([
        'data' => [],
        'success' => false,
        'status' => 404,
        'message' => __('api.common.invalid_route')
    ], 404);
});
