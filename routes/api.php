<?php

use App\Http\Controllers\Api\V1\Admin\AdminAuthController;
use App\Http\Controllers\Api\V1\Admin\AdminController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\TrackerUserController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\EmployeeSelfController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\TrackingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::middleware('throttle:10,1')->post('/login', [AuthController::class, 'login']);

    Route::prefix('admin/auth')->middleware('web')->group(function (): void {
        Route::post('/login', [AdminAuthController::class, 'login']);
        Route::middleware('auth:web')->group(function (): void {
            Route::post('/logout', [AdminAuthController::class, 'logout']);
            Route::get('/me', [AdminAuthController::class, 'me']);
        });
    });

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
            Route::get('/dashboard/summary', [DashboardController::class, 'summary'])->middleware('permission:dashboard.view');
            Route::get('/employees', [AdminController::class, 'employees']);
            Route::get('/devices', [AdminController::class, 'devices']);
            Route::get('/tracking-policies', [AdminController::class, 'trackingPolicies']);
            Route::get('/audit-logs', [AdminController::class, 'auditLogs']);
            Route::get('/live-locations', [AdminController::class, 'liveLocations']);

            Route::get('/tracker-users', [TrackerUserController::class, 'index'])->middleware('permission:tracker_users.view');
            Route::post('/tracker-users', [TrackerUserController::class, 'store'])->middleware('permission:tracker_users.create');
            Route::get('/tracker-users/{tracker_user}', [TrackerUserController::class, 'show'])->middleware('permission:tracker_users.view');
            Route::put('/tracker-users/{tracker_user}', [TrackerUserController::class, 'update'])->middleware('permission:tracker_users.edit');
            Route::delete('/tracker-users/{tracker_user}', [TrackerUserController::class, 'destroy'])->middleware('permission:tracker_users.delete');

            Route::get('/admin-users', [AdminUserController::class, 'index'])->middleware('permission:admins.view');
            Route::post('/admin-users', [AdminUserController::class, 'store'])->middleware('super-admin');
            Route::get('/admin-users/{admin_user}', [AdminUserController::class, 'show'])->middleware('permission:admins.view');
            Route::put('/admin-users/{admin_user}/permissions', [AdminUserController::class, 'updatePermissions'])->middleware('super-admin');
        });
    });
});
