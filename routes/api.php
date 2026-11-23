<?php

use App\Http\Controllers\Api\V1\Admin\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\EmployeeSelfController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\TrackingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::middleware('throttle:10,1')->post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::post('/device/register', [DeviceController::class, 'register']);
        Route::post('/device/revoke', [DeviceController::class, 'revoke']);

        Route::post('/tracking/start', [TrackingController::class, 'start']);
        Route::post('/tracking/stop', [TrackingController::class, 'stop']);
        Route::get('/tracking/status', [TrackingController::class, 'status']);

        Route::post('/locations/batch', [LocationController::class, 'batch']);

        Route::get('/me/location-history', [EmployeeSelfController::class, 'locationHistory']);
        Route::get('/me/sessions', [EmployeeSelfController::class, 'sessions']);
        Route::get('/me/reports', [EmployeeSelfController::class, 'reports']);

        Route::prefix('admin')->middleware('admin')->group(function (): void {
            Route::get('/employees', [AdminController::class, 'employees']);
            Route::get('/devices', [AdminController::class, 'devices']);
            Route::get('/tracking-policies', [AdminController::class, 'trackingPolicies']);
            Route::get('/audit-logs', [AdminController::class, 'auditLogs']);
            Route::get('/live-locations', [AdminController::class, 'liveLocations']);
        });
    });
});
