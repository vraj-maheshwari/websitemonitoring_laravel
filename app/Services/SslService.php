<?php

namespace App\Services;

use App\Models\Site;
use App\Models\SslLog;
use Illuminate\Support\Facades\Log;
use Throwable;

class SslService
{
    public function __construct(private MonitoringService $monitoring, private AlertService $alerts) {}

    public function runSslCheck(int $siteId): SslLog
    {
        $site = Site::findOrFail($siteId);
        $checkedAt = now();
        $host = parse_url($site->url, PHP_URL_HOST);
        $issuer = null;
        $subject = null;
        $subjectAltNames = [];
        $serialNumber = null;
        $signatureAlgorithm = null;
        $certificateVersion = null;
        $tlsVersion = null;
        $validFrom = null;
        $validUntil = null;
        $expiry = null;
        $days = null;
        $state = 'error';
        $error = null;
        $isTrusted = false;
        $hostnameValid = false;
        $score = 0;
        $grade = 'F';

        try {
            if (! $host) {
                throw new \RuntimeException('Unable to determine host from site URL.');
            }

            try {
                $result = $this->fetchCertificate($host, true);
                $isTrusted = true;
            } catch (Throwable $trustedException) {
                $error = $trustedException->getMessage();
                $result = $this->fetchCertificate($host, false);
            }

            $cert = $result['cert'];
            $tlsVersion = $result['tls_version'];
            $issuer = $this->formatCertificateName($cert['issuer'] ?? []);
            $subject = $this->formatCertificateName($cert['subject'] ?? []);
            $subjectAltNames = $this->extractSubjectAltNames($cert);
            $serialNumber = $cert['serialNumber'] ?? null;
            $signatureAlgorithm = $cert['signatureTypeSN'] ?? null;
            $certificateVersion = $cert['version'] ?? null;
            $validFrom = isset($cert['validFrom_time_t']) ? now()->setTimestamp($cert['validFrom_time_t'])->startOfDay() : null;
            $validUntil = isset($cert['validTo_time_t']) ? now()->setTimestamp($cert['validTo_time_t'])->startOfDay() : null;
            $expiry = $validUntil;
            $days = $expiry ? now()->startOfDay()->diffInDays($expiry, false) : null;
            $hostnameValid = $this->certificateMatchesHostname($host, $cert, $subjectAltNames);
            $state = $days === null ? 'error' : ($days <= 0 ? 'expired' : ($days <= 7 ? 'critical' : ($days <= 30 ? 'expiring' : 'valid')));
            [$score, $grade] = $this->scoreCertificate($state, $days, $tlsVersion, $hostnameValid, $isTrusted);
        } catch (Throwable $exception) {
            $error = trim(collect([$error, $exception->getMessage()])->filter()->unique()->implode(' | '));
            [$score, $grade] = $this->scoreCertificate($state, $days, $tlsVersion, $hostnameValid, $isTrusted);
        }

        $log = SslLog::create([
            'site_id' => $site->id,
            'checked_at' => $checkedAt,
            'is_valid' => $state === 'valid' && $isTrusted && $hostnameValid,
            'issuer' => $issuer,
            'expiry_date' => $expiry,
            'days_remaining' => $days,
            'ssl_state' => $state,
            'error_message' => $error,
            'ssl_subject' => $subject,
            'ssl_subject_alt_names' => $subjectAltNames,
            'ssl_serial_number' => $serialNumber,
            'ssl_signature_algorithm' => $signatureAlgorithm,
            'ssl_certificate_version' => $certificateVersion,
            'ssl_tls_version' => $tlsVersion,
            'ssl_valid_from' => $validFrom,
            'ssl_valid_until' => $validUntil,
            'ssl_is_trusted' => $isTrusted,
            'ssl_security_score' => $score,
            'ssl_grade' => $grade,
            'ssl_hostname_valid' => $hostnameValid,
            'ssl_error_details' => $error,
        ]);

        Log::info('SSL check completed', [
            'site_id' => $site->id,
            'url' => $site->url,
            'state' => $state,
            'tls_version' => $tlsVersion,
            'issuer' => $issuer,
            'expiry_date' => optional($expiry)->toDateString(),
            'days_remaining' => $days,
            'hostname_valid' => $hostnameValid,
            'is_trusted' => $isTrusted,
            'ssl_security_score' => $score,
            'ssl_grade' => $grade,
            'error' => $error,
        ]);

        $site->fill([
            'ssl_state' => $state,
            'ssl_issuer' => $issuer,
            'ssl_expiry_date' => $expiry,
            'ssl_days_remaining' => $days,
            'ssl_status' => $state === 'valid' && $isTrusted && $hostnameValid ? 'ok' : (in_array($state, ['critical', 'expiring'], true) ? 'warning' : 'error'),
            'ssl_subject' => $subject,
            'ssl_subject_alt_names' => $subjectAltNames,
            'ssl_serial_number' => $serialNumber,
            'ssl_signature_algorithm' => $signatureAlgorithm,
            'ssl_certificate_version' => $certificateVersion,
            'ssl_tls_version' => $tlsVersion,
            'ssl_valid_from' => $validFrom,
            'ssl_valid_until' => $validUntil,
            'ssl_is_trusted' => $isTrusted,
            'ssl_security_score' => $score,
            'ssl_grade' => $grade,
            'ssl_hostname_valid' => $hostnameValid,
            'ssl_error_details' => $error,
        ])->save();

        $this->monitoring->scheduleNextRun($site, 'ssl', $checkedAt);
        $this->alerts->checkSslAlerts($site->fresh());

        return $log;
    }

    private function fetchCertificate(string $host, bool $verify): array
    {
        $client = null;

        try {
            $context = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => $verify,
                    'verify_peer_name' => $verify,
                    'SNI_enabled' => true,
                    'peer_name' => $host,
                ],
            ]);

            $client = @stream_socket_client("ssl://{$host}:443", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);

            if (! $client) {
                throw new \RuntimeException($errstr ?: "Unable to connect to SSL endpoint ({$errno}).");
            }

            $params = stream_context_get_params($client);
            $certificate = $params['options']['ssl']['peer_certificate'] ?? null;

            if (! $certificate) {
                throw new \RuntimeException('SSL peer certificate was not returned.');
            }

            $cert = openssl_x509_parse($certificate);

            if (! is_array($cert)) {
                throw new \RuntimeException('Unable to parse SSL certificate.');
            }

            $meta = stream_get_meta_data($client);

            return [
                'cert' => $cert,
                'tls_version' => $meta['crypto']['protocol'] ?? null,
            ];
        } finally {
            if (is_resource($client)) {
                fclose($client);
            }
        }
    }

    private function certificateMatchesHostname(string $host, array $cert, array $subjectAltNames): bool
    {
        foreach ($subjectAltNames as $certHost) {
            if ($this->matchesHostname($host, $certHost)) {
                return true;
            }
        }

        $commonName = $cert['subject']['CN'] ?? null;

        return is_string($commonName) && $this->matchesHostname($host, $commonName);
    }

    private function matchesHostname(string $host, string $certHost): bool
    {
        $host = strtolower(trim($host, '.'));
        $certHost = strtolower(trim($certHost, '.'));

        if ($host === $certHost) {
            return true;
        }

        if (! str_starts_with($certHost, '*.')) {
            return false;
        }

        $base = substr($certHost, 2);

        return str_ends_with($host, '.'.$base)
            && substr_count($host, '.') === substr_count($base, '.') + 1;
    }

    private function extractSubjectAltNames(array $cert): array
    {
        $san = $cert['extensions']['subjectAltName'] ?? '';

        if (! is_string($san) || $san === '') {
            return [];
        }

        return collect(explode(',', $san))
            ->map(fn ($value) => trim($value))
            ->filter(fn ($value) => str_starts_with(strtolower($value), 'dns:'))
            ->map(fn ($value) => trim(substr($value, 4)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function formatCertificateName(array $parts): ?string
    {
        if (empty($parts)) {
            return null;
        }

        return collect($parts)
            ->map(fn ($value, $key) => "{$key}={$value}")
            ->implode(', ');
    }

    private function scoreCertificate(string $state, ?int $days, ?string $tlsVersion, bool $hostnameValid, bool $isTrusted): array
    {
        $score = 100;
        $tls = strtolower((string) $tlsVersion);

        if ($state === 'expired') {
            $score -= 50;
        }
        if (str_contains($tls, 'tlsv1.0') || $tls === 'tlsv1') {
            $score -= 30;
        } elseif (str_contains($tls, 'tlsv1.1')) {
            $score -= 20;
        } elseif ($tlsVersion && ! str_contains($tls, 'tlsv1.2') && ! str_contains($tls, 'tlsv1.3')) {
            $score -= 20;
        }
        if (! $hostnameValid) {
            $score -= 25;
        }
        if (! $isTrusted) {
            $score -= 30;
        }
        if ($days !== null && $days > 0 && $days <= 30) {
            $score -= 10;
        }

        $score = max(0, min(100, $score));
        $grade = $score >= 90 ? 'A' : ($score >= 75 ? 'B' : ($score >= 50 ? 'C' : ($score >= 25 ? 'D' : 'F')));

        return [$score, $grade];
    }
}
