<?php

use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// FarmReach Public & Integrated JSON APIs (as per specifications)
Route::get('/products', [ApiController::class, 'products']);
Route::get('/products/{product}', [ApiController::class, 'productDetails']);

Route::get('/orders', [ApiController::class, 'orders']);
Route::get('/profiles/{user}', [ApiController::class, 'profile']);

Route::get('/sales-data', [ApiController::class, 'salesData']);
Route::get('/top-products', [ApiController::class, 'topProducts']);
Route::get('/recent-activities', [ApiController::class, 'recentActivities']);

Route::get('/recipes', [ApiController::class, 'recipes']);
Route::get('/recipes/{recipe}/match', [ApiController::class, 'recipeMatch']);

Route::get('/bulk-orders', [ApiController::class, 'bulkOrders']);
Route::get('/farmer-locations', [ApiController::class, 'farmerLocations']);
