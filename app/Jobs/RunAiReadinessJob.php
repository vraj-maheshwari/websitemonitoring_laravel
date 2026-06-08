<?php

namespace App\Jobs;

use App\Models\Site;
use App\Models\SeoLog;
use App\Services\AiReadinessService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunAiReadinessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $siteId) {}

    public function handle(AiReadinessService $aiService): void
    {
        $site = Site::find($this->siteId);
        if (! $site) return;

        try {
            $ai = $aiService->analyze($site->url);

            SeoLog::create([
                'site_id' => $site->id,
                'checked_at' => now(),
                'ai_details' => $ai,
                'llms_exists' => $ai['llms']['exists'] ?? false,
                'gptbot_allowed' => $ai['robots']['gptbot'] ?? null,
                'claudebot_allowed' => $ai['robots']['claudebot'] ?? null,
                'google_extended_allowed' => $ai['robots']['google_extended'] ?? null,
                'ai_policy_found' => !empty($ai['policy']),
                'docs_found' => !empty($ai['docs']),
                'ai_score' => $ai['score'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('AI readiness check failed', ['site_id' => $site->id, 'error' => $e->getMessage()]);
        }
    }
}
