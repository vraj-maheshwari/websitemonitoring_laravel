# WebMonitoring — Laravel Website Monitoring Suite

A Laravel-based website monitoring and auditing system that runs scheduled checks (uptime, SSL, DNS, SEO, link audits, security) and produces summaries, alerts, and reports. This repository contains the backend services, jobs, models, and integrations used by the monitoring platform.

## Quick links

- Services: [app/Services](app/Services)
- Jobs: [app/Jobs](app/Jobs)
- Models: [app/Models](app/Models)
- Routes: [routes/web.php](routes/web.php)
- Configuration: [config/*.php](config)

## Project overview

WebMonitoring performs periodic checks against monitored sites and records results. The system is built around:

- Service classes (in `app/Services`) that implement check logic and coordinate subsystems.
- Queueable Jobs (in `app/Jobs`) that invoke services on schedules or when work is dispatched.
- Eloquent Models (in `app/Models`) that persist results and incidents.
- Notification integrations (Microsoft Teams) for alerts.

Checks include uptime, SSL, DNS, SEO, security scanning, and link audits. The system also aggregates daily summaries and retention/cleanup cycles for historic data.

## Architecture & flow

1. Scheduler triggers artisan commands (cron calling `php artisan schedule:run`).
2. Scheduled commands dispatch Jobs (e.g., `RunUptimeCheckJob`, `RunSslCheckJob`).
3. Jobs call Service classes (`UptimeService`, `SslService`, etc.) to perform checks and create logs/models.
4. `AlertService` evaluates results against thresholds and creates `Incident` records or `AlertHistory` entries.
5. `TeamsNotificationService` sends notifications to configured Microsoft Teams channels when incidents or alerts occur.
6. `SummaryService` and daily Jobs generate aggregated summaries stored in `DailyUptimeSummary`, `DailySslSummary`, `DailySeoSummary`, etc.

## Services (core)

Below are the primary services in `app/Services` with their responsibilities.

- `UptimeService.php` — Performs uptime checks and records `UptimeLog` entries.
- `SslService.php` — Validates SSL certificates, expiration, and records `SslLog` entries.
- `DnsService.php` — Runs DNS lookups and persists `DnsLog` entries.
- `SeoService.php` — Performs SEO-related audits and writes `SeoLog` results.
- `SecurityService.php` — Runs security checks/audits and records findings.
- `FullLinkAuditService.php` — Conducts exhaustive link audits and writes `FullLinkAuditLog`.
- `BrokenLinksUnifiedService.php` — Unifies broken link detection logic and reporting.
- `MonitoringService.php` — High-level orchestration service used by Jobs to run configured checks for a `Site`.
- `AlertService.php` — Evaluates check results and manages alerting/incident creation.
- `TeamsNotificationService.php` — Sends alerts/summary notifications to Microsoft Teams.
- `SummaryService.php` — Builds daily summaries across multiple checks and persists `Daily*Summary` models.
- `RetentionService.php` — Cleans up old logs according to retention policies.
- `ReportService.php` — Generates downloadable or scheduled reports based on collected data.
- `IncidentService.php` — Manages incident lifecycle and enrichment.
- `AnalyticsService.php` — Tracks metrics and usage analytics for dashboards and reports.

Files: [app/Services](app/Services)

## Jobs

Key jobs live in `app/Jobs` and generally perform a single check or background task. Examples:

- `RunUptimeCheckJob.php` — Dispatches uptime checks for configured sites.
- `RunSslCheckJob.php` — Dispatches SSL validation tasks.
- `RunDnsCheckJob.php` — Performs DNS probing jobs.
- `RunSeoCheckJob.php`, `RunSecurityCheckJob.php` — Run respective audits.
- `RunFullAuditJob.php`, `RunFullLinkAuditJob.php` — Full-scan jobs, usually longer running.
- `DailySummaryJob.php` — Aggregates daily results into summary tables.
- `DispatchDueChecksJob.php` — Top-level dispatcher that enqueues due checks according to site schedules.
- `RetentionCycleJob.php` — Triggers retention cleanup via `RetentionService`.

Files: [app/Jobs](app/Jobs)

## Models and data

Primary models are in `app/Models` and include:

- `Site` — Represents a monitored website and its configuration (checks enabled, thresholds, notification settings).
- `UptimeLog`, `SslLog`, `DnsLog`, `SeoLog`, `FullLinkAuditLog` — Raw results from checks.
- `Incident` — Records problems detected and tracks status.
- `AlertHistory` — Stores alert events history.
- `DailyUptimeSummary`, `DailySslSummary`, `DailySeoSummary` — Aggregated daily summaries.

Files: [app/Models](app/Models)

## Configuration & environment

- Copy `.env.example` to `.env` and configure database, queue driver, and Teams webhook URL.
- Important env vars: `DB_CONNECTION`, `QUEUE_CONNECTION`, `TEAMS_WEBHOOK_URL`, `APP_URL`, `APP_ENV`, `APP_KEY`.

Recommended queue: Redis (fast) or database (simple). For production, run queue workers with Supervisor or use Laravel Horizon for monitoring.

## Setup and running locally

1. Install PHP dependencies and node packages:

```bash
composer install
npm install
npm run build
```

2. Copy env and generate app key:

```bash
cp .env.example .env
php artisan key:generate
```

3. Configure DB and run migrations + seeders:

```bash
php artisan migrate --seed
```

4. Create storage symlink (if needed):

```bash
php artisan storage:link
```

5. Run queue worker and scheduler (for development):

```bash
php artisan queue:work
php artisan schedule:work
```

Or run the scheduler via cron in production:

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

## Common artisan commands

- Dispatch due checks immediately: `php artisan checks:dispatch` (if implemented)
- Run a single job manually: `php artisan tinker` then dispatch job or `php artisan queue:work --once`
- Generate reports/summaries: `php artisan reports:generate` (if present)

Refer to the `app/Console` commands for the exact command names.

## Testing

This project uses Pest/PHPUnit. Run tests with:

```bash
./vendor/bin/pest
```

## Deployment notes

- Use a persistent queue (Redis) and run multiple `php artisan queue:work` processes behind Supervisor or systemd.
- Ensure the scheduler runs every minute via cron to enqueue periodic checks.
- Configure appropriate `APP_ENV` / `APP_DEBUG` and secure the `TEAMS_WEBHOOK_URL` in environment.

## Troubleshooting

- If checks are not running, ensure the scheduler is active and the `DispatchDueChecksJob` is executing.
- Check `storage/logs/laravel.log` for exceptions from Jobs or Services.

## Contributing

1. Fork the repository and create a topic branch.
2. Write tests for new behavior where possible.
3. Submit a pull request with a clear description of the change.

## Where to look in the code

- Service implementations: [app/Services](app/Services)
- Jobs and scheduling: [app/Jobs](app/Jobs)
- Models: [app/Models](app/Models)
- Console commands and scheduler: [app/Console](app/Console)
- Routes: [routes/web.php](routes/web.php)

---

If you'd like, I can:

- Expand any service section with method-level details (read the specific service files).
- Add examples for `.env` values and a Supervisor config snippet.
- Create a `docs/` directory with separate pages for each service.

Tell me which next step you want.
