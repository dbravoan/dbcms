<?php

declare(strict_types=1);

use App\Http\Middleware\CheckPermission;
use Illuminate\Support\Facades\Route;
use Modules\Shop\Http\Controllers\Admin\InquiriesGetController;
use Modules\Shop\Http\Controllers\Admin\InquiryReviewPutController;
use Modules\Shop\Http\Controllers\Admin\LegacyPaymentReportGetController;
use Modules\Shop\Http\Controllers\Admin\OrderDeleteController;
use Modules\Shop\Http\Controllers\Admin\OrderGetController;
use Modules\Shop\Http\Controllers\Admin\OrdersGetController;
use Modules\Shop\Http\Controllers\Admin\OrderStatusPutController;
use Modules\Shop\Http\Controllers\Admin\ProductCreateGetController;
use Modules\Shop\Http\Controllers\Admin\ProductDeleteController;
use Modules\Shop\Http\Controllers\Admin\ProductEditGetController;
use Modules\Shop\Http\Controllers\Admin\ProductPostController;
use Modules\Shop\Http\Controllers\Admin\ProductPutController;
use Modules\Shop\Http\Controllers\Admin\ProductsGetController;
use Modules\Shop\Http\Controllers\Admin\ShopCategoriesGetController;
use Modules\Shop\Http\Controllers\Admin\ShopCategoryDeleteController;
use Modules\Shop\Http\Controllers\Admin\ShopCategoryPostController;
use Modules\Shop\Http\Controllers\Admin\ShopCategoryPutController;
use Modules\Shop\Http\Controllers\Admin\ShopSettingsGetController;

Route::middleware([CheckPermission::class.':manage:shop'])->prefix('shop')->name('shop.')->group(function (): void {
    // Products CRUD
    Route::get('/products', ProductsGetController::class)->name('products.index');
    Route::get('/products/create', ProductCreateGetController::class)->name('products.create');
    Route::post('/products', ProductPostController::class)->name('products.store');
    Route::get('/products/{product}/edit', ProductEditGetController::class)->name('products.edit');
    Route::put('/products/{product}', ProductPutController::class)->name('products.update');
    Route::delete('/products/{product}', ProductDeleteController::class)->name('products.destroy');

    // Categories
    Route::get('/categories', ShopCategoriesGetController::class)->name('categories.index');
    Route::post('/categories', ShopCategoryPostController::class)->name('categories.store');
    Route::put('/categories/{category}', ShopCategoryPutController::class)->name('categories.update');
    Route::delete('/categories/{category}', ShopCategoryDeleteController::class)->name('categories.destroy');

    // Orders
    Route::get('/orders/legacy-payments', LegacyPaymentReportGetController::class)->name('orders.legacy-payments');
    Route::get('/orders', OrdersGetController::class)->name('orders.index');
    Route::get('/orders/{order}', OrderGetController::class)->name('orders.show');
    Route::put('/orders/{order}/status', OrderStatusPutController::class)->name('orders.update-status');
    Route::delete('/orders/{order}', OrderDeleteController::class)->name('orders.destroy');
    Route::get('/inquiries', InquiriesGetController::class)->name('inquiries.index');
    Route::put('/inquiries/{inquiry}/review', InquiryReviewPutController::class)->name('inquiries.review');

    // Settings (GET redirects to the generic module settings page; the PUT is
    // handled by App\Http\Controllers\Admin\ModuleSettings\ModuleSettingsPutController)
    Route::get('/settings', ShopSettingsGetController::class)->name('settings');
});
