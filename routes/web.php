<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Catalog\ProductImageController;
use App\Http\Controllers\Catalog\ProductPageController;
use App\Http\Middleware\AffiliateRedirectRateLimit;

Route::get('/', function () {
    app()->setLocale('en');

    return view('welcome');
});

Route::get('/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['ar', 'en'], true), 404);
    app()->setLocale($locale);

    return view('welcome');
})->whereIn('locale', ['ar', 'en']);

Route::middleware(['auth', 'can:manage-products'])->get('/admin/products', function () {
    return redirect()->route('admin.products.index', ['locale' => app()->getLocale()]);
});

Route::prefix('{locale}')->whereIn('locale', ['ar', 'en'])->group(function (): void {
    Route::get('/products/{slug}', [ProductPageController::class, 'show'])->name('products.show');
    Route::get('/go/{slug}', [ProductPageController::class, 'redirect'])
        ->middleware(AffiliateRedirectRateLimit::class)
        ->name('products.go');

    Route::middleware(['auth', 'can:manage-products'])->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [AdminProductController::class, 'create'])->name('products.create');
        Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');
        Route::get('/products/{product}/preview', [AdminProductController::class, 'preview'])->name('products.preview');
        Route::post('/products/{product}/publish', [AdminProductController::class, 'publish'])->name('products.publish');
        Route::post('/products/{product}/hide', [AdminProductController::class, 'hide'])->name('products.hide');
    });
});

Route::get('/media/products/{image}', [ProductImageController::class, 'show'])->name('products.media');
