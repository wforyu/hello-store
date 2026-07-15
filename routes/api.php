<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\NotificationController as ApiNotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\ShippingController;
use App\Http\Controllers\Api\WishlistController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/home', [HomeController::class, 'index']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/categories', [ProductController::class, 'categories']);
Route::get('/settings/ppn', [SettingsController::class, 'ppn']);
Route::get('/settings/member-tiers', [SettingsController::class, 'memberTiers']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    // Cart
    Route::get('/cart', [CartController::class, 'index']);
    Route::get('/cart/count', [CartController::class, 'count']);
    Route::post('/cart/add/{product}', [CartController::class, 'add']);
    Route::post('/cart/update', [CartController::class, 'update']);
    Route::delete('/cart/remove/{productId}', [CartController::class, 'remove']);

    // Notifications
    Route::get('/notifications', [ApiNotificationController::class, 'index']);
    Route::get('/notifications/unread', [ApiNotificationController::class, 'unreadCount']);
    Route::post('/notifications/{notification}/read', [ApiNotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [ApiNotificationController::class, 'markAllAsRead']);

    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::post('/orders/{order}/payment', [OrderController::class, 'paymentUpload']);
    Route::post('/orders/{order}/confirm', [OrderController::class, 'confirmReceived']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::post('/orders/{order}/reorder', [OrderController::class, 'reorder']);

    // Addresses
    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::put('/addresses/{address}', [AddressController::class, 'update']);
    Route::delete('/addresses/{address}', [AddressController::class, 'destroy']);

    // Wishlist
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist/toggle/{product}', [WishlistController::class, 'toggle']);

    // Reviews
    Route::post('/products/{product}/review', [ReviewController::class, 'store']);

    // Coupons
    Route::post('/coupons/validate', [CouponController::class, 'check']);

    // Shipping
    Route::post('/shipping/rates', [ShippingController::class, 'rates']);
});
