<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StoreController::class, 'home'])->name('home');

Route::get('/products', [StoreController::class, 'products'])->name('products.index');
Route::get('/product/{slug}', [StoreController::class, 'productDetail'])->name('products.show');
Route::get('/products/suggestions', [StoreController::class, 'suggestions'])->name('products.suggestions');

Route::get('/cart', [StoreController::class, 'cartIndex'])->name('cart.index');
Route::post('/cart/add/{product}', [StoreController::class, 'cartAdd'])->name('cart.add');
Route::post('/cart/update', [StoreController::class, 'cartUpdate'])->name('cart.update');
Route::post('/cart/remove/{productId}', [StoreController::class, 'cartRemove'])->name('cart.remove');

Route::middleware('auth')->group(function () {
    Route::get('/account', [AccountController::class, 'dashboard'])->name('account.dashboard');

    Route::get('/checkout', [StoreController::class, 'checkout'])->name('checkout');
    Route::post('/checkout/place-order', [StoreController::class, 'placeOrder'])->name('checkout.place');

    Route::get('/orders', [StoreController::class, 'orders'])->name('orders.index');
    Route::get('/orders/{order}', [StoreController::class, 'orderShow'])->name('orders.show');
    Route::post('/orders/{order}/payment', [StoreController::class, 'paymentUpload'])->name('orders.payment');
    Route::post('/orders/{order}/confirm-received', [StoreController::class, 'confirmReceived'])->name('orders.confirm-received');
    Route::post('/orders/{order}/cancel', [StoreController::class, 'cancelOrder'])->name('orders.cancel');
    Route::get('/orders/{order}/print', [StoreController::class, 'printReceipt'])->name('orders.print');

    Route::post('/product/{product}/review', [StoreController::class, 'reviewStore'])->name('products.review');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('addresses', AddressController::class)->except(['show']);
});

Route::middleware(['auth', 'can:access-pos'])->group(function () {
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::get('/pos/search', [PosController::class, 'search'])->name('pos.search');
    Route::get('/pos/customers', [PosController::class, 'customers'])->name('pos.customers');
    Route::post('/pos/add', [PosController::class, 'add'])->name('pos.add');
    Route::post('/pos/update', [PosController::class, 'update'])->name('pos.update');
    Route::post('/pos/remove', [PosController::class, 'remove'])->name('pos.remove');
    Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
    Route::post('/pos/hold', [PosController::class, 'holdOrder'])->name('pos.hold');
    Route::get('/pos/holds', [PosController::class, 'recallOrders'])->name('pos.holds');
    Route::get('/pos/hold/{id}', [PosController::class, 'recallOrder'])->name('pos.recall');
    Route::delete('/pos/hold/{id}', [PosController::class, 'deleteHold'])->name('pos.hold-delete');
    Route::get('/pos/history', [PosController::class, 'history'])->name('pos.history');
    Route::get('/pos/print/{order}', [PosController::class, 'printReceipt'])->name('pos.print');

    Route::get('/orders/{order}/print-admin', [StoreController::class, 'printReceiptAdmin'])->name('orders.print-admin');
});

require __DIR__.'/auth.php';
