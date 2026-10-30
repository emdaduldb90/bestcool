<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ActivityController as AdminActivityController;
use App\Http\Controllers\Admin\BrandController as AdminBrandController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DeliveryZoneController as AdminDeliveryZoneController;
use App\Http\Controllers\Admin\InquiryController as AdminInquiryController;
use App\Http\Controllers\Admin\InventoryController as AdminInventoryController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\StaffController as AdminStaffController;
use App\Http\Controllers\Admin\SystemController as AdminSystemController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CmsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InquirySubmissionController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\Admin\TrackingAdminController;

Route::get('/', HomeController::class)->name('home');
Route::get('/shop', [CatalogController::class, 'index'])->name('shop.index');
Route::get('/product/{slug}', [CatalogController::class, 'show'])->name('product.show');
Route::get('/sitemap.xml', function () {
    return response()->stream(function () {
        $writeUrl = function (string $url, $updatedAt = null): void {
            echo '<url><loc>'.htmlspecialchars($url, ENT_XML1 | ENT_COMPAT, 'UTF-8').'</loc>';
            if ($updatedAt) echo '<lastmod>'.htmlspecialchars($updatedAt->toAtomString(), ENT_XML1 | ENT_COMPAT, 'UTF-8').'</lastmod>';
            echo '</url>'.PHP_EOL;
        };
        echo '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL.'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.PHP_EOL;
        \App\Models\ContentPage::query()->whereIn('slug', ['home', 'about-us', 'contact-us', 'privacy-policy', 'refund-policy', 'showroom', 'terms'])->orderBy('id')->chunk(100, function ($pages) use ($writeUrl) {
            foreach ($pages as $page) $writeUrl($page->slug === 'home' ? route('home') : route('page.show', $page->slug), $page->updated_at);
        });
        \App\Models\Category::query()->where('is_active', true)->orderBy('id')->chunk(500, function ($categories) use ($writeUrl) {
            foreach ($categories as $category) $writeUrl(route('shop.index', ['category' => $category->slug]), $category->updated_at);
        });
        \App\Models\Product::query()->active()->whereHas('category', fn ($query) => $query->where('is_active', true))->orderBy('id')->chunk(500, function ($products) use ($writeUrl) {
            foreach ($products as $product) $writeUrl(route('product.show', $product->slug), $product->updated_at);
        });
        echo '</urlset>'.PHP_EOL;
    }, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
})->name('sitemap');
Route::get('/robots.txt', fn () => response("User-agent: *\nAllow: /\nDisallow: /admin\nSitemap: ".route('sitemap')."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']))->name('robots');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/items/{product}', [CartController::class, 'store'])->name('cart.store');
Route::put('/cart/items/{product}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/items/{product}', [CartController::class, 'destroy'])->name('cart.destroy');
Route::get('/checkout', [CheckoutController::class, 'create'])->middleware('active-account')->name('checkout.create');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('active-account')->name('checkout.store');
Route::get('/order-success/{orderNumber}', [OrderController::class, 'success'])->name('order.success');

Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
Route::post('/wishlist/items/{product}', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
Route::post('/contact-message', [InquirySubmissionController::class, 'store'])->middleware('throttle:3,10')->name('inquiries.store');
Route::post('/tracking/consent', [TrackingController::class, 'consent'])->middleware('throttle:10,1')->name('tracking.consent');
Route::get('/tracking/config', [TrackingController::class, 'config'])->middleware('throttle:60,1')->name('tracking.config');
Route::post('/tracking/events', [TrackingController::class, 'event'])->middleware('throttle:120,1')->name('tracking.events');

Route::get('/account/login', [AuthController::class, 'login'])->name('login');
Route::post('/account/login', [AuthController::class, 'authenticate'])->middleware('throttle:5,1')->name('login.store');
Route::get('/account/register', [AuthController::class, 'register'])->name('register');
Route::post('/account/register', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('register.store');
Route::post('/account/logout', [AuthController::class, 'logout'])->name('logout');
Route::middleware('auth')->group(function () {
    Route::get('/account', [AccountController::class, 'index'])->middleware('active-account')->name('account.index');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', AdminDashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');
    Route::get('products/export', [AdminProductController::class, 'export'])->middleware('permission:products.manage')->name('products.export');
    Route::post('products/bulk', [AdminProductController::class, 'bulk'])->middleware('permission:products.manage')->name('products.bulk');
    Route::resource('products', AdminProductController::class)->middleware('permission:products.manage')->except('show');
    Route::get('brands', [AdminBrandController::class, 'index'])->middleware('permission:products.manage')->name('brands.index');
    Route::patch('brands', [AdminBrandController::class, 'update'])->middleware('permission:products.manage')->name('brands.update');
    Route::get('inventory', [AdminInventoryController::class, 'index'])->middleware('permission:inventory.manage')->name('inventory.index');
    Route::get('inventory/export', [AdminInventoryController::class, 'export'])->middleware('permission:inventory.manage')->name('inventory.export');
    Route::post('inventory/{product}', [AdminInventoryController::class, 'adjust'])->middleware('permission:inventory.manage')->name('inventory.adjust');
    Route::resource('categories', AdminCategoryController::class)->middleware('permission:categories.manage')->except('show');
    Route::resource('delivery-zones', AdminDeliveryZoneController::class)->middleware('permission:settings.manage')->except('show');
    Route::get('orders', [AdminOrderController::class, 'index'])->middleware('permission:orders.view')->name('orders.index');
    Route::post('orders/export', [AdminOrderController::class, 'export'])->middleware('permission:orders.view')->name('orders.export');
    Route::post('orders/bulk', [AdminOrderController::class, 'bulk'])->middleware('permission:orders.manage')->name('orders.bulk');
    Route::get('orders/{order}', [AdminOrderController::class, 'show'])->middleware('permission:orders.view')->name('orders.show');
    Route::get('orders/{order}/print/{document}', [AdminOrderController::class, 'print'])->middleware('permission:orders.view')->name('orders.print');
    Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->middleware('permission:orders.manage')->name('orders.status');
    Route::get('banners', [AdminBannerController::class, 'index'])->middleware('permission:content.manage')->name('banners.index');
    Route::post('banners', [AdminBannerController::class, 'store'])->middleware('permission:content.manage')->name('banners.store');
    Route::patch('banners/{banner}', [AdminBannerController::class, 'update'])->middleware('permission:content.manage')->name('banners.update');
    Route::delete('banners/{banner}', [AdminBannerController::class, 'destroy'])->middleware('permission:content.manage')->name('banners.destroy');
    Route::get('pages', [AdminPageController::class, 'index'])->middleware('permission:content.manage')->name('pages.index');
    Route::get('pages/{page}/edit', [AdminPageController::class, 'edit'])->middleware('permission:content.manage')->name('pages.edit');
    Route::put('pages/{page}', [AdminPageController::class, 'update'])->middleware('permission:content.manage')->name('pages.update');
    Route::post('pages/{page}/blocks', [AdminPageController::class, 'storeBlock'])->middleware('permission:content.manage')->name('pages.blocks.store');
    Route::patch('page-blocks/{block}', [AdminPageController::class, 'updateBlock'])->middleware('permission:content.manage')->name('pages.blocks.update');
    Route::post('page-blocks/{block}/move', [AdminPageController::class, 'moveBlock'])->middleware('permission:content.manage')->name('pages.blocks.move');
    Route::delete('page-blocks/{block}', [AdminPageController::class, 'destroyBlock'])->middleware('permission:content.manage')->name('pages.blocks.destroy');
    Route::get('customers', [AdminCustomerController::class, 'index'])->middleware('permission:customers.view')->name('customers.index');
    Route::get('customers/{customer}', [AdminCustomerController::class, 'show'])->middleware('permission:customers.view')->name('customers.show');
    Route::patch('customers/{customer}', [AdminCustomerController::class, 'update'])->middleware('permission:customers.manage')->name('customers.update');
    Route::get('inquiries', [AdminInquiryController::class, 'index'])->middleware('permission:customers.view')->name('inquiries.index');
    Route::get('inquiries/{inquiry}', [AdminInquiryController::class, 'show'])->middleware('permission:customers.view')->name('inquiries.show');
    Route::patch('inquiries/{inquiry}', [AdminInquiryController::class, 'update'])->middleware('permission:orders.manage')->name('inquiries.update');
    Route::delete('inquiries/{inquiry}', [AdminInquiryController::class, 'destroy'])->middleware('permission:customers.manage')->name('inquiries.destroy');
    Route::get('reports', [AdminReportController::class, 'index'])->middleware('permission:reports.view')->name('reports.index');
    Route::get('reports/export', [AdminReportController::class, 'export'])->middleware('permission:reports.view')->name('reports.export');
    Route::get('activity', [AdminActivityController::class, 'index'])->middleware('permission:activity.view')->name('activity.index');
    Route::get('staff', [AdminStaffController::class, 'index'])->middleware(['super-admin', 'permission:staff.manage'])->name('staff.index');
    Route::post('staff', [AdminStaffController::class, 'store'])->middleware(['super-admin', 'permission:staff.manage'])->name('staff.store');
    Route::patch('staff/{staff}', [AdminStaffController::class, 'update'])->middleware(['super-admin', 'permission:staff.manage'])->name('staff.update');
    Route::delete('staff/{staff}', [AdminStaffController::class, 'destroy'])->middleware(['super-admin', 'permission:staff.manage'])->name('staff.destroy');
    Route::get('system', [AdminSystemController::class, 'index'])->middleware('permission:system.view')->name('system.index');
    Route::post('system/clear-cache', [AdminSystemController::class, 'clearCache'])->middleware('permission:settings.manage')->name('system.clear-cache');
    Route::get('settings', [AdminSettingsController::class, 'edit'])->middleware('permission:settings.manage')->name('settings.edit');
    Route::put('settings', [AdminSettingsController::class, 'update'])->middleware('permission:settings.manage')->name('settings.update');
    Route::get('tracking/{section?}', [TrackingAdminController::class, 'show'])->middleware('permission:tracking.view')->where('section', 'overview|configuration|routing|events|analytics|links|deliveries|consent|health|ai')->name('tracking.show');
    Route::post('tracking/configuration', [TrackingAdminController::class, 'saveConfiguration'])->middleware('permission:tracking.manage')->name('tracking.configuration');
    Route::post('tracking/destinations/{destination}/test', [TrackingAdminController::class, 'testDestination'])->middleware(['permission:tracking.manage', 'throttle:3,10'])->name('tracking.destination.test');
    Route::put('tracking/routing', [TrackingAdminController::class, 'saveRouting'])->middleware('permission:tracking.manage')->name('tracking.routing');
    Route::put('tracking/events', [TrackingAdminController::class, 'saveEvents'])->middleware('permission:tracking.manage')->name('tracking.events.save');
    Route::get('tracking/links/export', [TrackingAdminController::class, 'exportLinks'])->middleware('permission:tracking.view')->name('tracking.links.export');
    Route::post('tracking/deliveries/{delivery}/retry', [TrackingAdminController::class, 'retryDelivery'])->middleware('permission:tracking.manage')->name('tracking.retry');
    Route::post('tracking/ai/reports', [TrackingAdminController::class, 'generateReport'])->middleware('permission:tracking.manage')->name('tracking.ai.generate');
    Route::get('tracking/ai/reports/{report}/download', [TrackingAdminController::class, 'downloadReport'])->middleware('permission:tracking.view')->name('tracking.ai.download');
    Route::get('profile', [AdminProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [AdminProfileController::class, 'update'])->name('profile.update');
});

Route::get('/{slug}', [CmsController::class, 'show'])
    ->where('slug', 'about-us|contact-us|privacy-policy|refund-policy|showroom|terms')
    ->name('page.show');
