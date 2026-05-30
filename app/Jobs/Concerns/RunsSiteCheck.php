<?php

namespace App\Jobs\Concerns;

use App\Models\Site;
use Throwable;

trait RunsSiteCheck
{
    private function markRunning(int $siteId, string $type): ?Site
    {
        $site = Site::find($siteId);
        if (! $site) {
            return null;
        }

        if ($site->getAttribute("{$type}_status") === 'running' && optional($site->getAttribute("{$type}_started_at"))->gt(now()->subMinutes(10))) {
            return null;
        }

        $site->setAttribute("{$type}_status", 'running');
        $site->setAttribute("{$type}_started_at", now());
        $site->save();
        $site->refreshAppStatus();

        return $site;
    }

    private function failCheck(Site $site, string $type, Throwable $exception): void
    {
        $site->setAttribute("{$type}_status", 'error');
        $site->setAttribute("{$type}_started_at", null);
        $site->last_error_message = $exception->getMessage();
        $site->save();
        $site->refreshAppStatus();
    }
}
