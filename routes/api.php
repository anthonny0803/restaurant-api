<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Client\MenuItemController as ClientMenuItemController;
use App\Http\Controllers\GuestReservationController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\PreOrderController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TableController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/complete-account', [AuthController::class, 'completeAccount']);
    });
});

Route::get('menu-items', [ClientMenuItemController::class, 'index']);
Route::post('guest/reservations', [GuestReservationController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('tables', TableController::class);
    Route::apiResource('menu-items', MenuItemController::class)->except(['index']);

    Route::prefix('reservations')->group(function () {
        Route::post('/', [ReservationController::class, 'store']);
        Route::get('/', [ReservationController::class, 'index']);
        Route::get('/{reservation}', [ReservationController::class, 'show']);
        Route::post('/{reservation}/cancel', [ReservationController::class, 'cancel']);

        Route::prefix('/{reservation}/pre-orders')->scopeBindings()->group(function () {
            Route::get('/', [PreOrderController::class, 'index']);
            Route::post('/', [PreOrderController::class, 'store']);
            Route::delete('/{reservationItem}', [PreOrderController::class, 'destroy']);
        });
    });
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin/analytics')->group(function () {
    Route::get('/occupancy', [AnalyticsController::class, 'occupancy']);
    Route::get('/revenue', [AnalyticsController::class, 'revenue']);
    Route::get('/top-menu-items', [AnalyticsController::class, 'topMenuItems']);
});

Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);
