<?php

namespace App\Http\Controllers;

use App\Jobs\RunDnsCheckJob;
use App\Jobs\RunFullAuditJob;
use App\Jobs\RunSeoCheckJob;
use App\Jobs\RunSecurityCheckJob;
use App\Jobs\RunSslCheckJob;
use App\Jobs\RunUptimeCheckJob;
use App\Models\Site;
use Illuminate\Http\Request;

class CheckController extends Controller
{
    public function trigger(Request $request, Site $site)
    {
        $data = $request->validate(['type' => ['required', 'in:uptime,ssl,seo,security,dns,all']]);
        $map = ['uptime' => RunUptimeCheckJob::class, 'ssl' => RunSslCheckJob::class, 'seo' => RunSeoCheckJob::class, 'security' => RunSecurityCheckJob::class, 'dns' => RunDnsCheckJob::class];

        if ($data['type'] === 'all') {
            RunFullAuditJob::dispatch($site->id);
        } else {
            $map[$data['type']]::dispatch($site->id);
            $site->update(["{$data['type']}_status" => 'queued']);
            $site->refreshAppStatus();
        }

        return back()->with('success', 'Check queued successfully.');
    }
}
