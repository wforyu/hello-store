<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductExportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StoreController::class, 'home'])->name('home');

Route::get('/privacy-policy', fn () => view('store.privacy-policy'))->name('privacy-policy');

Route::get('/products', [StoreController::class, 'products'])->name('products.index');
Route::get('/product/{slug}', [StoreController::class, 'productDetail'])->name('products.show');
Route::get('/products/suggestions', [StoreController::class, 'suggestions'])->name('products.suggestions');

Route::get('/cart', [StoreController::class, 'cartIndex'])->name('cart.index');
Route::get('/cart/count', [StoreController::class, 'cartCount'])->name('cart.count');
Route::post('/cart/add/{product}', [StoreController::class, 'cartAdd'])->name('cart.add');
Route::post('/cart/add-bundle/{bundle}', [StoreController::class, 'cartAddBundle'])->name('cart.add-bundle');
Route::post('/cart/update', [StoreController::class, 'cartUpdate'])->name('cart.update');
Route::post('/cart/remove/{key}', [StoreController::class, 'cartRemove'])->name('cart.remove');

Route::post('/compare/toggle/{product}', [StoreController::class, 'compareToggle'])->name('products.compare.toggle');
Route::get('/compare', [StoreController::class, 'compareIndex'])->name('products.compare');

Route::get('/bundles', [StoreController::class, 'bundles'])->name('products.bundles');
Route::get('/bundles/{slug}', [StoreController::class, 'bundleDetail'])->name('products.bundle-detail');

Route::middleware('auth')->group(function () {
    Route::post('/wishlist/toggle/{product}', [StoreController::class, 'wishlistToggle'])->name('wishlist.toggle');
    Route::get('/wishlist', [StoreController::class, 'wishlistIndex'])->name('wishlist.index');

    Route::get('/account', [AccountController::class, 'dashboard'])->name('account.dashboard');

    Route::get('/checkout', [StoreController::class, 'checkout'])->name('checkout');
    Route::post('/checkout/place-order', [StoreController::class, 'placeOrder'])->name('checkout.place');
    Route::post('/checkout/apply-coupon', [StoreController::class, 'applyCoupon'])->name('checkout.apply-coupon');

    Route::get('/orders', [StoreController::class, 'orders'])->name('orders.index');
    Route::get('/orders/{order}', [StoreController::class, 'orderShow'])->name('orders.show');
    Route::post('/orders/{order}/payment', [StoreController::class, 'paymentUpload'])->name('orders.payment');
    Route::post('/orders/{order}/confirm-received', [StoreController::class, 'confirmReceived'])->name('orders.confirm-received');
    Route::post('/orders/{order}/cancel', [StoreController::class, 'cancelOrder'])->name('orders.cancel');
    Route::get('/orders/{order}/print', [StoreController::class, 'printReceipt'])->name('orders.print');
    Route::post('/orders/{order}/reorder', [StoreController::class, 'reorder'])->name('orders.reorder');
    Route::get('/orders/{order}/download/{product}', [StoreController::class, 'downloadDigital'])->name('orders.download');

    Route::post('/product/{product}/review', [StoreController::class, 'reviewStore'])->name('products.review');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('addresses', AddressController::class)->except(['show']);

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread');
        Route::get('/json', [NotificationController::class, 'indexJson'])->name('json');
        Route::post('/{notification}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
        Route::post('/{notification}/read-json', [NotificationController::class, 'markAsReadJson'])->name('read-json');
    });
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
    Route::post('/pos/scan', [PosController::class, 'scanBarcode'])->name('pos.scan');
    Route::get('/pos/history', [PosController::class, 'history'])->name('pos.history');
    Route::get('/pos/print/{order}', [PosController::class, 'printReceipt'])->name('pos.print');
    Route::post('/pos/shift/expense', [PosController::class, 'addExpense'])->name('pos.shift.expense');
    Route::delete('/pos/shift/expense/{shiftExpense}', [PosController::class, 'deleteExpense'])->name('pos.shift.expense-delete');
    Route::post('/pos/shift/open', [PosController::class, 'openShift'])->name('pos.shift.open');
    Route::post('/pos/shift/close', [PosController::class, 'closeShift'])->name('pos.shift.close');
    Route::get('/pos/shift/history', [PosController::class, 'shiftHistory'])->name('pos.shift.history');

    Route::get('/orders/{order}/print-admin', [StoreController::class, 'printReceiptAdmin'])->name('orders.print-admin');
});

Route::middleware(['auth', 'can:admin'])->group(function () {
    Route::get('/admin/reports/export', [ReportController::class, 'export'])
        ->name('admin.reports.export');
    Route::get('/admin/products/export', [ProductExportController::class, 'export'])
        ->name('admin.products.export');
    Route::get('/admin/barcode', [BarcodeController::class, 'index'])->name('barcode.index');
    Route::post('/admin/barcode/generate', [BarcodeController::class, 'generate'])->name('barcode.generate');
    Route::get('/admin/barcode/product/{product}', [BarcodeController::class, 'generateForProduct'])->name('barcode.product');

    Route::post('/orders/{order}/refund', [StoreController::class, 'processRefund'])->name('orders.refund');
});

require __DIR__.'/auth.php';
