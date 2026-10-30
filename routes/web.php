<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DeliveryZoneController as AdminDeliveryZoneController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CmsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\WishlistController;

Route::get('/', HomeController::class)->name('home');
Route::get('/shop', [CatalogController::class, 'index'])->name('shop.index');
Route::get('/product/{slug}', [CatalogController::class, 'show'])->name('product.show');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/items/{product}', [CartController::class, 'store'])->name('cart.store');
Route::put('/cart/items/{product}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/items/{product}', [CartController::class, 'destroy'])->name('cart.destroy');
Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/order-success/{orderNumber}', [OrderController::class, 'success'])->name('order.success');

Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
Route::post('/wishlist/items/{product}', [WishlistController::class, 'toggle'])->name('wishlist.toggle');

Route::get('/account/login', [AuthController::class, 'login'])->name('login');
Route::post('/account/login', [AuthController::class, 'authenticate'])->name('login.store');
Route::get('/account/register', [AuthController::class, 'register'])->name('register');
Route::post('/account/register', [AuthController::class, 'store'])->name('register.store');
Route::post('/account/logout', [AuthController::class, 'logout'])->name('logout');
Route::middleware('auth')->group(function () {
    Route::get('/account', [AccountController::class, 'index'])->name('account.index');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::resource('products', AdminProductController::class)->except('show');
    Route::resource('categories', AdminCategoryController::class)->except('show');
    Route::resource('delivery-zones', AdminDeliveryZoneController::class)->except('show');
    Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.status');
    Route::get('banners', [AdminBannerController::class, 'index'])->name('banners.index');
    Route::post('banners', [AdminBannerController::class, 'store'])->name('banners.store');
    Route::patch('banners/{banner}', [AdminBannerController::class, 'update'])->name('banners.update');
    Route::delete('banners/{banner}', [AdminBannerController::class, 'destroy'])->name('banners.destroy');
    Route::get('settings', [AdminSettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [AdminSettingsController::class, 'update'])->name('settings.update');
});

Route::get('/{slug}', [CmsController::class, 'show'])
    ->where('slug', 'about-us|contact-us|privacy-policy|refund-policy|showroom|terms')
    ->name('page.show');
