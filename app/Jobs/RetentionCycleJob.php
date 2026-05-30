<?php

namespace App\Jobs;

use App\Services\RetentionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RetentionCycleJob implements ShouldQueue
{
    use Queueable;
    public function handle(RetentionService $service): void { $service->runRetentionCycle(); }
}
