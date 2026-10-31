<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductExportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');

    Route::post('logout', [AuthController::class, 'logout'])
        ->middleware('auth:api');

    Route::middleware(['auth:api', 'active'])->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });
});

Route::middleware(['auth:api', 'active'])->group(function () {

    /* ---- qualquer usuário autenticado ---- */

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::put('profile', [ProfileController::class, 'update']);
    Route::put('profile/password', [ProfileController::class, 'password']);

    Route::prefix('dashboard')->group(function () {
        Route::get('summary', [DashboardController::class, 'summary']);
        Route::get('top-products', [DashboardController::class, 'topProducts']);
        Route::get('sales-by-day', [DashboardController::class, 'salesByDay']);
        Route::get('low-stock', [DashboardController::class, 'lowStock']);
    });

    Route::get('products/export', ProductExportController::class);

    Route::apiResource('categories', CategoryController::class)->except(['destroy']);
    Route::apiResource('suppliers', SupplierController::class)->except(['destroy']);
    Route::apiResource('products', ProductController::class)->except(['destroy']);

    Route::apiResource('purchases', PurchaseController::class)
        ->only(['index', 'store', 'show', 'destroy']);

    Route::apiResource('sales', SaleController::class)->only(['index', 'store', 'show']);

    /* ---- só administrador ---- */

    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', UserController::class)->except(['show', 'destroy']);

        Route::delete('categories/{category}', [CategoryController::class, 'destroy']);
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy']);
        Route::delete('products/{product}', [ProductController::class, 'destroy']);

        Route::post('purchases/{purchase}/confirm', [PurchaseController::class, 'confirm']);
        Route::post('purchases/{purchase}/cancel', [PurchaseController::class, 'cancel']);
        Route::post('sales/{sale}/cancel', [SaleController::class, 'cancel']);
    });
});
