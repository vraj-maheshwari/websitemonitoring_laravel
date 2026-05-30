<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Services\ReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function show(Site $site, Request $request)
    {
        return match ($request->query('format', 'html')) {
            'json' => response()->json($this->reports->generateSiteReport($site->id)),
            'csv' => response()->streamDownload(fn () => print($this->reports->generateSiteCsvReport($site->id)), "site-{$site->id}-report.csv", ['Content-Type' => 'text/csv']),
            default => view('reports.site-pdf', ['report' => $this->reports->generateSiteReport($site->id)]),
        };
    }
}
