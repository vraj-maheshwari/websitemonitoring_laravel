# Laravel Website Monitoring Platform — Detailed Build Prompt

## Project Overview

Build a full-stack **Website Monitoring Platform** using **Laravel 11** with **Blade templates** for the frontend. The platform monitors websites across uptime, SSL certificates, SEO health, security posture, DNS integrity, performance (Core Web Vitals / Lighthouse), broken links, incidents, analytics, and reporting.

The Laravel application owns authentication, persistence, job scheduling, monitoring services, alerts, web routes, and all Blade views. There is no separate frontend build step — all pages are server-rendered Blade templates with TailwindCSS (CDN) and lightweight vanilla JavaScript (Alpine.js) for interactivity.

---

## Technology Stack

- **Backend:** Laravel 11, PHP 8.3, MySQL (or PostgreSQL), Laravel Queues (Redis driver), Laravel Scheduler
- **Queue Worker:** Laravel Horizon (or standard `php artisan queue:work`) with Redis
- **Frontend:** Blade templates, TailwindCSS (CDN or compiled via Vite), Alpine.js (for dropdowns, modals, tabs, live polling), Chart.js (CDN, for charts)
- **Other:** Guzzle HTTP (uptime/SSL/DNS fetching), PHP DNS functions, `barryvdh/laravel-dompdf` for PDF reports, optional Playwright/Puppeteer bridge for Lighthouse via Node.js subprocess

---

## Project Structure

```
.
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php          # register, login, logout
│   │   │   ├── DashboardController.php
│   │   │   ├── SiteController.php
│   │   │   ├── HistoryController.php
│   │   │   ├── CheckController.php
│   │   │   ├── SecurityController.php
│   │   │   ├── DnsController.php
│   │   │   ├── LinkAuditController.php
│   │   │   ├── ReportController.php
│   │   │   ├── IncidentController.php
│   │   │   ├── AnalyticsController.php
│   │   │   └── SettingsController.php
│   │   └── Middleware/
│   │       └── EnsureSiteOwnership.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Site.php
│   │   ├── UptimeLog.php
│   │   ├── SslLog.php
│   │   ├── SeoLog.php
│   │   ├── DnsLog.php
│   │   ├── Incident.php
│   │   ├── AlertHistory.php
│   │   ├── FullLinkAuditLog.php
│   │   ├── DailyUptimeSummary.php
│   │   ├── DailySslSummary.php
│   │   └── DailySeoSummary.php
│   ├── Services/
│   │   ├── MonitoringService.php
│   │   ├── UptimeService.php
│   │   ├── SslService.php
│   │   ├── SeoService.php
│   │   ├── SecurityService.php
│   │   ├── DnsService.php
│   │   ├── AlertService.php
│   │   ├── IncidentService.php
│   │   ├── AnalyticsService.php
│   │   ├── SummaryService.php
│   │   ├── RetentionService.php
│   │   ├── FullLinkAuditService.php
│   │   ├── BrokenLinksUnifiedService.php
│   │   ├── ReportService.php
│   │   └── TeamsNotificationService.php
│   ├── Jobs/
│   │   ├── RunUptimeCheckJob.php
│   │   ├── RunSslCheckJob.php
│   │   ├── RunSeoCheckJob.php
│   │   ├── RunSecurityCheckJob.php
│   │   ├── RunDnsCheckJob.php
│   │   ├── RunFullLinkAuditJob.php
│   │   ├── RunFullAuditJob.php
│   │   ├── DispatchDueChecksJob.php
│   │   ├── ZombieRescueJob.php
│   │   ├── RetentionCycleJob.php
│   │   └── DailySummaryJob.php
│   └── Console/
│       └── Kernel.php                      # Scheduler definitions
├── database/
│   └── migrations/
├── resources/
│   └── views/
│       ├── layouts/
│       │   └── app.blade.php               # Shell layout: sidebar + topbar
│       ├── auth/
│       │   ├── login.blade.php
│       │   └── register.blade.php
│       ├── dashboard/
│       │   └── index.blade.php
│       ├── sites/
│       │   ├── index.blade.php             # Sites list
│       │   ├── show.blade.php              # Site detail with tabs
│       │   ├── create.blade.php            # Add monitor form
│       │   └── edit.blade.php              # Edit monitor form
│       ├── security/
│       │   └── index.blade.php             # Fleet security posture
│       ├── analytics/
│       │   └── index.blade.php             # Fleet analytics
│       ├── incidents/
│       │   └── index.blade.php             # Incidents log
│       ├── settings/
│       │   └── index.blade.php             # Account settings
│       └── components/
│           ├── metric-card.blade.php
│           ├── status-badge.blade.php
│           ├── help-tooltip.blade.php
│           ├── uptime-chart.blade.php      # Chart.js canvas component
│           └── response-time-chart.blade.php
├── routes/
│   └── web.php                             # All web routes (auth + protected)
└── README.md
```

---

## Database Schema (Migrations)

### `users`
- `id`, `email` (unique), `password` (bcrypt hashed), `is_active` (boolean, default true), `timestamps`

### `sites`
Main monitor record. Stores both config and denormalized latest state for fast dashboards.

**Identity & Config:**
- `id`, `user_id` (FK → users), `url` (text), `normalized_url` (string, unique per user via composite unique index on `[user_id, normalized_url]`), `name` (string, nullable), `uptime_interval` (int, seconds), `ssl_interval`, `seo_interval`, `security_interval`, `dns_interval`, `tracked_keywords` (json, nullable)

**Overall Status:**
- `app_status` (enum: `unknown|ok|warning|error|processing`), `is_processing` (boolean)

**Per-check Status:**
- `uptime_status`, `ssl_status`, `seo_status`, `security_status`, `dns_status` (each enum: `unknown|queued|running|ok|warning|error`)

**Scheduling Timestamps:**
- `next_uptime_check_at`, `next_ssl_check_at`, `next_seo_check_at`, `next_security_check_at`, `next_dns_check_at`, `next_check_at` (all nullable datetime)
- `last_uptime_check_at`, `last_ssl_check_at`, `last_seo_check_at`, `last_security_check_at`, `last_dns_check_at` (nullable datetime)
- Per-check `_started_at` timestamps (for zombie detection)

**Denormalized Latest Uptime:**
- `current_status` (up/down/degraded), `last_status_code` (int), `last_response_time` (float ms), `last_ttfb` (float ms), `last_error_message` (text), `last_downtime_started_at`, `last_downtime_ended_at`

**Denormalized Latest SSL:**
- `ssl_state` (valid/expiring/expired/error), `ssl_issuer`, `ssl_expiry_date` (date), `ssl_days_remaining` (int)

**Denormalized Latest SEO:**
- `seo_score` (float 0–100), `seo_state` (good/warning/poor/error)

**Denormalized Latest Security:**
- `security_score` (float), `security_grade` (A+/A/B/C/D/F), `security_headers` (json)

**Denormalized Latest DNS:**
- `dns_resolved` (boolean), `dns_last_ips` (json), `dns_last_ns` (json), `dns_hijack_suspected` (boolean), `dns_ns_changed` (boolean)

**Denormalized Latest Lighthouse / Core Web Vitals:**
- `performance_score` (float), `lcp_ms` (float), `tbt_ms` (float), `fcp_ms` (float), `cls` (float)

### `uptime_logs`
- `id`, `site_id` (FK), `checked_at` (datetime), `status_code` (int), `response_time_ms` (float), `ttfb_ms` (float), `is_up` (boolean), `status` (up/down/degraded), `error_message` (text), `timestamps`

### `ssl_logs`
- `id`, `site_id`, `checked_at`, `is_valid` (boolean), `issuer` (string), `expiry_date` (date), `days_remaining` (int), `ssl_state`, `error_message`, `timestamps`

### `seo_logs`
- `id`, `site_id`, `checked_at`, `score` (float), `state`, `fetch_is_valid` (boolean), `fetch_status` (string), `fetch_html_preview` (text, truncated), `seo_signals` (json), `issues` (json), `recommendations` (json), `cwv_estimate` (json), `lighthouse` (json), `tech_stack` (json), `broken_links` (json), `security_categories` (json), `security_score` (float), `security_grade`, `security_headers` (json), `timestamps`

### `dns_logs`
- `id`, `site_id`, `checked_at`, `resolved` (boolean), `ips` (json), `nameservers` (json), `mx_records` (json), `hijack_suspected` (boolean), `ns_changed` (boolean), `error_message`, `timestamps`

### `incidents`
- `id`, `site_id`, `status` (OPEN/RESOLVED), `root_cause` (string), `opened_at` (datetime), `resolved_at` (nullable datetime), `timeline` (json array of events), `timestamps`

### `alert_histories`
- `id`, `site_id`, `check_type`, `alert_level`, `subject`, `body` (text), `sent_at` (datetime), `incident_id` (nullable FK), `timestamps`

### `full_link_audit_logs`
- `id`, `site_id`, `started_at`, `completed_at`, `status` (queued/running/done/failed), `max_depth`, `max_pages`, `pages_crawled`, `links_checked`, `results` (json), `error_message`, `timestamps`

### `daily_uptime_summaries`
- `id`, `site_id`, `date` (date), `total_checks`, `up_count`, `down_count`, `degraded_count`, `avg_response_time_ms`, `uptime_percent`, `timestamps`

### `daily_ssl_summaries`
- `id`, `site_id`, `date`, `ssl_state`, `days_remaining`, `timestamps`

### `daily_seo_summaries`
- `id`, `site_id`, `date`, `score`, `state`, `timestamps`

---

## Models

### `Site` model key methods:
- `toViewArray()`: return all denormalized fields + latest timestamps for passing to Blade views
- `refreshAppStatus()`: derives `app_status` from individual per-check statuses (worst of uptime/ssl/seo/security/dns)
- `rescueStuckTasks()`: finds checks where `_started_at` is older than 10 minutes and marks them failed, then calls `refreshAppStatus()`
- `securityHeaders()` accessor: reads from latest valid `SeoLog` security headers JSON

### All models use:
- `SoftDeletes` where appropriate for sites (cascade delete histories on force-delete)
- Scopes: `scopeDueForCheck($checkType, $now)` on `Site`

---

## Services

### `MonitoringService`
- `prepareSite(Site $site)`: normalize URL, set default `name` from hostname, seed all `next_*_check_at` to `now()`, compute `next_check_at` as minimum
- `refreshNextCheckAt(Site $site)`: set `next_check_at` = min of all `next_*_check_at`
- `getIntervalSeconds(Site $site, string $checkType)`: return interval with minimum floor (uptime: 60s, ssl: 3600s, seo: 3600s, security: 3600s, dns: 3600s)
- `scheduleNextRun(Site $site, string $checkType, Carbon $checkedAt)`: set `last_*_check_at` and compute `next_*_check_at = checkedAt + interval`, refresh `next_check_at`
- `getDueSiteIds(string $checkType, ?Carbon $now = null, int $limit = 100)`: query sites where `next_*_check_at <= now` and status is not `running`

### `UptimeService`
- `runUptimeCheck(int $siteId)`: HEAD-first HTTP probe with GET streaming fallback via Guzzle, measure TTFB and response time
  - HTTP status < 400 → UP; UP but response time > threshold → DEGRADED; otherwise → DOWN
  - Write `UptimeLog`, update denormalized `Site` fields, call `scheduleNextRun`, call `AlertService::checkUptimeAlerts`
  - Recovery from DOWN sets `last_downtime_ended_at`, triggers SEO cooldown on `Site`
- `getUptimeLogs(int $siteId, int $limit = 50)`: return recent logs

### `SslService`
- `runSslCheck(int $siteId)`: extract hostname, open socket on port 443, retrieve certificate via `stream_context_create` with `capture_peer_cert`, parse issuer/expiry from `openssl_x509_parse`
  - Classify state: VALID (>30 days), EXPIRING (≤30 days), EXPIRED (0 or past), ERROR
  - Write `SslLog`, update Site, schedule next run, check alerts

### `SeoService`
- `shouldSkipForCooldown(Site $site)`: skip if `last_downtime_ended_at` is within last 5 minutes
- `runSeoCheck(int $siteId)`: hybrid fetch (Guzzle first, optional Playwright fallback), validate HTML, parse SEO signals, score, estimate CWV, detect tech, check broken links, run security audit, save `SeoLog`, update Site, optionally run Lighthouse, schedule next run

### `SecurityService`
- `checkHttpSecurityHeaders(array $headers)`: score HSTS, CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy
- `parseCsp(string $csp)`: parse CSP directives into structured array
- `checkCsp(array $headers)`: score CSP presence, penalize unsafe directives
- `checkCors(array $headers, string $url)`: flag permissive `Access-Control-Allow-Origin: *`
- `checkMixedContent(string $html, array $headers)`: detect `http://` resource references in HTML
- `scanForMalware(string $html)`: regex patterns for suspicious eval/atob/obfuscated scripts/iframe injections
- `runSecurityAudit(string $html, array $responseHeaders, string $url = '')`: combine all categories into score (0–100), grade (A+/A/B/C/D/F), issues list, flags
- `runSecurityCheck(int $siteId)`: fetch page, run audit, update Site security fields, schedule next run, check alerts

### `DnsService`
- `resolveDns(string $hostname)`: resolve A/AAAA via `dns_get_record`, NS and MX records
- `detectDnsHijacking(string $hostname, array $expectedIps)`: compare current IPs to expected baseline
- `detectNameserverChanges(string $hostname, array $expectedNs)`: compare NS records
- `runDnsCheck(Site $site)`: resolve current DNS, compare to latest `DnsLog` baseline
- `applyDnsCheckResult(Site $site, array $result, Carbon $checkedAt)`: write `DnsLog`, update Site DNS fields, schedule next run, check DNS alerts

### `AlertService`
- `checkUptimeAlerts`, `checkSslAlerts`, `checkSeoAlerts`, `checkDnsAlerts`, `checkSecurityAlerts`: evaluate alert conditions per check type
- `handleUptimeTransition(Site $site, string $prevStatus, string $newStatus)`: open incidents on DOWN, update timeline on continued DOWN, resolve on recovery
- `_sendAlert(string $level, string $subject, string $body)`: dispatch to Teams webhook
- `_notifySite(...)`: write `AlertHistory`, enforce 5-minute cooldown and once-per-day daily suppression (via Redis or DB)

### `IncidentService`
- `detectRootCause(string $error, ?int $statusCode)`: classify DNS, SSL, timeout, 5xx, 4xx, network, unknown
- `makeTimelineEvent(string $type, string $message, ?Carbon $at = null)`: normalized event array
- `appendTimelineEvent(Incident $incident, array $event)`: push to timeline JSON, save
- `openIncidentWithRca(Site $site, string $cause, array $firstEvent)`: create OPEN Incident
- `updateIncidentTimeline(Incident $incident, array $event)`: append ongoing failure sample
- `resolveIncidentWithTimeline(Incident $incident, array $recoveryEvent)`: set RESOLVED + `resolved_at`

### `AnalyticsService`
- `getSiteAnalytics(int $siteId, int $days = 30)`: per-site uptime %, average latency, latency percentiles, incident count/duration, SEO score trend, SSL days trend, Lighthouse trend, daily summary rollup
- `getFleetAnalytics(int $userId, int $days = 7)`: fleet uptime trend (daily), average response time, average SSL days remaining, incident count, site count

### `SummaryService`
- `runDailySummary(?Carbon $targetDate = null)`: create/update `DailyUptimeSummary`, `DailySslSummary`, `DailySeoSummary` for all sites for the given date

### `RetentionService`
- `runRetentionCycle()`: backfill daily summaries before cutoff, delete raw logs older than retention window (90 days default), delete resolved Incidents older than 180 days, skip OPEN incidents

### `FullLinkAuditService`
- `runFullLinkAudit(string $startUrl, int $maxDepth, int $maxPages, int $maxLinks, ?callable $progress)`: crawl same-domain pages via Guzzle, extract all `<a href>` links, HEAD-check each, classify as OK / broken (4xx/5xx) / unverified (timeout)
- `recheckLinks(array $entries)`: re-validate previously broken/unverified links
- `mergeRecheckIntoAuditResults(FullLinkAuditLog $audit, array $recheckResults)`: update stored results JSON

### `BrokenLinksUnifiedService`
- `getUnifiedBrokenLinks(int $siteId)`: merge broken links from latest `SeoLog` and latest `FullLinkAuditLog`
- `recheckUnifiedBrokenLinks(int $siteId)`: revalidate, update both logs

### `ReportService`
- `generateSiteReport(int $siteId)`: array report with current site state, latest logs, 30-day histories, security/DNS summary, config
- `generateSitePdfReport(int $siteId)`: build PDF using `barryvdh/laravel-dompdf` from a dedicated Blade template (`views/reports/site-pdf.blade.php`)
- `generateSiteCsvReport(int $siteId)`: return CSV string of uptime/ssl/seo history rows

### `TeamsNotificationService`
- `sendTeamsAlert(string $subject, string $body)`: POST JSON payload to `TEAMS_WEBHOOK_URL` via Guzzle

---

## Background Jobs (Laravel Queues)

Use Redis as queue driver. All jobs implement `ShouldQueue`.

### Check Jobs (each acquires a lock before running)
- `RunUptimeCheckJob($siteId)`: set `uptime_status = running`, call `UptimeService::runUptimeCheck`, release lock on success/failure
- `RunSslCheckJob($siteId)`
- `RunSeoCheckJob($siteId)`
- `RunSecurityCheckJob($siteId)`
- `RunDnsCheckJob($siteId)`
- `RunFullLinkAuditJob($siteId, $auditId)`: runs crawl, updates `FullLinkAuditLog` progress
- `RunFullAuditJob($siteId)`: dispatches all five check jobs in sequence

**Locking pattern:** atomically move check status to `running` only if currently `queued/pending/done/failed` or stale running (started > 10 min ago). `releaseCheckLock` clears `_started_at` and calls `site->refreshAppStatus()`.

### Scheduler Jobs
- `DispatchDueChecksJob`: query sites with `next_*_check_at <= now`, dispatch check jobs. Runs every minute.
- `ZombieRescueJob`: rescue stuck tasks. Runs every 5 minutes.
- `RetentionCycleJob`: runs daily at 03:00.
- `DailySummaryJob`: runs daily at 00:30.

### Laravel Scheduler (`app/Console/Kernel.php`)
```php
$schedule->job(new DispatchDueChecksJob)->everyMinute();
$schedule->job(new ZombieRescueJob)->everyFiveMinutes();
$schedule->job(new DailySummaryJob)->dailyAt('00:30');
$schedule->job(new RetentionCycleJob)->dailyAt('03:00');
```

---

## Web Routes (`routes/web.php`)

All routes except auth use the `auth` middleware. Ownership enforced via `EnsureSiteOwnership` middleware bound on `{site}` resource routes.

### Authentication
```
GET    /login                   → AuthController@showLogin
POST   /login                   → AuthController@login
GET    /register                → AuthController@showRegister
POST   /register                → AuthController@register
POST   /logout                  → AuthController@logout
```

### Dashboard
```
GET    /                        → DashboardController@index
```

### Sites
```
GET    /sites                   → SiteController@index
GET    /sites/create            → SiteController@create
POST   /sites                   → SiteController@store
GET    /sites/{site}            → SiteController@show
GET    /sites/{site}/edit       → SiteController@edit
PUT    /sites/{site}            → SiteController@update
DELETE /sites/{site}            → SiteController@destroy
```

### Manual Checks (POST form submissions with CSRF)
```
POST   /sites/{site}/check      → CheckController@trigger   (field: type = uptime|ssl|seo|security|dns|all)
```

### History & Analytics (used by Blade views via AJAX or direct page render)
```
GET    /sites/{site}/history/uptime     → HistoryController@uptime
GET    /sites/{site}/history/ssl        → HistoryController@ssl
GET    /sites/{site}/history/seo        → HistoryController@seo
GET    /sites/{site}/history/dns        → HistoryController@dns
GET    /sites/{site}/uptime-summary     → HistoryController@uptimeSummary
GET    /sites/{site}/analytics          → AnalyticsController@site
GET    /analytics                       → AnalyticsController@fleet
```

### Security, DNS, Links, Reports
```
GET    /security                        → SecurityController@index
GET    /sites/{site}/security           → SecurityController@show
GET    /sites/{site}/dns                → DnsController@show
GET    /sites/{site}/tech-stack         → SiteController@techStack
GET    /sites/{site}/broken-links       → LinkAuditController@brokenLinks
POST   /sites/{site}/broken-links/recheck → LinkAuditController@recheckBrokenLinks
POST   /sites/{site}/full-link-audits   → LinkAuditController@startAudit
GET    /sites/{site}/full-link-audits   → LinkAuditController@auditList
GET    /sites/{site}/full-link-audits/latest → LinkAuditController@latestAudit
GET    /sites/{site}/report             → ReportController@show   (?format=json|csv|pdf)
```

### Incidents & Settings
```
GET    /incidents               → IncidentController@index
GET    /settings                → SettingsController@index
PUT    /settings                → SettingsController@update
```

### JSON endpoints (for Alpine.js polling / Chart.js data)
```
GET    /api/sites/{site}/status                  → SiteController@statusJson
GET    /api/dashboard/metrics                    → DashboardController@metricsJson
GET    /api/sites/{site}/history/uptime.json     → HistoryController@uptimeJson
GET    /api/sites/{site}/analytics.json          → AnalyticsController@siteJson
GET    /api/fleet/analytics.json                 → AnalyticsController@fleetJson
```
These routes return `response()->json(...)` and are consumed by Chart.js and Alpine.js `fetch()` calls within Blade views.

---

## Controllers

### `AuthController`
- `showLogin()`, `showRegister()`: return Blade views
- `login(Request $request)`: validate credentials, `Auth::attempt`, redirect to `/`
- `register(Request $request)`: validate, create User, login, redirect to `/`
- `logout()`: `Auth::logout`, redirect to `/login`

### `DashboardController`
- `index()`: query current user's sites, compute fleet metrics (total/up/down/degraded counts, average uptime %, SSL expiring count, average SEO score, recent failed uptime events), pass to `dashboard/index.blade.php`
- `metricsJson()`: same data as JSON for Alpine.js polling refresh

### `SiteController`
- `index()`: return user's sites ordered by `app_status`, pass to `sites/index.blade.php`
- `create()`: return `sites/create.blade.php`
- `store(Request $request)`: validate `url`, normalize, ensure unique per user, create `Site`, call `MonitoringService::prepareSite`, dispatch `RunFullAuditJob`, redirect to `sites/{site}` with success flash
- `show(Site $site)`: load site with latest uptime/ssl/seo/dns logs, pass to `sites/show.blade.php`
- `edit(Site $site)`: return `sites/edit.blade.php`
- `update(Request $request, Site $site)`: validate and update `name`, intervals, `tracked_keywords`, refresh schedule, redirect back with flash
- `destroy(Site $site)`: delete site + cascaded data, redirect to `/sites` with flash
- `statusJson(Site $site)`: return compact status JSON for Alpine.js polling

### `CheckController`
- `trigger(Request $request, Site $site)`: validate `type`, dispatch corresponding job(s), redirect back with flash `"Check queued successfully"`

### `HistoryController`
- All methods load the requested logs/summaries and return either a Blade partial or JSON depending on whether request expects JSON (`$request->expectsJson()`)

### `ReportController`
- `show(Site $site, Request $request)`: switch on `?format=`: json → `response()->json(...)`, csv → `response()->streamDownload(...)`, pdf → `response(pdfBytes)->header('Content-Type', 'application/pdf')`

### `SecurityController`
- `index()`: fleet-level SSL/DNS/security posture table for all user sites → `security/index.blade.php`
- `show(Site $site)`: per-site security detail → rendered as a tab within `sites/show.blade.php`

---

## Blade Views

### `layouts/app.blade.php`
Master layout with:
- `<head>`: TailwindCSS CDN, Alpine.js CDN, Chart.js CDN, CSRF meta tag
- Left sidebar: navigation links (Dashboard, Sites, Security, Analytics, Incidents, Settings), signed-in user email at the bottom
- Top bar: page title (from `@section('title')`), user menu with logout button
- `@yield('content')` main content area
- `@stack('scripts')` for page-specific JS

### `auth/login.blade.php`
- Centered card layout (no sidebar)
- Email + password fields, submit button, link to `/register`
- Display `$errors` bag

### `auth/register.blade.php`
- Email + password + password_confirmation fields, submit, link to `/login`

### `dashboard/index.blade.php`
Extends `layouts/app`. Sections:
- **Metric cards row**: Total Sites, Sites Up, Sites Down, Sites Degraded, Average Uptime %, SSL Expiring Soon — each uses `<x-metric-card>` component
- **Fleet response time chart**: Chart.js `LineChart` canvas, data loaded from `/api/fleet/analytics.json` on page load via inline `<script>`
- **Activity feed**: table of latest 20 failed/degraded uptime events with site name, status, response code, time ago
- **Auto-refresh**: Alpine.js `x-data` with `setInterval` every 30 seconds calling `/api/dashboard/metrics` and updating metric card values in place

### `sites/index.blade.php`
- Table listing all monitored sites: Name/URL, Overall Status badge, Uptime status, SSL state + days remaining, SEO score, Last checked, Actions (View / Edit / Delete)
- Delete uses a small `<form method="POST">` with `@method('DELETE')` and JS `confirm()` prompt
- Flash success message display at top

### `sites/create.blade.php`
- Form: URL input (required), Display Name (optional), Tracked Keywords (comma-separated), per-check interval fields (with sensible defaults pre-filled)
- Submit → `POST /sites`

### `sites/edit.blade.php`
- Same fields as create, pre-filled with current site values
- Submit → `PUT /sites/{site}`

### `sites/show.blade.php`
Site detail page with **Alpine.js tab system** (`x-data="{ tab: 'overview' }"`):

**Tab: Overview**
- Current status badge, last checked time, response time, TTFB
- Uptime history chart (Chart.js Line, 7-day hourly data from `/api/sites/{id}/history/uptime.json`)
- Manual check buttons (one form per check type + "Check All"), each `POST /sites/{site}/check`
- Recent uptime log table (last 20 rows)

**Tab: SSL**
- SSL state badge, issuer, expiry date, days remaining
- Recent SSL log table

**Tab: SEO**
- SEO score (large number + color), state badge
- Category breakdown table (title, meta, headings, canonical, etc. with pass/fail icons)
- Issues list and recommendations list (from `$seoLog->issues` and `$seoLog->recommendations`)
- Keyword density table for tracked keywords

**Tab: Performance**
- Core Web Vitals: LCP, FCP, TBT, CLS — each with value and Good/Needs Improvement/Poor badge
- If Lighthouse data available: performance score, real browser audit badge
- If only CWV estimate: note "(heuristic estimate)"

**Tab: Security**
- Security grade (large letter, color-coded A+=green, F=red)
- Category scores table: HSTS, CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, CORS, Mixed Content, Malware scan
- Security headers raw table

**Tab: Tech Stack**
- Detected technologies grouped by category (CMS, Framework, Analytics, CDN, Server, Advertising)
- Added/removed diffs if previous tech stack available

**Tab: Links**
- Broken links table (URL, status code, source page, type internal/external)
- "Recheck Broken Links" form (`POST /sites/{site}/broken-links/recheck`)
- "Start Full Link Audit" form (`POST /sites/{site}/full-link-audits`)
- Latest full audit status + summary counts

**Tab: DNS**
- Resolved IPs, Nameservers, MX records
- Hijack suspected / NS changed warning banners
- DNS log history table

**Tab: Reports**
- Download links: JSON report, CSV export, PDF report (all `GET /sites/{site}/report?format=...`)

**Auto-refresh on Overview tab:**
- Alpine.js polls `/api/sites/{site}/status` every 15 seconds and updates status badge + timestamps without full page reload

### `security/index.blade.php`
Fleet security posture table:
- Per-site: Name, Security Grade (colored), SSL State + days, DNS Hijack flag, NS Changed flag, Key missing headers
- Sortable by grade

### `analytics/index.blade.php`
- Fleet uptime trend chart (Chart.js Line, daily uptime % for last 7 days per site)
- Average response time trend chart
- Incident count bar chart
- Data loaded from `/api/fleet/analytics.json`

### `incidents/index.blade.php`
- Timeline table: Site, Root Cause, Status (OPEN/RESOLVED badge), Opened At, Resolved At, Duration
- Filter by status (OPEN / RESOLVED / All) via query string `?status=open`

### `settings/index.blade.php`
- Display current user email (read-only)
- Change password form (current password, new password, confirm)
- Logout button

### Blade Components (`resources/views/components/`)

**`metric-card.blade.php`** — props: `title`, `value`, `color` (green/red/yellow/gray), `icon`
```blade
<div class="bg-white rounded-lg shadow p-4">
    <p class="text-sm text-gray-500">{{ $title }}</p>
    <p class="text-2xl font-bold text-{{ $color }}-600">{{ $value }}</p>
</div>
```

**`status-badge.blade.php`** — prop: `status`
Returns a colored `<span>` pill: green for up/ok/valid, red for down/error/expired, yellow for degraded/warning/expiring, gray for unknown/processing.

**`help-tooltip.blade.php`** — prop: `text`
An `ⓘ` icon with Alpine.js-powered tooltip on hover.

**`uptime-chart.blade.php`** — prop: `siteId`
Renders a `<canvas>` and inline `<script>` that fetches `/api/sites/{{ $siteId }}/history/uptime.json` and initializes a Chart.js Line chart.

**`response-time-chart.blade.php`** — prop: `sites` (collection)
Renders a multi-line Chart.js chart comparing response times across all sites.

### `reports/site-pdf.blade.php`
Blade template used by `barryvdh/laravel-dompdf` for PDF generation. Includes:
- Site name/URL header, report generated timestamp
- Current status summary table
- SSL certificate details
- SEO score and top issues
- Security grade and header audit table
- DNS record snapshot
- 30-day uptime history table (last 30 daily summaries)

---

## Authentication

Use Laravel's built-in session authentication (`Auth::attempt`, `auth` middleware). No Sanctum or API tokens needed since everything is server-rendered with Blade.

CSRF protection is automatic via `@csrf` in all forms and the `VerifyCsrfToken` middleware.

---

## Configuration (`.env` keys)

```dotenv
APP_URL=http://localhost:8000
APP_KEY=                             # generate with php artisan key:generate
DB_CONNECTION=mysql
DB_DATABASE=website_monitor
REDIS_URL=redis://localhost:6379
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
TEAMS_WEBHOOK_URL=
RESPONSE_TIME_THRESHOLD=3000         # ms, above this = DEGRADED
HTTP_USER_AGENT="WebsiteMonitor/1.0"
HTTP_VERIFY_SSL=true
LIGHTHOUSE_ENABLED=false
DATA_RETENTION_DAYS=90
```

---

## Utilities

### URL Normalization
- Strip trailing slashes, lowercase scheme and host, add `https://` if no scheme
- `normalized_url`: strip `www.`, strip query string — used for uniqueness per user

### HTTP Client Helpers
- Shared Guzzle client with configurable timeout, user agent, SSL verification
- HEAD-first uptime probe: fall back to streaming GET if HEAD returns 405
- Record TTFB via Guzzle middleware or `on_headers` callback
- `checkFileExists($url)`: HEAD request for robots.txt / sitemap.xml

### Hybrid HTML Fetch (for SEO)
- Try Guzzle GET first
- Detect JS-rendered placeholder: no `<title>`, empty `<body>`, common framework loading text
- Optional Playwright fallback via Node.js subprocess (skip if `LIGHTHOUSE_ENABLED=false`)
- Return array: `html`, `headers`, `statusCode`, `finalUrl`, `fetchedViaPlaywright`

### SEO Parser
Extract from HTML string: `title`, `meta_description`, `meta_keywords`, `h1[]`, `h2[]`, `h3[]`, `canonical`, `robots_meta`, `viewport`, `language`, `hreflang[]`, `favicon`, internal/external link counts, images with/without alt, keyword density for tracked keywords, has sitemap.xml, has robots.txt, HTTPS redirect, structured data (JSON-LD)

### SEO Engine / Scoring
Score 0–100 with weighted categories:
- Title (present, 50–60 chars): 15 pts
- Meta description (present, 120–160 chars): 15 pts
- H1 (exactly one): 10 pts
- HTTPS: 10 pts
- Canonical tag: 5 pts
- Images with alt: 10 pts
- Page speed TTFB < 800ms: 10 pts
- Robots.txt + Sitemap: 5 pts each
- Structured data: 5 pts
- Mobile viewport: 5 pts
- Keyword in title/H1: 5 pts

Return: `score`, `status` (good ≥ 75, warning ≥ 50, poor < 50), `issues[]`, `recommendations[]`, category breakdown

### SEO Validator
Reject: empty HTML, HTTP error pages (status ≥ 400), placeholder pages ("coming soon", "under construction"), body text < 100 chars

### CWV Estimator
Heuristic when Lighthouse unavailable: LCP from TTFB + DOM size, FCP ≈ LCP × 0.6, CLS = 0.0, TBT from script count × heuristic. Rate each: Good / Needs Improvement / Poor

### Technology Profiler
Detect from HTML + response headers: WordPress, Drupal, Joomla, Wix, Shopify, React, Vue, Angular, Next.js, Laravel, Google Analytics, Hotjar, Cloudflare, Fastly, Nginx, Apache, GTM, Facebook Pixel

### Broken Link Checker
- `extractAllLinks($html, $baseUrl)`: parse all `<a href>`, classify internal/external, resolve relative URLs
- `checkBrokenLinks(array $links)`: HEAD each with 10s timeout, follow up to 5 redirects, classify OK / broken / unverified

---

## Setup Instructions

```bash
composer create-project laravel/laravel website-monitor
cd website-monitor
composer require guzzlehttp/guzzle barryvdh/laravel-dompdf predis/predis
# Configure .env
php artisan key:generate
php artisan migrate
php artisan serve
```

### Queue Worker and Scheduler
```bash
# Worker
php artisan queue:work redis --queue=default --tries=3

# Scheduler (dev)
php artisan schedule:work
```

### Production Cron
```
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

---

## Operational Notes

- All web routes protected by `auth` middleware; ownership enforced via `EnsureSiteOwnership` on `{site}` routes
- `Site.normalized_url` is unique per user (composite unique index)
- CSRF tokens included automatically via `@csrf` in all forms
- Chart.js charts initialized from inline `<script>` blocks using data fetched from JSON endpoints or PHP-to-JS variable injection (`@json($data)`)
- Alpine.js handles: tab switching in site detail, auto-refresh polling, confirm dialogs, tooltip toggling
- The `fetch_html_preview` in `SeoLog` is truncated (≤500 chars) — validation preview only, not a full HTML copy
- PDF reports rendered via `barryvdh/laravel-dompdf` from a dedicated `reports/site-pdf.blade.php` template
- Security detail evidence richest when latest `SeoLog` is fresh (security categories stored in SEO log)
- Do not commit webhook URLs or `APP_KEY` to version control

---

## Design Item (Known Gap to Address)

Standalone security checks persist only score/grade/status on `Site`, while detailed category breakdown is stored in `SeoLog`. For a complete implementation, add a dedicated `security_logs` table to store full audit results from standalone security checks independently of SEO runs.

---

## Testing

```bash
php artisan test
```

Test coverage should include: auth (login/register/logout), DNS resolution + hijack detection, SEO validator/parser, CWV estimator, hybrid fetch logic, broken link unification, security service scoring, link audit crawler, report generation (JSON/CSV/PDF), alert cooldown logic, zombie rescue, retention cycle.
