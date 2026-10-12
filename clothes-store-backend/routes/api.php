<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\AuthController;

// Public Routes
Route::prefix('v1')->group(function () {
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{slug}', [ProductController::class, 'show']);
    Route::post('/checkout', [OrderController::class, 'store']);
    
    // Admin Auth
    Route::post('/admin/login', [AuthController::class, 'login']);

    // Protected Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/admin/logout', [AuthController::class, 'logout']);
        Route::get('/admin/user', function (Request $request) {
            return $request->user();
        });
        
        Route::get('/admin/dashboard', [\App\Http\Controllers\DashboardController::class, 'index']);
        
        // Products
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);
        
        // Categories
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

        // Orders
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::put('/orders/{order}', [OrderController::class, 'update']);
        Route::delete('/orders/{order}', [OrderController::class, 'destroy']);
        
        // Customers
        Route::apiResource('customers', CustomerController::class);
    });
});
