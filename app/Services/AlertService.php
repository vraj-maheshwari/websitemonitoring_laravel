<?php

namespace App\Services;

use App\Models\Site;

class AlertService
{
    public function __construct(private IncidentService $incidents, private TeamsNotificationService $teams) {}

    public function checkUptimeAlerts(Site $site, string $previous, string $current): void
    {
        $this->handleUptimeTransition($site, $previous, $current);
    }

    public function checkSslAlerts(Site $site): void
    {
        if (in_array($site->ssl_state, ['expiring', 'expired', 'error'], true)) {
            $this->notifySite($site, 'ssl', 'warning', "SSL {$site->ssl_state} for {$site->name}", "Certificate state is {$site->ssl_state}.");
        }
    }

    public function checkSeoAlerts(Site $site): void
    {
        if (($site->seo_score ?? 100) < 50) {
            $this->notifySite($site, 'seo', 'warning', "Low SEO score for {$site->name}", "SEO score is {$site->seo_score}.");
        }
    }

    public function checkDnsAlerts(Site $site): void
    {
        if (! $site->dns_resolved || $site->dns_hijack_suspected || $site->dns_ns_changed) {
            $this->notifySite($site, 'dns', 'warning', "DNS issue for {$site->name}", 'DNS resolution, IP baseline, or nameserver baseline changed.');
        }
    }

    public function checkSecurityAlerts(Site $site): void
    {
        if (($site->security_score ?? 100) < 60) {
            $this->notifySite($site, 'security', 'warning', "Security score needs attention for {$site->name}", "Security grade is {$site->security_grade}.");
        }
    }

    public function handleUptimeTransition(Site $site, string $previous, string $current): void
    {
        if ($current === 'down') {
            $open = $site->incidents()->where('status', 'OPEN')->latest('opened_at')->first();
            $event = $this->incidents->makeTimelineEvent('failure', $site->last_error_message ?: "HTTP {$site->last_status_code}");
            if ($open) {
                $this->incidents->updateIncidentTimeline($open, $event);
            } else {
                $cause = $this->incidents->detectRootCause($site->last_error_message, $site->last_status_code);
                $open = $this->incidents->openIncidentWithRca($site, $cause, $event);
                $this->notifySite($site, 'uptime', 'critical', "{$site->name} is down", $event['message'], $open->id);
            }
        }

        if ($previous === 'down' && in_array($current, ['up', 'degraded'], true)) {
            $open = $site->incidents()->where('status', 'OPEN')->latest('opened_at')->first();
            if ($open) {
                $this->incidents->resolveIncidentWithTimeline($open, $this->incidents->makeTimelineEvent('recovery', 'Site recovered.'));
                $this->notifySite($site, 'uptime', 'info', "{$site->name} recovered", 'The site is responding again.', $open->id);
            }
        }
    }

    private function notifySite(Site $site, string $type, string $level, string $subject, string $body, ?int $incidentId = null): void
    {
        $recent = $site->alertHistories()
            ->where('check_type', $type)
            ->where('alert_level', $level)
            ->where('sent_at', '>=', now()->subMinutes(5))
            ->exists();

        if ($recent) {
            return;
        }

        $site->alertHistories()->create([
            'check_type' => $type,
            'alert_level' => $level,
            'subject' => $subject,
            'body' => $body,
            'sent_at' => now(),
            'incident_id' => $incidentId,
        ]);

        $this->teams->sendTeamsAlert($subject, $body);
    }
}
