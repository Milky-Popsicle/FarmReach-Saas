<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BuyerOrderController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\FarmerOrderController;
use App\Http\Controllers\FarmerProductController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\RecipeController;
use Illuminate\Support\Facades\Route;

// Redirect home to marketplace
Route::get('/', function () {
    return redirect()->route('marketplace');
});

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');

// Profile (All logged-in users)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [AuthController::class, 'editProfile'])->name('profile.edit');
    Route::post('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');
});

// Public Marketplace & Price Comparison
Route::get('/marketplace', [MarketplaceController::class, 'index'])->name('marketplace');
Route::get('/marketplace/product/{product}', [MarketplaceController::class, 'show'])->name('marketplace.show');
Route::get('/compare-prices', [MarketplaceController::class, 'comparePrices'])->name('marketplace.compare');

// Recipes (Recipe-Based Shopping)
Route::get('/recipes', [RecipeController::class, 'index'])->name('recipes.index');
Route::get('/recipes/{recipe}', [RecipeController::class, 'show'])->name('recipes.show');
Route::post('/recipes/add-batch-to-cart', [RecipeController::class, 'addBatchToCart'])->name('recipes.add-batch');

// Announcements
Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show'])->name('announcements.show');

// Cart & Orders
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
Route::post('/cart/update', [CartController::class, 'update'])->name('cart.update');
Route::post('/cart/remove', [CartController::class, 'remove'])->name('cart.remove');

Route::middleware('auth')->group(function () {
    Route::get('/checkout', [CartController::class, 'checkout'])->name('checkout');
    Route::post('/checkout', [CartController::class, 'placeOrder'])->name('checkout.place');
});

// Buyer & Peer Order Tracking (Dual view: Card & Table, Filter, Cancel, Reorder)
Route::middleware(['auth', 'role:buyer,farmer,admin'])->group(function () {
    Route::get('/buyer/orders', [BuyerOrderController::class, 'index'])->name('buyer.orders');
    Route::post('/buyer/orders/{order}/cancel', [BuyerOrderController::class, 'cancel'])->name('buyer.orders.cancel');
    Route::post('/buyer/orders/{order}/reorder', [BuyerOrderController::class, 'reorder'])->name('buyer.orders.reorder');
    Route::delete('/buyer/orders/{order}', [BuyerOrderController::class, 'destroy'])->name('buyer.orders.destroy');
    Route::post('/buyer/orders/bulk-delete', [BuyerOrderController::class, 'bulkDestroy'])->name('buyer.orders.bulkDelete');
});

// Farmer Portal
Route::middleware(['auth', 'role:farmer,admin'])->prefix('farmer')->name('farmer.')->group(function () {
    Route::get('/marketplace', [FarmerProductController::class, 'marketplace'])->name('marketplace');
    Route::get('/products', [FarmerProductController::class, 'index'])->name('products');
    Route::post('/products', [FarmerProductController::class, 'store'])->name('products.store');
    Route::put('/products/{product}', [FarmerProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [FarmerProductController::class, 'destroy'])->name('products.destroy');
    Route::post('/products/{product}/toggle-stock', [FarmerProductController::class, 'toggleStock'])->name('products.toggle');

    Route::get('/orders', [FarmerOrderController::class, 'index'])->name('orders');
    Route::post('/orders/{order}/status', [FarmerOrderController::class, 'updateStatus'])->name('orders.status');
});

// Admin Portal
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::delete('/users/{user}', [AdminController::class, 'deleteUser'])->name('users.delete');

    Route::get('/announcements', [AdminController::class, 'announcements'])->name('announcements');
    Route::post('/announcements', [AdminController::class, 'storeAnnouncement'])->name('announcements.store');
    Route::put('/announcements/{announcement}', [AdminController::class, 'updateAnnouncement'])->name('announcements.update');
    Route::delete('/announcements/{announcement}', [AdminController::class, 'deleteAnnouncement'])->name('announcements.delete');

    Route::get('/recipes', [AdminController::class, 'recipes'])->name('recipes');
    Route::post('/recipes', [AdminController::class, 'storeRecipe'])->name('recipes.store');
    Route::delete('/recipes/{recipe}', [AdminController::class, 'deleteRecipe'])->name('recipes.delete');
});
