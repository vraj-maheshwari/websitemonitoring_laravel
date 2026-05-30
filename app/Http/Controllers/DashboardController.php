<?php

namespace App\Http\Controllers;

use App\Models\UptimeLog;
use App\Jobs\RunUptimeCheckJob;


class DashboardController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        return view('dashboard.index', ['metrics' => $this->metrics($request), 'recentFailures' => $this->recentFailures($request)]);
    }

    public function failuresJson(\Illuminate\Http\Request $request)
    {
        return response()->json($this->recentFailures($request)->map(fn ($log) => [
            'site'       => $log->site->name ?: parse_url($log->site->url, PHP_URL_HOST),
            'status'     => $log->status,
            'status_code'=> $log->status_code,
            'checked_at' => $log->checked_at->diffForHumans(),
        ]));
    }

    public function metricsJson(\Illuminate\Http\Request $request)
    {
        return response()->json($this->metrics($request));
    }

    public function fleetCheckNow(\Illuminate\Http\Request $request)
    {
        $sites = $request->user()->sites()->get();
        $count = 0;
        foreach ($sites as $site) {
            RunUptimeCheckJob::dispatch($site->id);
            $count++;
        }

        return response()->json(['dispatched' => $count]);
    }

    private function metrics(\Illuminate\Http\Request $request): array
    {
        $sites = $request->user()->sites()->get();

        return [
            'total' => $sites->count(),
            'up' => $sites->where('current_status', 'up')->count(),
            'down' => $sites->where('current_status', 'down')->count(),
            'degraded' => $sites->where('current_status', 'degraded')->count(),
            'avg_uptime' => null,
            'ssl_expiring' => $sites->where('ssl_state', 'expiring')->count(),
            'avg_seo' => round((float) $sites->avg('seo_score'), 1),
        ];
    }

    private function recentFailures(\Illuminate\Http\Request $request)
    {
        return UptimeLog::with('site')
            ->whereHas('site', fn ($q) => $q->where('user_id', $request->user()->id))
            ->whereIn('status', ['down', 'degraded'])
            ->latest('checked_at')
            ->limit(20)
            ->get();
    }
}
