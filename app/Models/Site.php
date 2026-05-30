<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $url
 * @property string|null $name
 * @property string $normalized_url
 * @property array $tracked_keywords
 * @property array|null $security_headers
 * @property array|null $dns_last_ips
 * @property array|null $dns_last_ns
 * @property bool $dns_resolved
 * @property bool $dns_hijack_suspected
 * @property bool $dns_ns_changed
 * @property bool $is_processing
 * @property bool $in_fleet
 * @property string|null $current_status
 * @property string|null $app_status
 * @property string|null $uptime_status
 * @property string|null $ssl_status
 * @property string|null $seo_status
 * @property string|null $security_status
 * @property string|null $dns_status
 * @property string|null $ssl_state
 * @property int|null $uptime_interval
 * @property int|null $ssl_interval
 * @property int|null $seo_interval
 * @property int|null $security_interval
 * @property int|null $dns_interval
 * @property int|null $last_response_time
 * @property int|null $ssl_days_remaining
 * @property float|null $seo_score
 * @property Carbon|null $next_uptime_check_at
 * @property Carbon|null $next_ssl_check_at
 * @property Carbon|null $next_seo_check_at
 * @property Carbon|null $next_security_check_at
 * @property Carbon|null $next_dns_check_at
 * @property Carbon|null $next_check_at
 * @property Carbon|null $last_uptime_check_at
 * @property Carbon|null $last_ssl_check_at
 * @property Carbon|null $last_seo_check_at
 * @property Carbon|null $last_security_check_at
 * @property Carbon|null $last_dns_check_at
 * @property Carbon|null $uptime_started_at
 * @property Carbon|null $ssl_started_at
 * @property Carbon|null $seo_started_at
 * @property Carbon|null $security_started_at
 * @property Carbon|null $dns_started_at
 * @property Carbon|null $last_downtime_started_at
 * @property Carbon|null $last_downtime_ended_at
 * @property Carbon|null $ssl_expiry_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Site extends Model
{

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tracked_keywords' => 'array',
            'security_headers' => 'array',
            'dns_last_ips' => 'array',
            'dns_last_ns' => 'array',
            'dns_resolved' => 'boolean',
            'dns_hijack_suspected' => 'boolean',
            'dns_ns_changed' => 'boolean',
            'is_processing' => 'boolean',
            'in_fleet' => 'boolean',
            'next_uptime_check_at' => 'datetime',
            'next_ssl_check_at' => 'datetime',
            'next_seo_check_at' => 'datetime',
            'next_security_check_at' => 'datetime',
            'next_dns_check_at' => 'datetime',
            'next_check_at' => 'datetime',
            'last_uptime_check_at' => 'datetime',
            'last_ssl_check_at' => 'datetime',
            'last_seo_check_at' => 'datetime',
            'last_security_check_at' => 'datetime',
            'last_dns_check_at' => 'datetime',
            'uptime_started_at' => 'datetime',
            'ssl_started_at' => 'datetime',
            'seo_started_at' => 'datetime',
            'security_started_at' => 'datetime',
            'dns_started_at' => 'datetime',
            'last_downtime_started_at' => 'datetime',
            'last_downtime_ended_at' => 'datetime',
            'ssl_expiry_date' => 'date',
        ];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function uptimeLogs() { return $this->hasMany(UptimeLog::class); }
    public function sslLogs() { return $this->hasMany(SslLog::class); }
    public function seoLogs() { return $this->hasMany(SeoLog::class); }
    public function dnsLogs() { return $this->hasMany(DnsLog::class); }
    public function incidents() { return $this->hasMany(Incident::class); }
    public function alertHistories() { return $this->hasMany(AlertHistory::class); }
    public function fullLinkAuditLogs() { return $this->hasMany(FullLinkAuditLog::class); }

    public function scopeDueForCheck(Builder $query, string $checkType, ?Carbon $now = null): Builder
    {
        return $query->where("next_{$checkType}_check_at", '<=', $now ?: now())
            ->whereNot("{$checkType}_status", 'running');
    }

    public function toViewArray(): array
    {
        return array_merge($this->toArray(), [
            'display_name' => $this->name ?: parse_url($this->url, PHP_URL_HOST) ?: $this->url,
            'last_checked_at' => collect([
                $this->last_uptime_check_at,
                $this->last_ssl_check_at,
                $this->last_seo_check_at,
                $this->last_security_check_at,
                $this->last_dns_check_at,
            ])->filter()->max(),
        ]);
    }

    public function refreshAppStatus(): void
    {
        $statuses = collect(['uptime', 'ssl', 'seo', 'security', 'dns'])->map(fn ($type) => $this->getAttribute("{$type}_status"));
        $this->app_status = $statuses->contains('error') ? 'error'
            : ($statuses->contains('warning') ? 'warning'
            : ($statuses->contains('running') || $statuses->contains('queued') ? 'processing'
            : ($statuses->contains('ok') ? 'ok' : 'unknown')));
        $this->is_processing = $this->app_status === 'processing';
        $this->save();
    }

    public function rescueStuckTasks(): void
    {
        foreach (['uptime', 'ssl', 'seo', 'security', 'dns'] as $type) {
            $started = $this->getAttribute("{$type}_started_at");
            if ($this->getAttribute("{$type}_status") === 'running' && $started && $started->lt(now()->subMinutes(10))) {
                $this->setAttribute("{$type}_status", 'error');
                $this->setAttribute("{$type}_started_at", null);
            }
        }
        $this->save();
        $this->refreshAppStatus();
    }

    public function getSecurityHeadersAttribute($value): array
    {
        if ($value) {
            return json_decode($value, true) ?: [];
        }

        return $this->seoLogs()->latest('checked_at')->value('security_headers') ?: [];
    }
}
