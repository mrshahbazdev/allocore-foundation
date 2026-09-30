<?php

use Illuminate\Support\Facades\Route;
use Modules\DataPlatform\Http\Controllers\AnalyticsController;
use Modules\DataPlatform\Http\Controllers\AnonymizedRecordController;
use Modules\DataPlatform\Http\Controllers\ConnectController;
use Modules\DataPlatform\Http\Controllers\ConnectorController;
use Modules\DataPlatform\Http\Controllers\EventController;
use Modules\DataPlatform\Http\Controllers\InsightController;
use Modules\DataPlatform\Http\Controllers\IntegrationController;
use Modules\DataPlatform\Http\Controllers\KpiController;
use Modules\DataPlatform\Http\Controllers\MetricController;
use Modules\DataPlatform\Http\Controllers\NavCountsController;
use Modules\DataPlatform\Http\Controllers\NotificationController;
use Modules\DataPlatform\Http\Controllers\SearchController;

Route::middleware(['auth:sanctum', 'tenant.request', 'throttle:api', 'permission:metrics.view'])->prefix('v1')->group(function () {
    Route::post('events', [EventController::class, 'store'])->name('data-platform.events.store');
    Route::get('events', [EventController::class, 'index'])->name('data-platform.events');
    Route::get('events/summary', [EventController::class, 'summary'])->name('data-platform.events.summary');
    Route::get('events/export', [EventController::class, 'export'])->name('data-platform.events.export');
    Route::get('events/actors', [EventController::class, 'actors'])->name('data-platform.events.actors');
    Route::get('events/types', [EventController::class, 'types'])->name('data-platform.events.types');
    Route::get('events/subjects', [EventController::class, 'subjects'])->name('data-platform.events.subjects');
    Route::get('events/subject-types', [EventController::class, 'subjectTypes'])->name('data-platform.events.subject-types');
    Route::get('events/groups', [EventController::class, 'groups'])->name('data-platform.events.groups');
    Route::get('events/actions', [EventController::class, 'actions'])->name('data-platform.events.actions');
    Route::get('events/{event}', [EventController::class, 'show'])->name('data-platform.events.show');
    Route::get('metrics', [MetricController::class, 'index'])->name('data-platform.metrics');
    Route::get('kpis', [KpiController::class, 'index'])->name('data-platform.kpis');
    Route::get('insights', [InsightController::class, 'index'])->name('data-platform.insights');
    Route::get('insights/stats', [InsightController::class, 'stats'])->name('data-platform.insights.stats');
    Route::get('nav-counts', [NavCountsController::class, 'index'])->name('data-platform.nav-counts');
    Route::get('search', [SearchController::class, 'index'])->name('data-platform.search');
    Route::get('notifications', [NotificationController::class, 'index'])->name('data-platform.notifications');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('data-platform.notifications.read-all');
    Route::get('notifications/stats', [NotificationController::class, 'stats'])->name('data-platform.notifications.stats');
    Route::get('notifications/codes', [NotificationController::class, 'codes'])->name('data-platform.notifications.codes');
    Route::get('notifications/kinds', [NotificationController::class, 'kinds'])->name('data-platform.notifications.kinds');
    Route::post('notifications/test', [NotificationController::class, 'test'])->name('data-platform.notifications.test');
    Route::get('notifications/export', [NotificationController::class, 'export'])->name('data-platform.notifications.export');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('data-platform.notifications.unread-count');
    Route::get('notifications/{id}', [NotificationController::class, 'show'])->name('data-platform.notifications.show');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('data-platform.notifications.read');
    Route::post('notifications/{id}/unread', [NotificationController::class, 'markUnread'])->name('data-platform.notifications.unread');
    Route::post('notifications/batch', [NotificationController::class, 'batch'])->name('data-platform.notifications.batch');
    Route::post('notifications/delete-read', [NotificationController::class, 'deleteRead'])->name('data-platform.notifications.delete-read');
    Route::delete('notifications', [NotificationController::class, 'destroyAll'])->name('data-platform.notifications.destroy_all');
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])->name('data-platform.notifications.destroy');
    Route::get('analytics/trends', [AnalyticsController::class, 'trends'])->name('data-platform.analytics.trends');
    Route::get('metrics/{metric}', [MetricController::class, 'show'])->name('data-platform.metrics.show');
    Route::apiResource('integrations', IntegrationController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('anonymized-records', [AnonymizedRecordController::class, 'index'])->name('data-platform.anonymized-records.index');
    Route::post('connectors/{connector}/run', [ConnectorController::class, 'run']);
    Route::apiResource('connectors', ConnectorController::class)->only(['index', 'store', 'update', 'destroy']);
});

// Suite-Connect — Zugangsdaten liefern Mandantenliste bzw. Webhook-URL.
Route::post('v1/connect', [ConnectController::class, 'store'])
    ->middleware('throttle:10,1')->name('data-platform.connect');
Route::post('v1/connect/exchange', [ConnectController::class, 'exchange'])
    ->middleware('throttle:10,1')->name('data-platform.connect.exchange');

// Öffentlicher Webhook-Eingang — Token in der URL identifiziert Quelle + Mandant.
Route::post('v1/webhooks/{token}', [IntegrationController::class, 'webhook'])
    ->middleware('throttle:60,1')->name('data-platform.webhook');
