@php
    $expectedHeaders = [
        'strict-transport-security' => [
            'label'  => 'Strict-Transport-Security (HSTS)',
            'what'   => 'Forces browsers to always use secure HTTPS connections.',
            'why'    => 'This error appears when the server is not sending the HSTS header.',
            'impact' => 'Users may be vulnerable to insecure HTTP connections or downgrade attacks.',
        ],
        'x-frame-options' => [
            'label'  => 'X-Frame-Options',
            'what'   => 'Protects the website from being embedded inside iframes.',
            'why'    => 'This error appears when the website does not define the X-Frame-Options header.',
            'impact' => 'The site may be vulnerable to clickjacking attacks.',
        ],
        'x-content-type-options' => [
            'label'  => 'X-Content-Type-Options',
            'what'   => 'Prevents browsers from MIME-type sniffing.',
            'why'    => 'This error appears when the X-Content-Type-Options header is missing.',
            'impact' => 'Browsers may incorrectly interpret files, increasing the risk of malicious script execution.',
        ],
        'x-xss-protection' => [
            'label'  => 'X-XSS-Protection',
            'what'   => 'Enables legacy browser XSS filtering protection.',
            'why'    => 'This error appears when the header is not configured on the server.',
            'impact' => 'Older browsers may have reduced protection against cross-site scripting (XSS) attacks.',
        ],
        'referrer-policy' => [
            'label'  => 'Referrer-Policy',
            'what'   => 'Controls how much referral information is shared when users navigate to another website.',
            'why'    => 'This error appears when the website does not define a Referrer-Policy header.',
            'impact' => 'Sensitive URLs or query parameters may leak to third-party websites.',
        ],
        'permissions-policy' => [
            'label'  => 'Permissions-Policy',
            'what'   => 'Controls access to browser features like camera, microphone, geolocation, fullscreen, and other sensitive APIs.',
            'why'    => 'This error appears when the website is not sending the Permissions-Policy header, so browsers may allow unnecessary feature access.',
            'impact' => 'May increase privacy and security risks by exposing browser APIs.',
        ],
    ];

    $storedHeaders = collect($site->security_headers ?? [])
        ->mapWithKeys(fn ($value, $key) => [strtolower($key) => is_array($value) ? implode('; ', $value) : $value]);

    $categoryConfig = [
        'cors'          => ['title' => 'CORS Configuration',          'description' => 'Checks whether cross-origin access is restricted safely.',                                          'max' => 15],
        'csp'           => ['title' => 'Content Security Policy',     'description' => 'Checks whether Content Security Policy is present and avoids unsafe script rules.',                 'max' => 20],
        'headers'       => ['title' => 'HTTP Security Headers',       'description' => 'Checks whether important browser security headers are present and valid.',                          'max' => 30],
        'malware'       => ['title' => 'Malware & Injection Signals', 'description' => 'Scans page HTML for suspicious injection, obfuscation, and malware patterns.',                     'max' => 20],
        'mixed_content' => ['title' => 'Mixed Content & HTTPS',       'description' => 'Checks whether HTTPS pages avoid loading insecure HTTP resources.',                                 'max' => 15],
    ];
@endphp

{{-- Score summary --}}
<div class="grid gap-4 md:grid-cols-2 mb-6">
    <div class="bg-white border border-slate-200 rounded-lg p-5 flex items-center gap-4">
        <div class="text-4xl font-bold {{ ($site->security_score ?? 0) >= 80 ? 'text-emerald-600' : (($site->security_score ?? 0) >= 50 ? 'text-amber-500' : 'text-red-600') }}">
            {{ $site->security_grade ?: 'N/A' }}
        </div>
        <div>
            <p class="text-sm text-slate-500">Security Grade</p>
            <p class="text-2xl font-semibold">{{ $site->security_score ?? 'N/A' }}%</p>
        </div>
    </div>
    <div class="bg-white border border-slate-200 rounded-lg p-5 text-sm text-slate-600 space-y-1">
        <p class="font-semibold text-slate-800 mb-2">Score Report</p>
        <p>The scanner adds points from five security categories, then converts that total into a percentage.</p>
        @if($site->security_score !== null)
            <p class="font-mono text-xs mt-2">{{ $site->security_score }} earned / 100 possible × 100 = {{ $site->security_score }}%</p>
        @endif
        <div class="flex gap-3 mt-2 text-xs font-semibold">
            <span class="text-emerald-600">A 80-100%</span>
            <span class="text-blue-600">B 65-79%</span>
            <span class="text-amber-500">C 50-64%</span>
            <span class="text-orange-500">D 35-49%</span>
            <span class="text-red-600">F 0-34%</span>
        </div>
    </div>
</div>

@if ($latestSeo?->security_categories)
    @php
        $rawCats = $latestSeo->security_categories;
        // Normalise old key 'mixed' → 'mixed_content' for backward compat
        if (isset($rawCats['mixed']) && !isset($rawCats['mixed_content'])) {
            $rawCats['mixed_content'] = $rawCats['mixed'];
        }
        $securityCategories = collect($rawCats);
    @endphp

    <h3 class="font-semibold text-slate-800 mb-3">Security Score Breakdown</h3>
    <div class="space-y-4 mb-8">
        @foreach ($categoryConfig as $key => $config)
            @php
                $category = $securityCategories->get($key, []);
                $score    = $category['score'] ?? 0;
                $max      = $category['max'] ?? $config['max'];
                $percent  = $max > 0 ? round($score / $max * 100) : 0;
                // Issues come directly from the service now
                $issues   = $category['issues'] ?? [];
            @endphp

            <div class="rounded-lg border border-slate-200 bg-white p-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <p class="font-semibold text-slate-800">{{ $config['title'] }}</p>
                        <p class="text-xs text-slate-500 mt-0.5">{{ $config['description'] }}</p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-2xl font-bold {{ $percent === 100 ? 'text-emerald-600' : ($percent >= 50 ? 'text-amber-500' : 'text-red-600') }}">{{ $percent }}%</p>
                        <p class="text-xs text-slate-500 mt-1">{{ $score }} points earned<br>{{ $max - $score }} points lost<br>{{ $max }} max</p>
                    </div>
                </div>

                @if (count($issues) > 0)
                    <div class="mt-3 rounded-md bg-red-50 border border-red-100 p-3 text-sm text-red-800">
                        <p class="font-semibold mb-1">Why points were lost</p>
                        <ul class="list-disc pl-5 space-y-0.5">
                            @foreach ($issues as $issue)
                                <li>{{ $issue }}</li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <div class="mt-3 rounded-md bg-emerald-50 border border-emerald-100 p-3 text-sm text-emerald-800">
                        No issues found in this category.
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Detailed header breakdown (like Python output) --}}
    @php
        $headersCategory = $securityCategories->get('headers', []);
        $headerItems     = $headersCategory['details'] ?? $headersCategory['items'] ?? [];
        $missingHeaders  = collect($headerItems)->filter(fn ($item) => ! ($item['present'] ?? false))->keys();
    @endphp

    @if ($missingHeaders->isNotEmpty())
        <h3 class="font-semibold text-slate-800 mb-3">HTTP Security Headers</h3>
        <div class="space-y-4 mb-8">
            @foreach ($missingHeaders as $hKey)
                @php $meta = $expectedHeaders[$hKey] ?? null; @endphp
                @if ($meta)
                    <div class="rounded-lg border border-slate-200 bg-white p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <p class="font-semibold text-slate-800">{{ $meta['label'] }}</p>
                                <p class="text-xs font-mono text-slate-400">{{ $hKey }}</p>
                            </div>
                            <span class="px-2 py-1 rounded text-xs font-semibold bg-red-100 text-red-700">Missing</span>
                        </div>
                        <div class="space-y-2 text-sm">
                            <div><span class="font-semibold text-slate-700">What it is:</span> <span class="text-slate-600">{{ $meta['what'] }}</span></div>
                            <div><span class="font-semibold text-slate-700">Why this error appears:</span> <span class="text-slate-600">{{ $meta['why'] }}</span></div>
                            <div><span class="font-semibold text-slate-700">Security impact:</span> <span class="text-slate-600">{{ $meta['impact'] }}</span></div>
                        </div>
                    </div>
                @endif
            @endforeach
            @if ($missingHeaders->contains('x-xss-protection'))
                <p class="text-xs text-slate-500 italic">Note: Modern browsers mostly rely on Content Security Policy (CSP) instead.</p>
            @endif
        </div>
    @endif

@else
    <div class="mt-5 rounded-md border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
        No detailed security data available yet. Run an SEO/security check to populate this section.
    </div>
@endif

{{-- Present headers table --}}
<h3 class="font-semibold text-slate-800 mb-3">Security Response Headers</h3>
<div class="overflow-auto rounded-lg border border-slate-200 bg-white">
    <table class="min-w-full text-sm">
        <thead class="bg-slate-50 border-b">
            <tr>
                <th class="px-4 py-3 text-left font-semibold text-slate-700">Header</th>
                <th class="px-4 py-3 text-left font-semibold text-slate-700">Value</th>
                <th class="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($expectedHeaders as $hKey => $meta)
                @php
                    $value  = $storedHeaders->get($hKey);
                    $present = $value !== null;
                @endphp
                <tr class="border-t hover:bg-slate-50">
                    <td class="px-4 py-3 font-medium">{{ $meta['label'] }}</td>
                    <td class="px-4 py-3 text-slate-600 break-all max-w-md">{{ $present ? $value : '—' }}</td>
                    <td class="px-4 py-3">
                        @if ($present)
                            <span class="px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-700">Present</span>
                        @else
                            <span class="px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-700">Missing</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
