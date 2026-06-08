<?php

namespace App\Http\Controllers;

use App\Jobs\RunDnsCheckJob;
use App\Jobs\RunFullAuditJob;
use App\Jobs\RunSeoCheckJob;
use App\Jobs\RunSecurityCheckJob;
use App\Jobs\RunSslCheckJob;
use App\Jobs\RunUptimeCheckJob;
use App\Jobs\RunAiReadinessJob;
use App\Models\Site;
use Illuminate\Http\Request;

class CheckController extends Controller
{
    public function trigger(Request $request, Site $site)
    {
        $data = $request->validate(['type' => ['required', 'in:uptime,ssl,seo,lighthouse,security,dns,ai,all']]);
        $map = [
            'uptime' => RunUptimeCheckJob::class,
            'ssl' => RunSslCheckJob::class,
            'seo' => RunSeoCheckJob::class,
            'lighthouse' => RunSeoCheckJob::class,
            'security' => RunSecurityCheckJob::class,
            'dns' => RunDnsCheckJob::class,
            'ai' => RunAiReadinessJob::class,
        ];

        if ($data['type'] === 'all') {
            RunFullAuditJob::dispatch($site->id);
        } else {
            $map[$data['type']]::dispatch($site->id);
            $statusColumn = $data['type'] === 'lighthouse' ? 'seo_status' : (in_array($data['type'], ['ai','seo']) ? 'seo_status' : "{$data['type']}_status");
            $site->update([$statusColumn => 'queued']);
            $site->refreshAppStatus();
        }

        return back()->with('success', 'Check queued successfully.');
    }
}
