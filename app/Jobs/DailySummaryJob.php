<?php

namespace App\Jobs;

use App\Services\SummaryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DailySummaryJob implements ShouldQueue
{
    use Queueable;
    public function handle(SummaryService $service): void { $service->runDailySummary(); }
}
