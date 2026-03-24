<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\MenuItemController as AdminMenuItemController;
use App\Http\Controllers\Admin\ReservationController as AdminReservationController;
use App\Http\Controllers\Admin\RestaurantSettingController;
use App\Http\Controllers\Admin\TableController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Client\GuestReservationController;
use App\Http\Controllers\Client\MenuItemController as ClientMenuItemController;
use App\Http\Controllers\Client\PreOrderController;
use App\Http\Controllers\Client\ReservationController as ClientReservationController;
use App\Http\Controllers\StripeWebhookController;
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
Route::get('reservations/available-tables', [ClientReservationController::class, 'availableTables']);
Route::post('guest/reservations', [GuestReservationController::class, 'store']);

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::apiResource('tables', TableController::class);
    Route::apiResource('menu-items', AdminMenuItemController::class);

    Route::prefix('reservations')->group(function () {
        Route::get('/', [AdminReservationController::class, 'index']);
        Route::get('/{reservation}', [AdminReservationController::class, 'show']);
    });

    Route::get('settings', [RestaurantSettingController::class, 'show']);
    Route::patch('settings', [RestaurantSettingController::class, 'update']);

    Route::prefix('analytics')->group(function () {
        Route::get('/occupancy', [AnalyticsController::class, 'occupancy']);
        Route::get('/revenue', [AnalyticsController::class, 'revenue']);
        Route::get('/top-menu-items', [AnalyticsController::class, 'topMenuItems']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('reservations')->group(function () {
        Route::post('/', [ClientReservationController::class, 'store']);
        Route::get('/', [ClientReservationController::class, 'index']);
        Route::get('/{reservation}', [ClientReservationController::class, 'show']);
        Route::post('/{reservation}/cancel', [ClientReservationController::class, 'cancel']);

        Route::prefix('/{reservation}/pre-orders')->scopeBindings()->group(function () {
            Route::get('/', [PreOrderController::class, 'index']);
            Route::post('/', [PreOrderController::class, 'store']);
            Route::delete('/{reservationItem}', [PreOrderController::class, 'destroy']);
        });
    });
});

Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->middleware('throttle:60,1');
