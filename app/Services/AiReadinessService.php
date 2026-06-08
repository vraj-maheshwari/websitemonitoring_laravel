<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AiReadinessService
{
    public function analyze(string $siteUrl): array
    {
        $llms = $this->checkLlms($siteUrl);
        $robots = $this->checkAiRobots($siteUrl);
        $policy = $this->detectAiPolicy($siteUrl);
        $docs = $this->detectDocumentation($siteUrl);

        $data = [
            'llms' => $llms,
            'robots' => $robots,
            'policy' => $policy,
            'docs' => $docs,
        ];

        $data['score'] = $this->calculateScore($data);

        return $data;
    }

    private function checkLlms(string $url): array
    {
        try {
            $resp = Http::timeout(10)->withoutVerifying()->get(rtrim($url, '/') . '/llms.txt');
        } catch (\Throwable $e) {
            return ['exists' => false, 'urls' => [], 'content_length' => 0];
        }

        if (!$resp->successful()) {
            return ['exists' => false, 'urls' => [], 'content_length' => 0];
        }

        $content = (string) $resp->body();
        preg_match_all('/https?:\/\/[^\s]+/', $content, $matches);

        return [
            'exists' => true,
            'urls' => $matches[0] ?? [],
            'content_length' => strlen($content),
            'content' => $content,
        ];
    }

    private function checkAiRobots(string $url): array
    {
        try {
            $resp = Http::timeout(8)->get(rtrim($url, '/') . '/robots.txt');
        } catch (\Throwable $e) {
            return [];
        }

        if (!$resp->successful()) {
            return [];
        }

        $content = Str::lower($resp->body());

        return [
            'gptbot' => Str::contains($content, 'gptbot'),
            'claudebot' => Str::contains($content, 'claudebot'),
            'google_extended' => Str::contains($content, 'google-extended'),
            'ccbot' => Str::contains($content, 'ccbot'),
        ];
    }

    private function detectAiPolicy(string $url): bool
    {
        $paths = ['/ai-policy', '/ai', '/artificial-intelligence', '/terms', '/privacy'];

        foreach ($paths as $path) {
            try {
                $resp = Http::timeout(6)->head(rtrim($url, '/') . $path);
            } catch (\Throwable $e) {
                continue;
            }

            if ($resp->successful()) {
                return true;
            }
        }

        return false;
    }

    private function detectDocumentation(string $url): bool
    {
        $paths = ['/docs', '/documentation', '/api', '/developers', '/help'];

        foreach ($paths as $path) {
            try {
                $resp = Http::timeout(6)->head(rtrim($url, '/') . $path);
            } catch (\Throwable $e) {
                continue;
            }

            if ($resp->successful()) {
                return true;
            }
        }

        return false;
    }

    private function calculateScore(array $data): int
    {
        $score = 0;

        if (!empty($data['llms']['exists'])) {
            $score += 30;
        }

        if (!empty($data['robots']['gptbot'])) {
            $score += 15;
        }

        if (!empty($data['robots']['claudebot'])) {
            $score += 15;
        }

        if (!empty($data['policy'])) {
            $score += 20;
        }

        if (!empty($data['docs'])) {
            $score += 20;
        }

        return min(100, (int) $score);
    }
}
