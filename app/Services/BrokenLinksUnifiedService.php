<?php

namespace App\Services;

use App\Models\Site;

class BrokenLinksUnifiedService
{
    public function __construct(private FullLinkAuditService $audits) {}

    public function getUnifiedBrokenLinks(int $siteId): array
    {
        $site = Site::findOrFail($siteId);
        $seoLinks = $site->seoLogs()->latest('checked_at')->first()?->broken_links ?: [];
        $auditLinks = $site->fullLinkAuditLogs()->latest('created_at')->first()?->results['links'] ?? [];

        return collect(array_merge($seoLinks, $auditLinks))
            ->whereIn('state', ['broken', 'unverified'])
            ->unique('url')
            ->values()
            ->all();
    }

    public function recheckUnifiedBrokenLinks(int $siteId): array
    {
        return $this->audits->recheckLinks($this->getUnifiedBrokenLinks($siteId));
    }
}
