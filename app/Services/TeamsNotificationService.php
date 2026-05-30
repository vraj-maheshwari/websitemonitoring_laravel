<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TeamsNotificationService
{
    public function sendTeamsAlert(string $subject, string $body): void
    {
        $url = config('services.teams.webhook_url') ?: env('TEAMS_WEBHOOK_URL');
        if (! $url) {
            return;
        }

        Http::timeout(5)->post($url, ['title' => $subject, 'text' => $body]);
    }
}
