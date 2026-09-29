<?php

namespace App\Http\Controllers\Api\V2;

use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v2/trips/', 'middleware' => ['app_language']], function () {

    Route::middleware(['auth:sanctum', 'driver'])->group(function () {
        Route::get('get-trips-history', 'App\Http\Controllers\Api\V2\Trip\DriverController@getTripsHistory');
        Route::get('get-active-trip', 'App\Http\Controllers\Api\V2\Trip\DriverController@getActiveTrip');
        Route::post('update-checkpoint', 'App\Http\Controllers\Api\V2\Trip\DriverController@updateCheckpoint');
        Route::post('update-checkpoint-notes', 'App\Http\Controllers\Api\V2\Trip\DriverController@updateCheckpointNotes');
        Route::post('update-trip', 'App\Http\Controllers\Api\V2\Trip\DriverController@updateTrip');
        Route::post('assign-trip-by-qr', 'App\Http\Controllers\Api\V2\Trip\DriverController@assignTruckByQrScan');
        Route::get('notifications', 'App\Http\Controllers\Api\V2\Trip\DriverController@assignTripByQrScan');

    });
    Route::get('/get-available-vehicles', 'App\Http\Controllers\Api\V2\Trip\DriverController@getAvailableVehicles')->name('getAvailableVehicles');
});

Route::group(['prefix' => 'v2/driver/', 'middleware' => ['app_language']], function () {

    Route::middleware('auth:sanctum', 'driver')->group(function () {
        Route::get('notifications', 'App\Http\Controllers\Api\V2\Trip\DriverNotificationController@getAllNotification');
    });
});

Route::get('/get-user-coordinates/{id}', 'App\Http\Controllers\Api\V2\Trip\DriverController@getLongLatitude')->name('test');

Route::fallback(function () {
    return response()->json([
        'data' => [],
        'success' => false,
        'status' => 404,
        'message' => 'Invalid Route'
    ], 404);
});
