<?php

namespace App\Services;

use App\Models\FullLinkAuditLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FullLinkAuditService
{
    public function runFullLinkAudit(string $startUrl, int $maxDepth = 1, int $maxPages = 25, int $maxLinks = 100, ?callable $progress = null): array
    {
        $html = Http::timeout(20)->get($startUrl)->body();
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\']/i', $html, $matches);
        $links = collect($matches[1])->map(fn ($href) => $this->absoluteUrl($href, $startUrl))->filter()->unique()->take($maxLinks)->values();
        $results = $this->recheckLinks($links->map(fn ($url) => ['url' => $url])->all());

        $progress && $progress(['pages_crawled' => 1, 'links_checked' => count($results)]);

        Log::info('Full link audit completed', [
            'start_url' => $startUrl,
            'pages_crawled' => 1,
            'links_checked' => count($results),
            'broken' => collect($results)->where('state', 'broken')->count(),
            'unverified' => collect($results)->where('state', 'unverified')->count(),
        ]);

        return ['pages' => [$startUrl], 'links' => $results, 'summary' => ['broken' => collect($results)->where('state', 'broken')->count(), 'unverified' => collect($results)->where('state', 'unverified')->count()]];
    }

    public function recheckLinks(array $entries): array
    {
        return collect($entries)->map(function ($entry) {
            $url = is_array($entry) ? $entry['url'] : $entry;
            try {
                $status = Http::timeout(10)->head($url)->status();
                return ['url' => $url, 'status' => $status, 'state' => $status >= 400 ? 'broken' : 'ok'];
            } catch (\Throwable $e) {
                return ['url' => $url, 'status' => null, 'state' => 'unverified'];
            }
        })->values()->all();
    }

    public function mergeRecheckIntoAuditResults(FullLinkAuditLog $audit, array $recheckResults): FullLinkAuditLog
    {
        $results = $audit->results ?: [];
        $results['links'] = $recheckResults;
        $audit->update(['results' => $results, 'links_checked' => count($recheckResults)]);

        return $audit;
    }

    private function absoluteUrl(string $href, string $base): ?string
    {
        if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'mailto:')) {
            return null;
        }
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $parts = parse_url($base);
        return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').'/'.ltrim($href, '/');
    }
}
