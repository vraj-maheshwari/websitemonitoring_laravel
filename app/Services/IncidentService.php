<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\Site;
use Illuminate\Support\Carbon;

class IncidentService
{
    public function detectRootCause(?string $error, ?int $statusCode): string
    {
        $text = strtolower((string) $error);

        return match (true) {
            str_contains($text, 'dns') || str_contains($text, 'could not resolve') => 'dns',
            str_contains($text, 'ssl') || str_contains($text, 'certificate') => 'ssl',
            str_contains($text, 'timeout') || str_contains($text, 'timed out') => 'timeout',
            $statusCode >= 500 => 'server_error',
            $statusCode >= 400 => 'client_error',
            $error !== null => 'network',
            default => 'unknown',
        };
    }

    public function makeTimelineEvent(string $type, string $message, ?Carbon $at = null): array
    {
        return ['type' => $type, 'message' => $message, 'at' => ($at ?: now())->toIso8601String()];
    }

    public function appendTimelineEvent(Incident $incident, array $event): void
    {
        $timeline = $incident->timeline ?: [];
        $timeline[] = $event;
        $incident->update(['timeline' => $timeline]);
    }

    public function openIncidentWithRca(Site $site, string $cause, array $firstEvent): Incident
    {
        return $site->incidents()->create([
            'status' => 'OPEN',
            'root_cause' => $cause,
            'opened_at' => now(),
            'timeline' => [$firstEvent],
        ]);
    }

    public function updateIncidentTimeline(Incident $incident, array $event): void
    {
        $this->appendTimelineEvent($incident, $event);
    }

    public function resolveIncidentWithTimeline(Incident $incident, array $recoveryEvent): void
    {
        $this->appendTimelineEvent($incident, $recoveryEvent);
        $incident->update(['status' => 'RESOLVED', 'resolved_at' => now()]);
    }
}
