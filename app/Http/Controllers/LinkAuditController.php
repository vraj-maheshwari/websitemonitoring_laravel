<?php

namespace App\Http\Controllers;

use App\Jobs\RunFullLinkAuditJob;
use App\Models\Site;
use App\Services\BrokenLinksUnifiedService;
use Illuminate\Http\Request;

class LinkAuditController extends Controller
{
    public function __construct(private BrokenLinksUnifiedService $links) {}
    public function brokenLinks(Site $site) { return response()->json($this->links->getUnifiedBrokenLinks($site->id)); }
    public function recheckBrokenLinks(Site $site) { return back()->with('success', 'Rechecked '.count($this->links->recheckUnifiedBrokenLinks($site->id)).' links.'); }
    public function startAudit(Request $request, Site $site)
    {
        $data = $request->validate(['max_depth' => ['nullable', 'integer', 'min:1', 'max:3'], 'max_pages' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $audit = $site->fullLinkAuditLogs()->create(['max_depth' => $data['max_depth'] ?? 1, 'max_pages' => $data['max_pages'] ?? 25]);
        RunFullLinkAuditJob::dispatch($site->id, $audit->id);
        return back()->with('success', 'Full link audit queued.');
    }
    public function auditList(Site $site) { return response()->json($site->fullLinkAuditLogs()->latest()->limit(20)->get()); }
    public function latestAudit(Site $site) { return response()->json($site->fullLinkAuditLogs()->latest()->first()); }
}
