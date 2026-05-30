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
        if ($site->ssl_state === 'expired') {
            $this->notifySite($site, 'ssl', 'critical', "SSL expired for {$site->name}", 'The SSL certificate has expired.');
        }
        if ($site->ssl_state === 'critical') {
            $this->notifySite($site, 'ssl', 'critical', "SSL critical for {$site->name}", "Certificate expires in {$site->ssl_days_remaining} day(s).");
        }
        if ($site->ssl_state === 'expiring') {
            $this->notifySite($site, 'ssl', 'warning', "SSL expiring for {$site->name}", "Certificate expires in {$site->ssl_days_remaining} day(s).");
        }
        if ($site->ssl_hostname_valid === false && ! in_array($site->ssl_state, ['unknown', 'error'], true)) {
            $this->notifySite($site, 'ssl', 'critical', "SSL hostname mismatch for {$site->name}", 'The certificate does not match the monitored hostname.');
        }
        if ($site->ssl_is_trusted === false && $site->ssl_state !== 'unknown') {
            $this->notifySite($site, 'ssl', 'critical', "SSL certificate is untrusted for {$site->name}", 'The certificate chain could not be validated.');
        }
        if ($this->isWeakTls($site->ssl_tls_version)) {
            $this->notifySite($site, 'ssl', 'warning', "Weak TLS for {$site->name}", "Detected TLS version: {$site->ssl_tls_version}.");
        }
        if (($site->ssl_security_score ?? 100) < 50) {
            $this->notifySite($site, 'ssl', 'warning', "Low SSL score for {$site->name}", "SSL score is {$site->ssl_security_score}, grade {$site->ssl_grade}.");
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
        if (! $site->dns_resolved) {
            $this->notifySite($site, 'dns', 'critical', "DNS unresolved for {$site->name}", 'No A or AAAA records resolved.');
        }
        if ($site->dnssec_enabled === false && $site->dns_resolved) {
            $this->notifySite($site, 'dns', 'warning', "DNSSEC disabled for {$site->name}", 'DNSSEC records were not detected.');
        }
        if (in_array($site->dns_hijack_risk, ['high', 'critical'], true)) {
            $level = $site->dns_hijack_risk === 'critical' ? 'critical' : 'warning';
            $this->notifySite($site, 'dns', $level, "DNS hijack risk {$site->dns_hijack_risk} for {$site->name}", 'DNS records changed in a way that may indicate hijacking.');
        }
        if ($site->dns_ns_changed) {
            $this->notifySite($site, 'dns', 'warning', "Nameservers changed for {$site->name}", 'Nameserver records changed from the previous check.');
        }
        $latest = $site->dnsLogs()->latest('checked_at')->first();
        $changes = $latest?->change_details ?: [];
        if (($changes['mx_records']['changed'] ?? false) === true) {
            $this->notifySite($site, 'dns', 'warning', "MX records changed for {$site->name}", 'Mail exchanger records changed from the previous check.');
        }
        if ($latest && empty($latest->caa_records)) {
            $this->notifySite($site, 'dns', 'warning', "CAA records missing for {$site->name}", 'No CAA records were found.');
        }
        if (($site->dns_response_time_ms ?? 0) > 500) {
            $this->notifySite($site, 'dns', 'warning', "Slow DNS for {$site->name}", "DNS lookup took {$site->dns_response_time_ms}ms.");
        }
        if (($site->dns_score ?? 100) < 50) {
            $this->notifySite($site, 'dns', 'warning', "Low DNS score for {$site->name}", "DNS score is {$site->dns_score}, grade {$site->dns_grade}.");
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
            ->where('subject', $subject)
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

    private function isWeakTls(?string $tlsVersion): bool
    {
        $tls = strtolower((string) $tlsVersion);

        return $tls !== '' && ! str_contains($tls, 'tlsv1.2') && ! str_contains($tls, 'tlsv1.3');
    }
}
