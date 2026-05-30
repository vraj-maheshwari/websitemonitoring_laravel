<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SslLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'expiry_date' => 'date',
            'is_valid' => 'boolean',
            'ssl_valid_from' => 'date',
            'ssl_valid_until' => 'date',
            'ssl_is_trusted' => 'boolean',
            'ssl_hostname_valid' => 'boolean',
            'ssl_subject_alt_names' => 'array',
        ];
    }

    public function site() { return $this->belongsTo(Site::class); }
}
