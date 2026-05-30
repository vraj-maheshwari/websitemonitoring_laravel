<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DnsLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'resolved' => 'boolean',
            'ips' => 'array',
            'nameservers' => 'array',
            'mx_records' => 'array',
            'txt_records' => 'array',
            'cname_records' => 'array',
            'soa_records' => 'array',
            'caa_records' => 'array',
            'dnssec_enabled' => 'boolean',
            'hijack_suspected' => 'boolean',
            'ns_changed' => 'boolean',
            'nameserver_health' => 'array',
            'change_details' => 'array',
        ];
    }

    public function site() { return $this->belongsTo(Site::class); }
}
