<?php

namespace App\Jobs;

use App\Models\Site;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ZombieRescueJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Site::query()->where(function ($query) {
            foreach (['uptime', 'ssl', 'seo', 'security', 'dns'] as $type) {
                $query->orWhere(fn ($q) => $q->where("{$type}_status", 'running')->where("{$type}_started_at", '<', now()->subMinutes(10)));
            }
        })->chunkById(100, function ($sites): void {
            $sites->each->rescueStuckTasks();
        });
    }
}
