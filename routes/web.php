<?php

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
    Route::get('/filters/{filter}/search', [FilterController::class, 'search'])->name('filters.search');
    Route::get('/filters/{filter}', [FilterController::class, 'show'])->name('filters.show');
    Route::post('/filters/{filter}/sync', [FilterController::class, 'sync'])->name('filters.sync');
    Route::get('/category-product-ids', [FilterController::class, 'categoryProductIds'])->name('category.product-ids');
});

require __DIR__.'/auth.php';
