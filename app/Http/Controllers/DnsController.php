<?php

namespace App\Http\Controllers;

use App\Models\Site;

class DnsController extends Controller
{
    public function show(Site $site)
    {
        return response()->json(['site' => $site->only(['dns_resolved', 'dns_last_ips', 'dns_last_ns', 'dns_hijack_suspected', 'dns_ns_changed']), 'logs' => $site->dnsLogs()->latest('checked_at')->limit(50)->get()]);
    }
}
