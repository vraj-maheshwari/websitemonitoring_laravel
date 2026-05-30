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
            'hijack_suspected' => 'boolean',
            'ns_changed' => 'boolean',
        ];
    }

    public function site() { return $this->belongsTo(Site::class); }
}
