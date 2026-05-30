<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FullLinkAuditLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime', 'results' => 'array'];
    }

    public function site() { return $this->belongsTo(Site::class); }
}
