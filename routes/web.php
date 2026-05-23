<?php

use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CampaignPushController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationTemplateController;
use App\Http\Controllers\NotificationWebhookController;
use App\Http\Controllers\StandalonePushController;
use App\Http\Controllers\FilterController;
use App\Http\Controllers\FilterGroupController;
use App\Http\Controllers\ProductController;
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
    // ── Notifications ─────────────────────────────────────────────────────────
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');

    // ── Notifications – OneSignal data ───────────────────────────────────────
    Route::get('/notifications/app-stats',  [NotificationController::class, 'appStats'])->name('notifications.app-stats');
    Route::get('/notifications/segments',   [NotificationController::class, 'segments'])->name('notifications.segments');
    Route::get('/notifications/history',    [NotificationController::class, 'history'])->name('notifications.history');
    Route::get('/notifications/scheduled',  [NotificationController::class, 'scheduled'])->name('notifications.scheduled');
    Route::get('/notifications/stats',      [NotificationController::class, 'statsOverview'])->name('notifications.stats');

    // ── Notification templates ────────────────────────────────────────────────
    Route::get('/notification-templates',         [NotificationTemplateController::class, 'index'])->name('notification-templates.index');
    Route::post('/notification-templates',        [NotificationTemplateController::class, 'store'])->name('notification-templates.store');
    Route::delete('/notification-templates/{id}', [NotificationTemplateController::class, 'destroy'])->name('notification-templates.destroy');

    // ── Standalone pushes ─────────────────────────────────────────────────────
    Route::post('/standalone-pushes',                [StandalonePushController::class, 'store'])->name('standalone-pushes.store');
    Route::get('/standalone-pushes/{id}/duplicate',  [StandalonePushController::class, 'duplicate'])->name('standalone-pushes.duplicate');
    Route::delete('/standalone-pushes/{id}',         [StandalonePushController::class, 'destroy'])->name('standalone-pushes.destroy');
    Route::get('/standalone-pushes/{id}/stats',      [StandalonePushController::class, 'stats'])->name('standalone-pushes.stats');

    // ── Campaign push duplicate + retry ──────────────────────────────────────
    Route::get('/campaigns/{campaignId}/pushes/{pushId}/duplicate', [CampaignPushController::class, 'duplicate'])->name('campaign-pushes.duplicate');
    Route::post('/campaigns/{campaignId}/pushes/{pushId}/retry',    [CampaignPushController::class, 'retry'])->name('campaign-pushes.retry');
    Route::post('/campaigns/{campaignId}/sync-stats',               [CampaignPushController::class, 'syncCampaignStats'])->name('campaign-pushes.sync-stats');

    // ── Standalone push retry ─────────────────────────────────────────────────
    Route::post('/standalone-pushes/{id}/retry', [StandalonePushController::class, 'retry'])->name('standalone-pushes.retry');

    // ── Campaigns ────────────────────────────────────────────────────────────
    Route::get('/campaigns',            [CampaignController::class, 'index'])->name('campaigns.index');
    Route::get('/campaigns/create',     [CampaignController::class, 'create'])->name('campaigns.create');
    Route::post('/campaigns',           [CampaignController::class, 'store'])->name('campaigns.store');
    Route::get('/campaigns/{id}/edit',  [CampaignController::class, 'edit'])->name('campaigns.edit');
    Route::put('/campaigns/{id}',       [CampaignController::class, 'update'])->name('campaigns.update');
    Route::delete('/campaigns/{id}',    [CampaignController::class, 'destroy'])->name('campaigns.destroy');

    // ── Campaign pushes ───────────────────────────────────────────────────────
    Route::get('/campaigns/{campaignId}/pushes',                    [CampaignPushController::class, 'index'])->name('campaign-pushes.index');
    Route::post('/campaigns/{campaignId}/pushes',                   [CampaignPushController::class, 'store'])->name('campaign-pushes.store');
    Route::delete('/campaigns/{campaignId}/pushes/{pushId}',        [CampaignPushController::class, 'destroy'])->name('campaign-pushes.destroy');
    Route::get('/campaigns/{campaignId}/pushes/{pushId}/stats',     [CampaignPushController::class, 'stats'])->name('campaign-pushes.stats');

    // ── Filters ──────────────────────────────────────────────────────────────
    Route::get('/filters', [FilterGroupController::class, 'index'])->name('filters.index');
    Route::post('/filter-groups', [FilterGroupController::class, 'store'])->name('filter-groups.store');
    Route::post('/filters', [FilterController::class, 'store'])->name('filters.store');
    Route::get('/filters/{filter}/search', [FilterController::class, 'search'])->name('filters.search');
    Route::get('/filters/{filter}', [FilterController::class, 'show'])->name('filters.show');
    Route::post('/filters/{filter}/sync', [FilterController::class, 'sync'])->name('filters.sync');
    Route::post('/filters/{filter}/sync-categories', [FilterController::class, 'syncCategories'])->name('filters.sync-categories');
    Route::get('/category-product-ids', [FilterController::class, 'categoryProductIds'])->name('category.product-ids');

    // ── Product variants & related (batch routes MUST come before {productId}) ──
    Route::post('/products/batch-suggest-variants', [ProductController::class, 'batchSuggestVariants'])->name('products.batch-suggest-variants');
    Route::post('/products/batch-suggest-related',  [ProductController::class, 'batchSuggestRelated'])->name('products.batch-suggest-related');

    // Product detail page
    Route::get('/products/{productId}', [ProductController::class, 'show'])->name('products.show');

    // Variants CRUD
    Route::get('/products/{productId}/variants',                      [ProductController::class, 'getVariants'])->name('products.variants');
    Route::get('/products/{productId}/variant-groups',                [ProductController::class, 'getVariantGroups'])->name('products.variant-groups');
    Route::post('/products/{productId}/suggest-variants',             [ProductController::class, 'suggestVariants'])->name('products.suggest-variants');
    Route::post('/products/{productId}/save-variants',                [ProductController::class, 'saveVariants'])->name('products.save-variants');
    Route::delete('/products/{productId}/variants/{variantId}',       [ProductController::class, 'removeVariant'])->name('products.remove-variant');

    // Related CRUD
    Route::get('/products/{productId}/related',                       [ProductController::class, 'getRelated'])->name('products.related');
    Route::post('/products/{productId}/suggest-related',              [ProductController::class, 'suggestRelated'])->name('products.suggest-related');
    Route::post('/products/{productId}/save-related',                 [ProductController::class, 'saveRelated'])->name('products.save-related');
    Route::delete('/products/{productId}/related/{relatedId}',        [ProductController::class, 'removeRelated'])->name('products.remove-related');

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

// ── OneSignal webhook (no auth – called by OneSignal servers) ────────────────
Route::post('/webhook/onesignal', [NotificationWebhookController::class, 'handle'])
    ->name('webhook.onesignal')
    ->withoutMiddleware(['web']);

require __DIR__.'/auth.php';
