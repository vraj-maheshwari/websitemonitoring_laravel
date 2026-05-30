<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(private AnalyticsService $analytics) {}
    public function site(Site $site) { return view('analytics.site', ['site' => $site, 'analytics' => $this->analytics->getSiteAnalytics($site->id)]); }
    public function fleet(Request $request)
    {
        return view('analytics.index', ['analytics' => $this->analytics->getFleetAnalytics($request->user()->id)]);
    }

    public function siteJson(Site $site)
    {
        return response()->json($this->analytics->getSiteAnalytics($site->id));
    }

    public function fleetJson(Request $request)
    {
        $siteIds = $request->query('site_ids');
        if (is_string($siteIds)) {
            $siteIds = array_filter(array_map('intval', explode(',', $siteIds)));
        }
        $live = $request->boolean('live');
        $minutes = (int) $request->query('minutes', 60);

        return response()->json($this->analytics->getFleetAnalytics($request->user()->id, days: 7, siteIds: $siteIds, live: $live, minutes: $minutes));
    }
}
