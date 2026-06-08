<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\JsonResponse;

class DnsController extends Controller
{
    public function show(Site $site): JsonResponse
    {
        $logs = $site->dnsLogs()
            ->latest('checked_at')
            ->limit(50)
            ->get()
            ->map(function ($log) {

                return [

                    'checked_at' => $log->checked_at,

                    'resolved' => $log->resolved,

                    'ips' => $log->ips ?? [],

                    'nameservers' => $log->nameservers ?? [],

                    'mx_records' => $log->mx_records ?? [],

                    'txt_records' => $log->txt_records ?? [],

                    'cname_records' => $log->cname_records ?? [],

                    'caa_records' => $log->caa_records ?? [],

                    'soa_records' => $log->soa_records ?? [],

                    'dnssec_enabled' => $log->dnssec_enabled,

                    'ttl_min' => $log->ttl_min,

                    'ttl_max' => $log->ttl_max,

                    'ttl_average' => $log->ttl_average,

                    'response_time_ms' => $log->response_time_ms,

                    'hijack_suspected' => $log->hijack_suspected,

                    'hijack_risk' => $log->hijack_risk,

                    'ns_changed' => $log->ns_changed,

                    'dns_score' => $log->dns_score,

                    'dns_grade' => $log->dns_grade,

                    'nameserver_health' => $log->nameserver_health ?? [],

                    'change_details' => $log->change_details ?? [],

                    'error_message' => $log->error_message

                ];

            });

        return response()->json([

            'site' => [

                'resolved' => $site->dns_resolved,

                'ips' => $site->dns_last_ips ?? [],

                'nameservers' => $site->dns_last_ns ?? [],

                'dns_score' => $site->dns_score,

                'dns_grade' => $site->dns_grade,

                'response_time_ms' => $site->dns_response_time_ms,

                'dnssec_enabled' => $site->dnssec_enabled,

                'hijack_risk' => $site->dns_hijack_risk,

                'hijack_suspected' => $site->dns_hijack_suspected,

                'ns_changed' => $site->dns_ns_changed,

                'error_details' => $site->dns_error_details

            ],

            'logs' => $logs

        ]);
    }
}