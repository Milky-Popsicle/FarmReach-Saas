<?php

use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public catalog and recipe content. Responses use explicit field whitelists
// and never include farmer phone numbers.
Route::get('/products', [ApiController::class, 'products']);
Route::get('/products/{product}', [ApiController::class, 'productDetails']);
Route::get('/recipes', [ApiController::class, 'recipes']);
Route::get('/recipes/{recipe}/match', [ApiController::class, 'recipeMatch']);

// Authenticated data, scoped to the viewer in ApiController.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/orders', [ApiController::class, 'orders']);
    Route::get('/bulk-orders', [ApiController::class, 'bulkOrders']);
    Route::get('/profiles/{user}', [ApiController::class, 'profile']);
    Route::get('/farmer-locations', [ApiController::class, 'farmerLocations']);
});

// Global business analytics and audit data are administrator-only.
Route::middleware(['auth:sanctum', 'can:viewAdminAnalytics'])->group(function () {
    Route::get('/sales-data', [ApiController::class, 'salesData']);
    Route::get('/top-products', [ApiController::class, 'topProducts']);
    Route::get('/recent-activities', [ApiController::class, 'recentActivities']);
});
