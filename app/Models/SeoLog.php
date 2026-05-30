<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'fetch_is_valid' => 'boolean',
            'seo_signals' => 'array',
            'issues' => 'array',
            'recommendations' => 'array',
            'cwv_estimate' => 'array',
            'lighthouse' => 'array',
            'tech_stack' => 'array',
            'broken_links' => 'array',
            'security_categories' => 'array',
            'security_headers' => 'array',
        ];
    }

    public function site() { return $this->belongsTo(Site::class); }
}
