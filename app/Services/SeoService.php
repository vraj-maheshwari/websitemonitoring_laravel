<?php

namespace App\Services;

use App\Models\SeoLog;
use App\Models\Site;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SeoService
{
    public function __construct(private MonitoringService $monitoring, private SecurityService $security, private AlertService $alerts) {}

    public function shouldSkipForCooldown(Site $site): bool
    {
        return $site->last_downtime_ended_at && $site->last_downtime_ended_at->gt(now()->subMinutes(5));
    }

    public function runSeoCheck(int $siteId): SeoLog
    {
        $site = Site::findOrFail($siteId);
        $checkedAt = \Illuminate\Support\Carbon::now();

        if ($this->shouldSkipForCooldown($site)) {
            $this->monitoring->scheduleNextRun($site, 'seo', $checkedAt);
            return $site->seoLogs()->latest('checked_at')->first() ?: SeoLog::create(['site_id' => $site->id, 'checked_at' => $checkedAt, 'state' => 'unknown']);
        }

        $fetch = $this->fetchPage($site->url);
        $response = $fetch['response'];
        $html = $fetch['html'];
        $signals = $this->parseSignals($html, $site->url, $site->tracked_keywords ?: []);
        $signals['_fetch'] = $fetch['diagnostics'];

        // Check robots.txt and sitemap.xml existence
        try {
            $robotsOk = Http::timeout(8)->head(rtrim($site->url, '/') . '/robots.txt')->successful();
            $sitemapOk = Http::timeout(8)->head(rtrim($site->url, '/') . '/sitemap.xml')->successful();
        } catch (\Throwable) {
            $robotsOk = false;
            $sitemapOk = false;
        }
        $signals['has_robots']      = $robotsOk;
        $signals['has_robots_txt']  = $robotsOk;
        $signals['has_sitemap']     = $sitemapOk;
        $signals['has_sitemap_xml'] = $sitemapOk;
        $signals['ttfb']            = $fetch['diagnostics']['ttfb'] ?? null;
        $signals['total_response_time'] = $fetch['diagnostics']['total_response_time'] ?? null;
        $signals['https_redirect']  = str_starts_with($site->url, 'https://');
        $score = $this->score($signals, $response->status(), $site->url);
        $securityAudit = $this->security->runSecurityAudit($html, $response->headers(), $site->url);
        $cwv = $this->estimateCwv($html, (float) ($site->last_ttfb ?: 500));

        $log = SeoLog::create([
            'site_id' => $site->id,
            'checked_at' => $checkedAt,
            'score' => $score['score'],
            'state' => $score['state'],
            'fetch_is_valid' => $fetch['is_valid'],
            'fetch_status' => $fetch['diagnostics']['status_label'],
            'fetch_html_preview' => $fetch['preview'],
            'seo_signals' => $signals,
            'issues' => $score['issues'],
            'recommendations' => $score['recommendations'],
            'cwv_estimate' => $cwv,
            'tech_stack' => $this->detectTechnology($html, $response->headers()),
            'broken_links' => $this->quickBrokenLinks($signals['links']),
            'security_categories' => $securityAudit['categories'],
            'security_score' => $securityAudit['score'],
            'security_grade' => $securityAudit['grade'],
            'security_headers' => $response->headers(),
        ]);

        Log::info('SEO check completed', [
            'site_id' => $site->id,
            'url' => $site->url,
            'score' => $score['score'],
            'state' => $score['state'],
            'issues' => $score['issues'],
            'recommendations' => $score['recommendations'],
            'performance' => $cwv,
            'security_score' => $securityAudit['score'],
            'security_grade' => $securityAudit['grade'],
            'fetch' => $fetch['diagnostics'],
        ]);

        $site->fill([
            'seo_score' => $score['score'],
            'seo_state' => $score['state'],
            'seo_status' => $fetch['is_valid'] ? ($score['score'] >= 75 ? 'ok' : 'warning') : 'error',
            'performance_score' => $cwv['performance_score'],
            'lcp_ms' => $cwv['lcp_ms'],
            'fcp_ms' => $cwv['fcp_ms'],
            'tbt_ms' => $cwv['tbt_ms'],
            'cls' => $cwv['cls'],
            'security_score' => $securityAudit['score'],
            'security_grade' => $securityAudit['grade'],
            'security_headers' => $response->headers(),
        ])->save();

        return $log;
    }

    private function fetchPage(string $url): array
    {
        $effectiveUrl = $url;
        $response = Http::withHeaders([
            'User-Agent' => env('HTTP_USER_AGENT', 'Mozilla/5.0 (compatible; WebsiteMonitor/1.0; +https://localhost)'),
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
        ])
            ->timeout(25)
            ->connectTimeout(10)
            ->retry(1, 250)
            ->withOptions([
                'allow_redirects' => ['track_redirects' => true],
                'on_stats' => function ($stats) use (&$effectiveUrl): void {
                    $effectiveUrl = (string) $stats->getEffectiveUri();
                },
            ])
            ->get($url);

        $html = (string) $response->body();
        $plainText = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html))));
        $contentType = $response->header('Content-Type');
        $isHtml = str_contains(strtolower((string) $contentType), 'html') || str_contains(strtolower($html), '<html') || str_contains(strtolower($html), '<!doctype');
        $isValid = $response->successful() && $isHtml && strlen($plainText) >= 50;

        return [
            'response' => $response,
            'html' => $html,
            'preview' => substr($plainText, 0, 500),
            'is_valid' => $isValid,
            'diagnostics' => [
                'status_code' => $response->status(),
                'status_label' => $response->status().' '.($isValid ? 'valid-html' : 'invalid-html'),
                'effective_url' => $effectiveUrl,
                'content_type' => $contentType,
                'html_bytes' => strlen($html),
                'text_bytes' => strlen($plainText),
                'looks_like_html' => $isHtml,
                'redirect_history' => $response->header('X-Guzzle-Redirect-History') ? explode(', ', $response->header('X-Guzzle-Redirect-History')) : [],
                'reason_if_empty' => $this->emptyReason($response->status(), $html, $plainText, $isHtml),
            ],
        ];
    }

    private function emptyReason(int $status, string $html, string $plainText, bool $isHtml): ?string
    {
        if ($status >= 400) {
            return "HTTP {$status} response";
        }
        if (trim($html) === '') {
            return 'Empty response body';
        }
        if (! $isHtml) {
            return 'Response is not HTML';
        }
        if (strlen($plainText) < 50) {
            return 'HTML has very little visible text; site may be JS-rendered, blocked, or a placeholder';
        }

        return null;
    }

    private function parseSignals(string $html, string $url, array $keywords): array
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . ($html ?: '<html></html>'), LIBXML_NOWARNING | LIBXML_NOERROR);
        $xpath = new \DOMXPath($dom);
        // Strip style and script blocks before extracting text
        $cleanHtml = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        $cleanHtml = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $cleanHtml);
        $text  = trim(preg_replace('/\s+/', ' ', strip_tags($cleanHtml)));
        $words = $text !== '' ? preg_split('/\s+/', strtolower($text)) : [];
        // Count only real words (alphabetic) for word count metric
        $realWords = array_filter($words, fn ($w) => preg_match('/^[a-z]{2,}$/i', $w));
        $wordCount = count($realWords);

        // ── Links ──────────────────────────────────────────────────────────
        $links = [];
        $internalCount = 0;
        $externalCount = 0;
        $linksWithAnchor = 0;
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        foreach ($xpath->query('//a[@href]') as $node) {
            $href = $node->getAttribute('href');
            $abs  = $this->absoluteUrl($href, $url);
            if ($abs) {
                $links[] = $abs;
                $linkHost = parse_url($abs, PHP_URL_HOST) ?: '';
                if ($linkHost === $host) $internalCount++; else $externalCount++;
            }
            if (trim($node->textContent) !== '') $linksWithAnchor++;
        }

        // ── Images ────────────────────────────────────────────────────────
        $imgTotal      = $xpath->query('//img')->length;
        $imgWithoutAlt = $xpath->query('//img[not(@alt) or @alt=""]')->length;
        $imgWithAlt    = $imgTotal - $imgWithoutAlt;
        $altCoverage   = $imgTotal > 0 ? round($imgWithAlt / $imgTotal, 4) : 1.0;

        // ── Headings ──────────────────────────────────────────────────────
        $h1 = array_map(fn ($n) => trim($n->textContent), iterator_to_array($xpath->query('//h1')));
        $h2 = array_map(fn ($n) => trim($n->textContent), iterator_to_array($xpath->query('//h2')));

        // ── Meta / head signals ───────────────────────────────────────────
        $title = trim($xpath->evaluate('string(//title)'))
            ?: $this->matchFirst('/<title[^>]*>(.*?)<\/title>/is', $html);
        $metaDescription = trim($xpath->evaluate('string(//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="description"]/@content)'))
            ?: $this->matchFirst('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\']/i', $html)
            ?: $this->matchFirst('/<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']description["\']/i', $html);
        $canonical  = trim($xpath->evaluate('string(//link[translate(@rel,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="canonical"]/@href)'))
            ?: $this->matchFirst('/<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']*)["\']/i', $html);
        $viewport   = trim($xpath->evaluate('string(//meta[@name="viewport"]/@content)'))
            ?: $this->matchFirst('/<meta[^>]+name=["\']viewport["\'][^>]+content=["\']([^"\']*)["\']/i', $html);
        $robotsMeta = trim($xpath->evaluate('string(//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="robots"]/@content)'));
        $htmlLang   = trim($xpath->evaluate('string(//html/@lang)'));
        $hasFavicon = $xpath->query('//link[contains(translate(@rel,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"icon")]')->length > 0;
        $hreflangNodes = $xpath->query('//link[@hreflang]');
        $structuredData = $xpath->query('//script[@type="application/ld+json"]')->length;

        // ── Resources ─────────────────────────────────────────────────────
        $cssTotal    = $xpath->query('//link[@rel="stylesheet"]')->length;
        $cssBlocking = $xpath->query('//head//link[@rel="stylesheet"]')->length;
        $jsTotal     = $xpath->query('//script[@src]')->length;
        $jsBlocking  = $xpath->query('//head//script[@src][not(@async)][not(@defer)]')->length;

        // ── Keyword density ───────────────────────────────────────────────
        $textLower    = strtolower($text);
        $totalWords   = max(1, $wordCount);
        $keywordDensity = [];
        foreach ($keywords as $kw) {
            $kw = strtolower(trim($kw));
            if ($kw === '') continue;
            $count = substr_count($textLower, $kw);
            if ($count > 0) {
                $keywordDensity[] = [
                    'keyword' => $kw,
                    'count'   => $count,
                    'density' => round($count / $totalWords * 100, 2) . '%',
                ];
            }
        }
        // Auto top-10 keywords if none tracked
        $autoKeywords = [];
        if (empty($keywords)) {
            // Only count pure alphabetic words (no CSS/JS tokens like display:, !important, etc.)
            $pureWords = array_filter($words, fn ($w) => strlen($w) > 4 && preg_match('/^[a-z]+$/i', $w));
            $freq = array_count_values($pureWords);
            // Remove common stop words
            $stopWords = ['about','after','also','been','before','between','could','every','first','from','have',
                'here','into','just','like','more','most','much','only','other','over','same','some','such',
                'than','that','their','them','then','there','these','they','this','those','through','under',
                'very','well','were','what','when','where','which','while','will','with','would','your'];
            foreach ($stopWords as $sw) unset($freq[$sw]);
            arsort($freq);
            foreach (array_slice($freq, 0, 10, true) as $kw => $count) {
                $autoKeywords[] = [
                    'keyword' => $kw,
                    'count'   => $count,
                    'density' => round($count / $totalWords * 100, 2) . '%',
                ];
            }
        }

        $hasNoindex = (bool) preg_match('/noindex/i', $robotsMeta)
            || $xpath->query('//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="robots"][contains(translate(@content,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"noindex")]')->length > 0;

        return [
            // Core
            'title'                      => html_entity_decode($title),
            'title_text'                 => html_entity_decode($title),
            'title_length'               => strlen($title),
            'title_present'              => $title !== '',
            'title_in_optimal_range'     => strlen($title) >= 10 && strlen($title) <= 70,
            'meta_description'           => html_entity_decode($metaDescription),
            'meta_description_text'      => html_entity_decode($metaDescription),
            'meta_description_length'    => strlen($metaDescription),
            'meta_description_present'   => $metaDescription !== '',
            'meta_description_in_optimal_range' => strlen($metaDescription) > 0 && strlen($metaDescription) <= 180,
            'meta_length'                => strlen($metaDescription),
            // Headings
            'h1'                         => $h1 ?: $this->matchAll('/<h1[^>]*>(.*?)<\/h1>/is', $html),
            'h1_list'                    => $h1 ?: $this->matchAll('/<h1[^>]*>(.*?)<\/h1>/is', $html),
            'h1_text'                    => $h1 ?: $this->matchAll('/<h1[^>]*>(.*?)<\/h1>/is', $html),
            'h1_count'                   => count($h1),
            'h1_present'                 => count($h1) > 0,
            'h2'                         => $h2,
            'h2_count'                   => count($h2),
            'h3_count'                   => $xpath->query('//h3')->length,
            'h4_count'                   => $xpath->query('//h4')->length,
            'h5_count'                   => $xpath->query('//h5')->length,
            'h6_count'                   => $xpath->query('//h6')->length,
            'has_logical_hierarchy'      => count($h1) === 1 && count($h2) > 0,
            // Canonical / meta
            'canonical'                  => $canonical,
            'canonical_url'              => $canonical,
            'has_canonical'              => $canonical !== '',
            'viewport'                   => $viewport,
            'has_viewport'               => $viewport !== '',
            'mobile_friendly'            => $viewport !== '',
            'robots_meta'                => $robotsMeta,
            'robots_meta_content'        => $robotsMeta,
            'has_noindex'                => $hasNoindex,
            'html_lang'                  => $htmlLang,
            'lang_attribute'             => $htmlLang,
            'has_lang'                   => $htmlLang !== '',
            'has_favicon'                => $hasFavicon,
            'has_hreflang'               => $hreflangNodes->length > 0,
            'hreflang_count'             => $hreflangNodes->length,
            // Images
            'images_total'               => $imgTotal,
            'img_count'                  => $imgTotal,
            'image_count'                => $imgTotal,
            'images_without_alt'         => $imgWithoutAlt,
            'img_without_alt'            => $imgWithoutAlt,
            'missing_alt_count'          => $imgWithoutAlt,
            'img_with_alt'               => $imgWithAlt,
            'alt_text_coverage'          => $altCoverage,
            // Links
            'links'                      => array_values(array_unique(array_filter($links))),
            'internal_link_count'        => $internalCount,
            'external_link_count'        => $externalCount,
            'links_with_anchor_text'     => $linksWithAnchor,
            // Resources
            'css_total'                  => $cssTotal,
            'css_blocking_count'         => $cssBlocking,
            'js_total'                   => $jsTotal,
            'js_blocking_count'          => $jsBlocking,
            // Structured data
            'structured_data'            => $structuredData,
            // Content
            'word_count'                 => $wordCount,
            'page_size_kb'               => round(strlen($html) / 1024, 2),
            'meets_word_count_threshold' => $wordCount >= 300,
            'has_mixed_content'          => (bool) preg_match('/<[^>]+(?:src|href)=["\']http:\/\//i', $html),
            'mixed_content_count'        => preg_match_all('/<[^>]+(?:src|href)=["\']http:\/\//i', $html),
            'https_redirect'             => str_starts_with($url, 'https://'),
            // Keywords
            'keyword_density'            => !empty($keywordDensity) ? $keywordDensity : $autoKeywords,
            'custom_keyword_density'     => $keywordDensity,
            // Robots / sitemap (checked separately in runSeoCheck)
            'has_robots'                 => false,
            'has_robots_txt'             => false,
            'has_sitemap'                => false,
            'has_sitemap_xml'            => false,
        ];
    }

    private function matchFirst(string $pattern, string $html): string
    {
        preg_match($pattern, $html, $matches);

        return isset($matches[1]) ? trim(strip_tags($matches[1])) : '';
    }

    private function matchAll(string $pattern, string $html): array
    {
        preg_match_all($pattern, $html, $matches);

        return collect($matches[1] ?? [])->map(fn ($value) => trim(strip_tags($value)))->filter()->values()->all();
    }

    private function score(array $signals, int $status, string $url): array
    {
        $points = 0;
        $issues = [];
        $recommendations = [];
        $checks = [
            'fetch' => [($signals['_fetch']['reason_if_empty'] ?? null) === null, 10, 'Fetch did not return a valid HTML page: '.($signals['_fetch']['reason_if_empty'] ?? 'unknown reason').'.'],
            'title' => [$signals['title'] !== '' && strlen($signals['title']) >= 10 && strlen($signals['title']) <= 70, 15, 'Improve the title tag.'],
            'meta_description' => [$signals['meta_description'] !== '' && strlen($signals['meta_description']) <= 180, 15, 'Add a concise meta description.'],
            'h1' => [count($signals['h1']) === 1, 10, 'Use exactly one H1.'],
            'https' => [str_starts_with($url, 'https://'), 10, 'Serve the page over HTTPS.'],
            'canonical' => [$signals['canonical'] !== '', 5, 'Add a canonical URL.'],
            'image_alt' => [$signals['images_total'] === 0 || $signals['images_without_alt'] === 0, 10, 'Add alt text to images.'],
            'status' => [$status < 400, 10, 'Fix the HTTP status response.'],
            'structured_data' => [$signals['structured_data'] > 0, 5, 'Add JSON-LD structured data.'],
            'viewport' => [$signals['viewport'] !== '', 5, 'Add a mobile viewport tag.'],
            'keywords' => [empty($signals['keyword_density']) || max($signals['keyword_density']) > 0, 5, 'Use tracked keywords in visible content.'],
        ];

        foreach ($checks as $passed) {
            if ($passed[0]) {
                $points += $passed[1];
            } else {
                $issues[] = $passed[2];
                $recommendations[] = $passed[2];
            }
        }

        return ['score' => min(100, $points), 'state' => $points >= 75 ? 'good' : ($points >= 50 ? 'warning' : 'poor'), 'issues' => $issues, 'recommendations' => $recommendations];
    }

    private function estimateCwv(string $html, float $ttfb): array
    {
        $scriptCount  = $html ? substr_count(strtolower($html), '<script') : 0;
        $cssCount     = $html ? substr_count(strtolower($html), '<link rel="stylesheet"') : 0;
        $imgCount     = $html ? substr_count(strtolower($html), '<img') : 0;
        $imgNoAlt     = $html ? preg_match_all('/<img(?![^>]*\balt\s*=)[^>]*>/i', $html) : 0;
        $pageSizeKb   = round(strlen($html) / 1024, 2);

        // LCP estimate: TTFB + render delay from blocking resources + page size
        $renderDelay = ($scriptCount * 0.25) + ($cssCount * 0.05) + ($pageSizeKb / 1000);
        $lcpS        = round($ttfb / 1000 + $renderDelay, 2);

        // FID/INP estimate: blocking scripts in head
        $jsBlocking  = $html ? preg_match_all('/<head[^>]*>.*?<script[^>]+src[^>]*(?!async|defer)[^>]*>/is', $html) : 0;
        $fidMs       = $jsBlocking * 80;

        // CLS estimate: images without alt/dimensions
        $clsEstimate = $imgNoAlt > 0 ? min(2.5, round($imgNoAlt * 0.05, 2)) : 0.0;

        // Ratings
        $lcpRating = $lcpS <= 2.5 ? 'good' : ($lcpS <= 4.0 ? 'needs_improvement' : 'poor');
        $fidRating = $fidMs <= 100 ? 'good' : ($fidMs <= 300 ? 'needs_improvement' : 'poor');
        $clsRating = $clsEstimate <= 0.1 ? 'good' : ($clsEstimate <= 0.25 ? 'needs_improvement' : 'poor');

        // Performance score (0-100)
        $perfScore = max(0, min(100, 100 - ($lcpS * 5) - ($fidMs / 50) - ($clsEstimate * 20)));

        return [
            'performance_score' => round($perfScore),
            'lcp_ms'            => round($lcpS * 1000, 2),
            'lcp_estimate_s'    => $lcpS,
            'lcp_note'          => "Estimated from TTFB ({$ttfb}ms) + render delay ({$renderDelay}s from {$jsBlocking} blocking scripts, {$cssCount} blocking stylesheets, {$pageSizeKb} KB page). Not a real browser measurement.",
            'lcp_rating'        => $lcpRating,
            'fcp_ms'            => round($lcpS * 1000 * 0.6, 2),
            'fid_estimate_ms'   => $fidMs,
            'fid_note'          => "Estimated from {$jsBlocking} render-blocking script(s) in <head>. Each blocking script delays interactivity by ~80ms. Not a real browser measurement.",
            'fid_rating'        => $fidRating,
            'tbt_ms'            => $scriptCount * 35,
            'cls'               => $clsEstimate,
            'cls_estimate'      => $clsEstimate,
            'cls_note'          => $imgNoAlt > 0 ? "Estimated from {$imgNoAlt} image(s) missing alt text (images without alt often also lack explicit dimensions, a primary CLS cause). Not a real browser measurement." : "No images missing alt text detected.",
            'cls_rating'        => $clsRating,
        ];
    }

    private function detectTechnology(string $html, array $headers): array
    {
        $lower = strtolower($html);
        $server = $headers['Server'][0] ?? $headers['server'][0] ?? null;
        $poweredBy = $headers['X-Powered-By'][0] ?? $headers['x-powered-by'][0] ?? null;
        $serverLower = strtolower((string) $server);
        $poweredByLower = strtolower((string) $poweredBy);

        $cms = [];
        $frameworks = [];
        $analytics = [];
        $backend = [];
        $infrastructure = [];

        // Future improvement: use a browser renderer such as Playwright,
        // Puppeteer, or another headless browser to inspect hydrated DOM and
        // runtime globals. Raw Http::get() HTML can miss JS-rendered React,
        // Vue, and Next.js applications after production builds.
        if ($this->containsAny($lower, ['wp-content', 'wp-json'])) {
            $cms[] = 'WordPress';
        }
        if ($this->containsAny($lower, ['sites/default'])) {
            $cms[] = 'Drupal';
        }
        if ($this->containsAny($lower, ['joomla'])) {
            $cms[] = 'Joomla';
        }
        if ($this->containsAny($lower, ['shopify', 'cdn.shopify'])) {
            $cms[] = 'Shopify';
        }
        if ($this->containsAny($lower, ['mage'])) {
            $cms[] = 'Magento';
        }

        if ($this->containsAny($lower, ['id="root"', "id='root'", 'data-reactroot', 'react-dom', '__react_devtools_global_hook__'])) {
            $frameworks[] = 'React';
        }
        if ($this->containsAny($lower, ['__next', '_next/static', 'nextexport'])) {
            $frameworks[] = 'Next.js';
        }
        if ($this->containsAny($lower, ['id="app"', "id='app'", 'data-v-', '__vue__'])) {
            $frameworks[] = 'Vue';
        }
        if ($this->containsAny($lower, ['ng-version', 'ng-app'])) {
            $frameworks[] = 'Angular';
        }
        if ($this->containsAny($lower, ['csrf-token', '/livewire/', '/vendor/livewire/'])) {
            $frameworks[] = 'Laravel';
        }

        if ($this->containsAny($lower, ['google-analytics', 'googletagmanager', 'gtag'])) {
            $analytics[] = 'Google Analytics/GTM';
        }
        if ($this->containsAny($lower, ['connect.facebook.net'])) {
            $analytics[] = 'Facebook Pixel';
        }
        if ($this->containsAny($lower, ['hotjar'])) {
            $analytics[] = 'Hotjar';
        }
        if ($this->containsAny($lower, ['clarity.ms'])) {
            $analytics[] = 'Microsoft Clarity';
        }

        if ($this->containsAny($poweredByLower, ['php'])) {
            $backend[] = 'PHP';
        }
        if ($this->containsAny($poweredByLower, ['express'])) {
            $backend[] = 'Node.js';
        }
        if ($this->containsAny($poweredByLower, ['asp.net'])) {
            $backend[] = 'ASP.NET';
        }
        if ($this->containsAny($poweredByLower, ['django'])) {
            $backend[] = 'Django';
        }
        if ($this->containsAny($poweredByLower, ['ruby on rails', 'rails'])) {
            $backend[] = 'Ruby on Rails';
        }

        if ($this->containsAny($serverLower, ['nginx'])) {
            $infrastructure[] = 'Nginx';
        }
        if ($this->containsAny($serverLower, ['openresty'])) {
            $infrastructure[] = 'OpenResty';
            $infrastructure[] = 'Nginx';
        }
        if ($this->containsAny($serverLower, ['apache'])) {
            $infrastructure[] = 'Apache';
        }
        if ($this->containsAny($serverLower, ['cloudflare'])) {
            $infrastructure[] = 'Cloudflare';
        }
        if ($this->containsAny($serverLower, ['litespeed'])) {
            $infrastructure[] = 'LiteSpeed';
        }
        if ($this->containsAny($serverLower, ['iis', 'microsoft-iis'])) {
            $infrastructure[] = 'IIS';
        }

        return [
            'cms' => array_values(array_unique($cms)),
            'frameworks' => array_values(array_unique($frameworks)),
            'analytics' => array_values(array_unique($analytics)),
            'backend' => array_values(array_unique($backend)),
            'infrastructure' => array_values(array_unique($infrastructure)),
            'server' => array_values(array_filter([$server])),
        ];
    }

    private function containsAny(string $content, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($content, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    private function quickBrokenLinks(array $links): array
    {
        return collect($links)->take(10)->map(function ($link) {
            try {
                $status = Http::timeout(5)->head($link)->status();
                return ['url' => $link, 'status' => $status, 'state' => $status >= 400 ? 'broken' : 'ok'];
            } catch (\Throwable $e) {
                return ['url' => $link, 'status' => null, 'state' => 'unverified'];
            }
        })->values()->all();
    }

    private function absoluteUrl(string $href, string $base): ?string
    {
        if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
            return null;
        }
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $parts = parse_url($base);
        $root = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        return $root.'/'.ltrim($href, '/');
    }
}
