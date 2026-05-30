<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UptimeLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime', 'is_up' => 'boolean'];
    }

    public function site() { return $this->belongsTo(Site::class); }
}
