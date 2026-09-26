<?php

use Illuminate\Support\Facades\Route;
use Modules\DataPlatform\Http\Controllers\AnalyticsController;
use Modules\DataPlatform\Http\Controllers\EventController;
use Modules\DataPlatform\Http\Controllers\InsightController;
use Modules\DataPlatform\Http\Controllers\MetricController;
use Modules\DataPlatform\Http\Controllers\NavCountsController;
use Modules\DataPlatform\Http\Controllers\NotificationController;
use Modules\DataPlatform\Http\Controllers\SearchController;

Route::middleware(['auth:sanctum', 'tenant.request', 'permission:metrics.view'])->prefix('v1')->group(function () {
    Route::get('events', [EventController::class, 'index'])->name('data-platform.events');
    Route::get('metrics', [MetricController::class, 'index'])->name('data-platform.metrics');
    Route::get('insights', [InsightController::class, 'index'])->name('data-platform.insights');
    Route::get('nav-counts', [NavCountsController::class, 'index'])->name('data-platform.nav-counts');
    Route::get('search', [SearchController::class, 'index'])->name('data-platform.search');
    Route::get('notifications', [NotificationController::class, 'index'])->name('data-platform.notifications');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('data-platform.notifications.read-all');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('data-platform.notifications.unread-count');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('data-platform.notifications.read');
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])->name('data-platform.notifications.destroy');
    Route::get('analytics/trends', [AnalyticsController::class, 'trends'])->name('data-platform.analytics.trends');
    Route::get('metrics/{metric}', [MetricController::class, 'show'])->name('data-platform.metrics.show');
});
