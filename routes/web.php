<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FilterController;
use App\Http\Controllers\FilterGroupController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware('admin.auth')->name('dashboard');

Route::middleware('admin.auth')->group(function () {
    Route::get('/filters', [FilterGroupController::class, 'index'])->name('filters.index');
    Route::post('/filter-groups', [FilterGroupController::class, 'store'])->name('filter-groups.store');
    Route::post('/filters', [FilterController::class, 'store'])->name('filters.store');
    Route::get('/filters/{filter}/search', [FilterController::class, 'search'])->name('filters.search');
    Route::get('/filters/{filter}', [FilterController::class, 'show'])->name('filters.show');
    Route::post('/filters/{filter}/sync', [FilterController::class, 'sync'])->name('filters.sync');
    Route::post('/filters/{filter}/sync-categories', [FilterController::class, 'syncCategories'])->name('filters.sync-categories');
    Route::get('/category-product-ids', [FilterController::class, 'categoryProductIds'])->name('category.product-ids');

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/{categoryId}', [CategoryController::class, 'show'])->name('categories.show');
    Route::get('/categories/{categoryId}/product-filters', [CategoryController::class, 'getProductFilterIndex'])->name('categories.product-filters');
    Route::get('/products/{productId}/categories', [CategoryController::class, 'getProductCategories'])->name('products.categories');
    Route::post('/products/{productId}/sync-categories', [CategoryController::class, 'syncProductCategories'])->name('products.sync-categories');
    Route::get('/products/{productId}/filters', [CategoryController::class, 'getProductFilters'])->name('products.filters');
    Route::post('/products/{productId}/sync-filters', [CategoryController::class, 'syncProductFilters'])->name('products.sync-filters');
    Route::post('/products/{productId}/suggest-filters', [CategoryController::class, 'suggestFilters'])->name('products.suggest-filters');
    Route::post('/products/{productId}/create-filter', [CategoryController::class, 'createAndAssignFilter'])->name('products.create-filter');
});

require __DIR__.'/auth.php';
