<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'resolved_at' => 'datetime', 'timeline' => 'array'];
    }

    public function site() { return $this->belongsTo(Site::class); }
}
