<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertHistory extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function site() { return $this->belongsTo(Site::class); }
    public function incident() { return $this->belongsTo(Incident::class); }
}
