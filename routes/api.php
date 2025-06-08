<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\NotificationController;
use Illuminate\Support\Facades\Redis;

Route::group(['prefix' => 'notifications'], function () {
    Route::post('/publish', [NotificationController::class, 'publish']);
    Route::get('/recent', [NotificationController::class, 'recent']);
    Route::get('/summary', [NotificationController::class, 'summary']);
    Route::put('/{id}/status', [NotificationController::class, 'updateStatus']);
});

