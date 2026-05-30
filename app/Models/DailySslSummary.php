<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailySslSummary extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['date' => 'date']; }
    public function site() { return $this->belongsTo(Site::class); }
}
