<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CheckController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DnsController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\LinkAuditController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/api/dashboard/metrics', [DashboardController::class, 'metricsJson'])->name('dashboard.metrics');
    Route::get('/api/dashboard/failures', [DashboardController::class, 'failuresJson'])->name('dashboard.failures');

    Route::resource('sites', SiteController::class)->middleware('site.owner');
    Route::middleware('site.owner')->group(function () {
        Route::post('/sites/{site}/check', [CheckController::class, 'trigger'])->name('sites.check');
        Route::get('/sites/{site}/history/uptime', [HistoryController::class, 'uptime'])->name('sites.history.uptime');
        Route::get('/sites/{site}/history/ssl', [HistoryController::class, 'ssl'])->name('sites.history.ssl');
        Route::get('/sites/{site}/history/seo', [HistoryController::class, 'seo'])->name('sites.history.seo');
        Route::get('/sites/{site}/history/dns', [HistoryController::class, 'dns'])->name('sites.history.dns');
        Route::get('/sites/{site}/uptime-summary', [HistoryController::class, 'uptimeSummary'])->name('sites.uptime-summary');
        Route::get('/sites/{site}/analytics', [AnalyticsController::class, 'site'])->name('sites.analytics');
        Route::get('/sites/{site}/security', [SecurityController::class, 'show'])->name('sites.security');
        Route::get('/sites/{site}/dns', [DnsController::class, 'show'])->name('sites.dns');
        Route::get('/sites/{site}/tech-stack', [SiteController::class, 'techStack'])->name('sites.tech-stack');
        Route::get('/sites/{site}/broken-links', [LinkAuditController::class, 'brokenLinks'])->name('sites.broken-links');
        Route::post('/sites/{site}/broken-links/recheck', [LinkAuditController::class, 'recheckBrokenLinks'])->name('sites.broken-links.recheck');
        Route::post('/sites/{site}/full-link-audits', [LinkAuditController::class, 'startAudit'])->name('sites.full-link-audits.store');
        Route::get('/sites/{site}/full-link-audits', [LinkAuditController::class, 'auditList'])->name('sites.full-link-audits.index');
        Route::get('/sites/{site}/full-link-audits/latest', [LinkAuditController::class, 'latestAudit'])->name('sites.full-link-audits.latest');
        Route::get('/sites/{site}/report', [ReportController::class, 'show'])->name('sites.report');
        Route::get('/api/sites/{site}/status', [SiteController::class, 'statusJson'])->name('api.sites.status');
        Route::get('/api/sites/{site}/history/uptime.json', [HistoryController::class, 'uptimeJson'])->name('api.sites.history.uptime');
        Route::get('/api/sites/{site}/analytics.json', [AnalyticsController::class, 'siteJson'])->name('api.sites.analytics');
        Route::post('/sites/{site}/fleet', [SiteController::class, 'toggleFleet'])->name('sites.fleet.toggle');
    });

    Route::get('/security', [SecurityController::class, 'index'])->name('security.index');
    Route::get('/analytics', [AnalyticsController::class, 'fleet'])->name('analytics.index');
    Route::get('/api/fleet/analytics.json', [AnalyticsController::class, 'fleetJson'])->name('api.fleet.analytics');
    Route::get('/api/fleet/check-now', [DashboardController::class, 'fleetCheckNow'])->name('api.fleet.check');
    Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
});
