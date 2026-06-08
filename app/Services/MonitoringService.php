<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Carbon;
use Carbon\CarbonInterface;

class MonitoringService
{
    public const CHECK_TYPES = ['uptime', 'ssl', 'seo', 'security', 'dns'];

    public function prepareSite(Site $site): Site
    {
        $site->url = $this->normalizeUrl($site->url);
        $site->normalized_url = $this->normalizedIdentity($site->url);
        $site->name = $site->name ?: parse_url($site->url, PHP_URL_HOST);

        foreach (self::CHECK_TYPES as $type) {
            $site->setAttribute("next_{$type}_check_at", now());
            $site->setAttribute("{$type}_status", 'queued');
        }

        $this->refreshNextCheckAt($site);
        $site->save();
        $site->refreshAppStatus();

        return $site;
    }

    public function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host = strtolower($parts['host'] ?? '');
        $path = isset($parts['path']) ? rtrim($parts['path'], '/') : '';

        return $scheme.'://'.$host.$path;
    }

    public function normalizedIdentity(string $url): string
    {
        $url = $this->normalizeUrl($url);
        $host = preg_replace('/^www\./', '', strtolower(parse_url($url, PHP_URL_HOST) ?: $url));
        $path = rtrim(parse_url($url, PHP_URL_PATH) ?: '', '/');

        return $host.$path;
    }

    public function refreshNextCheckAt(Site $site): void
    {
        $next = collect(self::CHECK_TYPES)
            ->map(fn ($type) => $site->getAttribute("next_{$type}_check_at"))
            ->filter()
            ->min();

        $site->next_check_at = $next;
    }

    public function getIntervalSeconds(Site $site, string $checkType): int
    {
        $minimums = ['uptime' => 60, 'ssl' => 3600, 'seo' => 3600, 'security' => 3600, 'dns' => 3600];

        return max((int) $site->getAttribute("{$checkType}_interval"), $minimums[$checkType] ?? 3600);
    }

    public function scheduleNextRun(Site $site, string $checkType, CarbonInterface  $checkedAt): void
    {
        $site->setAttribute("last_{$checkType}_check_at", $checkedAt);
        $site->setAttribute("next_{$checkType}_check_at", $checkedAt->copy()->addSeconds($this->getIntervalSeconds($site, $checkType)));
        $site->setAttribute("{$checkType}_started_at", null);
        $this->refreshNextCheckAt($site);
        $site->save();
        $site->refreshAppStatus();
    }

    public function getDueSiteIds(string $checkType, ?Carbon $now = null, int $limit = 100): array
    {
        return Site::query()
            ->dueForCheck($checkType, $now ?: now())
            ->orderBy("next_{$checkType}_check_at")
            ->limit($limit)
            ->pluck('id')
            ->all();
    }
}
