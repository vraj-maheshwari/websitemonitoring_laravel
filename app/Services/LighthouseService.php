<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class LighthouseService
{
    public function runAudit(string $url): ?array
    {
        $script = base_path('tools/lighthouse-audit.js');

        if (! file_exists($script)) {
            Log::warning('Lighthouse audit script is missing', ['script' => $script]);
            return null;
        }

        $process = new Process([
            env('NODE_BINARY', 'node'),
            $script,
            '--stdout',
            '--url=' . $url,
        ]);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            Log::warning('Lighthouse audit failed', [
                'url' => $url,
                'exit_code' => $process->getExitCode(),
                'stderr' => $process->getErrorOutput(),
                'stdout' => substr($process->getOutput(), 0, 2000),
            ]);

            return null;
        }

        $output = trim($process->getOutput());

        if ($output === '') {
            Log::warning('Lighthouse audit produced empty JSON output', ['url' => $url]);
            return null;
        }

        $decoded = json_decode($output, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Lighthouse audit JSON decode failed', [
                'url' => $url,
                'error' => json_last_error_msg(),
                'output' => substr($output, 0, 2000),
            ]);
            return null;
        }

        return $decoded;
    }
}
