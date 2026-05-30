<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SecurityService
{
    public function __construct(private MonitoringService $monitoring, private AlertService $alerts) {}

    // ── CATEGORY A: HTTP Security Headers (30 pts) ──────────────────────────

    private const HTTP_HEADERS = [
        // [key, label, base_points]
        ['strict-transport-security', 'Strict-Transport-Security (HSTS)', 8],
        ['x-frame-options',           'X-Frame-Options',                  5],
        ['x-content-type-options',    'X-Content-Type-Options',           5],
        ['x-xss-protection',          'X-XSS-Protection',                 2],
        ['referrer-policy',           'Referrer-Policy',                  5],
        ['permissions-policy',        'Permissions-Policy',               5],
    ];

    private const VALID_REFERRER_POLICIES = [
        'no-referrer', 'no-referrer-when-downgrade', 'origin',
        'origin-when-cross-origin', 'same-origin', 'strict-origin',
        'strict-origin-when-cross-origin', 'unsafe-url',
    ];

    public function checkHttpSecurityHeaders(array $headers): array
    {
        $norm   = collect($headers)->mapWithKeys(fn ($v, $k) => [strtolower($k) => is_array($v) ? implode('; ', $v) : $v]);
        $items  = [];
        $issues = [];
        $score  = 0;
        $max    = 0;

        foreach (self::HTTP_HEADERS as [$key, $label, $base]) {
            $max  += $base;
            $value = $norm->get($key, '');
            $present = $value !== '' && $value !== null;
            $detail  = ['present' => $present, 'value' => $present ? $value : null, 'score' => 0];

            if ($present) {
                $detail['score'] = $base;
                $score += $base;

                if ($key === 'strict-transport-security') {
                    // +2 if max-age >= 31536000
                    if (preg_match('/max-age\s*=\s*(\d+)/i', $value, $m) && (int)$m[1] >= 31536000) {
                        $detail['score'] += 2;
                        $score += 2;
                        $max   += 2;
                        $detail['hsts_long_duration'] = true;
                    }
                    // +1 if includeSubDomains
                    if (stripos($value, 'includesubdomains') !== false) {
                        $detail['score'] += 1;
                        $score += 1;
                        $max   += 1;
                        $detail['hsts_include_subdomains'] = true;
                    }
                } elseif ($key === 'referrer-policy') {
                    $rp = strtolower(trim(explode(',', $value)[0]));
                    if (!in_array($rp, self::VALID_REFERRER_POLICIES)) {
                        $issues[] = "Unknown Referrer-Policy value: {$value}";
                    }
                }
            } else {
                $issues[] = "Missing {$label}";
            }

            $items[$key] = $detail;
        }

        return ['score' => min($score, $max), 'max' => $max, 'issues' => $issues, 'items' => $items];
    }

    // ── CATEGORY B: Content Security Policy (20 pts) ────────────────────────

    public function parseCsp(string $csp): array
    {
        return collect(explode(';', $csp))->mapWithKeys(function ($directive) {
            $parts = preg_split('/\s+/', trim($directive));
            $name  = array_shift($parts);
            return $name ? [$name => $parts] : [];
        })->all();
    }

    public function checkCsp(array $headers): array
    {
        $norm = collect($headers)->mapWithKeys(fn ($v, $k) => [strtolower($k) => is_array($v) ? implode('; ', $v) : $v]);
        $csp  = $norm->get('content-security-policy', '');
        $issues = [];
        $score  = 0;
        $max    = 20;

        $details = [
            'present'         => (bool)$csp,
            'directives'      => [],
            'script_src'      => [],
            'unsafe_inline'   => false,
            'unsafe_eval'     => false,
            'wildcard_source' => false,
        ];

        if (!$csp) {
            $issues[] = 'No Content-Security-Policy header found';
            return ['score' => 0, 'max' => $max, 'issues' => $issues, 'details' => $details];
        }

        $score += 8; // presence
        $directives = $this->parseCsp((string)$csp);
        $details['directives'] = $directives;

        $scriptSrc    = $directives['script-src'] ?? [];
        $scriptSrcStr = strtolower(implode(' ', $scriptSrc));
        $details['script_src'] = $scriptSrc;

        // unsafe-inline
        if (str_contains($scriptSrcStr, 'unsafe-inline')) {
            $details['unsafe_inline'] = true;
            $issues[] = "CSP allows unsafe-inline scripts — XSS risk";
        } else {
            $score += 4;
        }

        // unsafe-eval
        if (str_contains($scriptSrcStr, 'unsafe-eval')) {
            $details['unsafe_eval'] = true;
            $issues[] = "CSP allows eval() — code injection risk";
        } else {
            $score += 4;
        }

        // wildcard
        if (in_array('*', $scriptSrc) || str_contains($scriptSrcStr, '*')) {
            $details['wildcard_source'] = true;
            $issues[] = "CSP uses wildcard source in script-src — defeats purpose of CSP";
        } else {
            $score += 4;
        }

        return ['score' => min($score, $max), 'max' => $max, 'issues' => $issues, 'details' => $details];
    }

    // ── CATEGORY C: CORS Misconfiguration (15 pts) ──────────────────────────

    public function checkCors(array $headers, string $url): array
    {
        $norm   = collect($headers)->mapWithKeys(fn ($v, $k) => [strtolower($k) => is_array($v) ? implode(', ', $v) : $v]);
        $acao   = $norm->get('access-control-allow-origin', '');
        $acac   = $norm->get('access-control-allow-credentials', '');
        $acam   = $norm->get('access-control-allow-methods', '');
        $issues = [];
        $score  = 0;
        $max    = 15;

        $details = [
            'acao_present'   => (bool)$acao,
            'acao_value'     => $acao,
            'acac_value'     => $acac,
            'acam_value'     => $acam,
            'classification' => '',
        ];

        if (!$acao) {
            $score = 15;
            $details['classification'] = 'not_exposed';
        } elseif ($acao === '*') {
            if ($acac && strtolower($acac) === 'true') {
                $issues[] = 'CRITICAL: CORS allows any origin with credentials — auth bypass risk';
                $score = 0;
                $details['classification'] = 'critical_misconfig';
            } elseif ($acam && (stripos($acam, 'delete') !== false || stripos($acam, 'put') !== false)) {
                $issues[] = 'CORS exposes DELETE/PUT to any origin';
                $score = 8;
                $details['classification'] = 'wildcard_public';
            } else {
                $score = 8;
                $details['classification'] = 'wildcard_no_credentials';
            }
            // Check dangerous methods
            if ($acam) {
                $dangerous = array_filter(['delete','put','patch'], fn ($m) => stripos($acam, $m) !== false);
                if ($dangerous) {
                    $issues[] = 'CORS exposes dangerous methods: ' . implode(', ', $dangerous);
                }
            }
        } elseif (strtolower($acao) === 'null') {
            $issues[] = 'CORS allows null origin — sandbox bypass possible';
            $score = 3;
            $details['classification'] = 'null_origin';
        } else {
            $score = 15;
            $details['classification'] = 'specific_origin';
            $details['allowed_origin'] = $acao;
        }

        return ['score' => min($score, $max), 'max' => $max, 'issues' => $issues, 'details' => $details];
    }

    // ── CATEGORY D: Mixed Content & HTTPS Quality (15 pts) ──────────────────

    public function checkMixedContent(string $html, array $headers): array
    {
        $issues = [];
        $max    = 15;

        $counts = [
            'http_scripts'     => 0,
            'http_images'      => 0,
            'http_iframes'     => 0,
            'http_stylesheets' => 0,
            'http_fonts'       => 0,
            'http_other'       => 0,
        ];

        if (!$html) {
            return ['score' => $max, 'max' => $max, 'issues' => [], 'details' => ['total_mixed' => 0, ...$counts]];
        }

        preg_match_all('/<script[^>]+src=["\']http:\/\/([^"\']+)/i', $html, $m);
        $counts['http_scripts'] = count($m[0]);

        preg_match_all('/<link[^>]+href=["\']http:\/\/([^"\']+)/i', $html, $m);
        $counts['http_stylesheets'] = count($m[0]);

        preg_match_all('/<img[^>]+src=["\']http:\/\/([^"\']+)/i', $html, $m);
        $counts['http_images'] = count($m[0]);

        preg_match_all('/<iframe[^>]+src=["\']http:\/\/([^"\']+)/i', $html, $m);
        $counts['http_iframes'] = count($m[0]);

        preg_match_all('/url\s*\(\s*["\']?http:\/\//i', $html, $m);
        $counts['http_fonts'] = count($m[0]);

        $total = array_sum($counts);

        if ($total === 0) {
            $score = $max;
        } else {
            if ($counts['http_scripts'] > 0) $issues[] = "{$counts['http_scripts']} script(s) load over HTTP — mixed content warning";
            if ($counts['http_images'] > 0)  $issues[] = "{$counts['http_images']} image(s) load over HTTP — mixed content warning in browsers";
            if ($counts['http_iframes'] > 0) $issues[] = "{$counts['http_iframes']} iframe(s) load over HTTP — security risk";
            if ($counts['http_stylesheets'] > 0) $issues[] = "{$counts['http_stylesheets']} stylesheet(s) load over HTTP — blocks HTTPS indicator";
            if ($counts['http_fonts'] > 0)   $issues[] = "{$counts['http_fonts']} font resource(s) load over HTTP — mixed content";

            $score = $counts['http_scripts'] > 0
                ? max(0, $max - ($total * 2))
                : max(0, $max - $total);
        }

        return ['score' => min($score, $max), 'max' => $max, 'issues' => $issues, 'details' => ['total_mixed' => $total, ...$counts]];
    }

    // ── CATEGORY E: Malware & Injection Signals (20 pts) ────────────────────

    private const MALWARE_PATTERNS = [
        ['eval\s*\(\s*base64_decode',                                    'high',   5, 'eval(base64_decode) — obfuscated PHP execution'],
        ['eval\s*\(\s*unescape',                                         'high',   5, 'eval(unescape) — obfuscated JS execution'],
        ['document\.write\s*\(\s*unescape',                              'medium', 4, 'document.write(unescape) — obfuscated JS injection'],
        ['coinhive',                                                      'high',   8, 'CoinHive crypto-miner'],
        ['cryptonight',                                                   'high',   8, 'CryptoNight miner'],
        ['crypto[-_]?miner',                                             'high',   8, 'Crypto-miner reference'],
        ['fromcharcode',                                                  'low',    3, 'String.fromCharCode obfuscation'],
        ['malicious[-_]?script',                                         'high',   6, 'Explicit malicious-script marker'],
        ['<script[^>]*src=[\'"]https?://[^\'\"]*\.ru/',                  'high',   6, 'External script from .ru domain'],
        ['\\\\x[0-9a-f]{2}\\\\x[0-9a-f]{2}\\\\x[0-9a-f]{2}',          'low',    3, 'Hex-encoded obfuscation sequence'],
        ['<script[^>]*>document\.location\s*=',                         'high',   6, 'Script redirecting document.location — possible hijacking'],
        ['window\.location\.replace\s*\(',                               'medium', 3, 'window.location.replace() — potential open redirect'],
        ['\batob\s*\(',                                                   'medium', 3, 'atob() — base64 decode in JavaScript'],
        ['fromcharcode.*eval',                                            'high',   6, 'fromCharCode combined with eval — obfuscation attack'],
        ['\.ru\/[a-z0-9]{8,}\.js',                                       'high',   6, 'Random-looking .ru JS path — suspicious external script'],
        ['\.xyz\/[a-z0-9]{8,}\.js',                                      'high',   5, 'Random-looking .xyz JS path — suspicious external script'],
        ['<iframe[^>]*style\s*=\s*["\'][^"\']*display\s*:\s*none',      'high',   6, 'Hidden iframe injection'],
        ['<link\\b[^>]*\\bhref=["\']http://',                             'medium', 3, 'External resource injection via link tag'],
        ['unescape\s*\(\s*%u',                                           'high',   5, 'Unicode unescape obfuscation'],
        ['javascript\s*:\s*void\s*\([^)]*\)',                            'low',    2, 'javascript:void() in href — potential phishing'],
    ];

    public function scanForMalware(string $html): array
    {
        $issues  = [];
        $flags   = [];
        $score   = 20;
        $max     = 20;
        $foundHigh = false;

        if (!$html) {
            return ['score' => $max, 'max' => $max, 'issues' => [], 'malware_flags' => []];
        }

        foreach (self::MALWARE_PATTERNS as [$pattern, $severity, $deduction, $description]) {
            if (@preg_match('~' . $pattern . '~i', $html)) {
                $flags[]  = ['pattern' => explode(' — ', $description)[0], 'severity' => $severity, 'description' => $description, 'deduction' => $deduction];
                $issues[] = $description;
                $score   -= $deduction;
                if ($severity === 'high') $foundHigh = true;
            }
        }

        $score = max(0, $score);
        if ($foundHigh) $score = 0;

        return ['score' => $score, 'max' => $max, 'issues' => $issues, 'malware_flags' => $flags];
    }

    // ── Public API ───────────────────────────────────────────────────────────

    public function runSecurityAudit(string $html, array $responseHeaders, string $url = ''): array
    {
        $headers      = $this->checkHttpSecurityHeaders($responseHeaders);
        $csp          = $this->checkCsp($responseHeaders);
        $cors         = $this->checkCors($responseHeaders, $url);
        $mixedContent = $this->checkMixedContent($html, $responseHeaders);
        $malware      = $this->scanForMalware($html);

        $total    = $headers['score'] + $csp['score'] + $cors['score'] + $mixedContent['score'] + $malware['score'];
        $maxTotal = $headers['max']   + $csp['max']   + $cors['max']   + $mixedContent['max']   + $malware['max'];
        $score    = $maxTotal > 0 ? (int) round(($total / $maxTotal) * 100) : 0;

        $allIssues = array_merge($headers['issues'], $csp['issues'], $cors['issues'], $mixedContent['issues'], $malware['issues']);

        return [
            'score' => $score,
            'grade' => $this->grade($score),
            'categories' => [
                'headers'       => ['score' => $headers['score'],      'max' => $headers['max'],      'issues' => $headers['issues'],      'details' => $headers['items']],
                'csp'           => ['score' => $csp['score'],          'max' => $csp['max'],          'issues' => $csp['issues'],          'details' => $csp['details']],
                'cors'          => ['score' => $cors['score'],         'max' => $cors['max'],         'issues' => $cors['issues'],         'details' => $cors['details']],
                'mixed_content' => ['score' => $mixedContent['score'], 'max' => $mixedContent['max'], 'issues' => $mixedContent['issues'], 'details' => $mixedContent['details']],
                'malware'       => ['score' => $malware['score'],      'max' => $malware['max'],      'issues' => $malware['issues'],      'malware_flags' => $malware['malware_flags']],
            ],
            'security_issues' => $allIssues,
            'malware_flags'   => array_column($malware['malware_flags'], 'description'),
        ];
    }

    public function runSecurityCheck(int $siteId): array
    {
        $site      = Site::findOrFail($siteId);
        $checkedAt = \Illuminate\Support\Carbon::now();
        $response  = Http::timeout(15)->get($site->url);
        $audit     = $this->runSecurityAudit($response->body(), $response->headers(), $site->url);

        $site->fill([
            'security_score'  => $audit['score'],
            'security_grade'  => $audit['grade'],
            'security_headers' => $response->headers(),
            'security_status' => $audit['score'] >= 80 ? 'ok' : ($audit['score'] >= 50 ? 'warning' : 'error'),
        ])->save();

        Log::info('Security check completed', ['site_id' => $site->id, 'score' => $audit['score'], 'grade' => $audit['grade']]);

        $this->monitoring->scheduleNextRun($site, 'security', $checkedAt);
        $this->alerts->checkSecurityAlerts($site->fresh());

        return $audit;
    }

    private function grade(float $score): string
    {
        return match (true) {
            $score >= 80 => 'A',
            $score >= 65 => 'B',
            $score >= 50 => 'C',
            $score >= 35 => 'D',
            default      => 'F',
        };
    }
}
