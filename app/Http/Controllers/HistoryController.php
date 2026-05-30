<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function uptime(Request $request, Site $site) { return $this->respond($request, $site->uptimeLogs()->latest('checked_at')->limit(100)->get()); }
    public function ssl(Request $request, Site $site) { return $this->respond($request, $site->sslLogs()->latest('checked_at')->limit(100)->get()); }
    public function seo(Request $request, Site $site) { return $this->respond($request, $site->seoLogs()->latest('checked_at')->limit(100)->get()); }
    public function dns(Request $request, Site $site) { return $this->respond($request, $site->dnsLogs()->latest('checked_at')->limit(100)->get()); }
    public function uptimeSummary(Request $request, Site $site) { return $this->respond($request, $site->uptimeLogs()->selectRaw('date(checked_at) as date, avg(response_time_ms) as avg_response_time, avg(is_up) * 100 as uptime_percent')->groupBy('date')->latest('date')->limit(30)->get()); }
    public function uptimeJson(Site $site) { return response()->json($site->uptimeLogs()->latest('checked_at')->limit(50)->get()->reverse()->values()); }

    private function respond(Request $request, $data)
    {
        return $request->expectsJson() ? response()->json($data) : view('sites.history', compact('data'));
    }
}
