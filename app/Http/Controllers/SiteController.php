<?php

namespace App\Http\Controllers;

use App\Jobs\RunFullAuditJob;
use App\Models\Site;
use App\Services\MonitoringService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SiteController extends Controller
{
    public function __construct(private MonitoringService $monitoring) {}

    public function index(Request $request)
    {
        return view('sites.index', ['sites' => $request->user()->sites()->orderBy('app_status')->latest()->paginate(20)]);
    }

    public function create() { return view('sites.create', ['site' => new Site()]); }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $normalized = $this->monitoring->normalizedIdentity($data['url']);

        validator(['normalized_url' => $normalized], [
            'normalized_url' => Rule::unique('sites', 'normalized_url')
                ->where('user_id', $request->user()->id),
        ])->validate();

        $site = $request->user()->sites()->create(array_merge($data, [
            'url' => $this->monitoring->normalizeUrl($data['url']),
            'normalized_url' => $normalized,
            'tracked_keywords' => $this->keywords($request->input('tracked_keywords')),
        ]));

        $this->monitoring->prepareSite($site);
        RunFullAuditJob::dispatch($site->id);

        return redirect()->route('sites.show', $site)->with('success', 'Site monitor created and initial audit queued.');
    }

    public function show(Site $site)
    {
        $site->load([
            'uptimeLogs' => fn ($q) => $q->latest('checked_at')->limit(20),
            'sslLogs' => fn ($q) => $q->latest('checked_at')->limit(20),
            'seoLogs' => fn ($q) => $q->latest('checked_at')->limit(5),
            'dnsLogs' => fn ($q) => $q->latest('checked_at')->limit(20),
            'fullLinkAuditLogs' => fn ($q) => $q->latest()->limit(5),
        ]);

        return view('sites.show', ['site' => $site, 'latestSeo' => $site->seoLogs->first(), 'latestAudit' => $site->fullLinkAuditLogs->first()]);
    }

    public function edit(Site $site) { return view('sites.edit', compact('site')); }

    public function update(Request $request, Site $site)
    {
        $data = $this->validated($request, $site);
        if (isset($data['url'])) {
            $data['url'] = $this->monitoring->normalizeUrl($data['url']);
            $data['normalized_url'] = $this->monitoring->normalizedIdentity($data['url']);
            validator(['normalized_url' => $data['normalized_url']], [
                'normalized_url' => Rule::unique('sites', 'normalized_url')->where('user_id', $request->user()->id)->ignore($site->id),
            ])->validate();
        }

        $site->fill($data);
        $site->tracked_keywords = $this->keywords($request->input('tracked_keywords'));
        $this->monitoring->refreshNextCheckAt($site);
        $site->save();

        return redirect()->route('sites.show', $site)->with('success', 'Site monitor updated.');
    }

    public function destroy(Site $site)
    {
        $site->delete();
        return redirect()->route('sites.index')->with('success', 'Site monitor deleted.');
    }

    public function toggleFleet(Request $request, Site $site)
    {
        $inFleet = filter_var($request->input('in_fleet'), FILTER_VALIDATE_BOOLEAN);
        $site->in_fleet = $inFleet;
        $site->save();

        return response()->json(['in_fleet' => $site->in_fleet]);
    }

    public function statusJson(Site $site)
    {
        return response()->json($site->fresh()->toViewArray());
    }

    public function techStack(Site $site)
    {
        return response()->json($site->seoLogs()->latest('checked_at')->value('tech_stack') ?: []);
    }

    private function validated(Request $request, ?Site $site = null): array
    {
        return $request->validate([
            'url' => [$site ? 'sometimes' : 'required', 'url'],
            'name' => ['nullable', 'string', 'max:255'],
            'uptime_interval' => ['required', 'integer', 'min:60'],
            'ssl_interval' => ['required', 'integer', 'min:3600'],
            'seo_interval' => ['required', 'integer', 'min:3600'],
            'security_interval' => ['required', 'integer', 'min:3600'],
            'dns_interval' => ['required', 'integer', 'min:3600'],
        ]);
    }

    private function keywords(?string $value): array
    {
        return collect(explode(',', (string) $value))->map(fn ($v) => trim($v))->filter()->values()->all();
    }
}
