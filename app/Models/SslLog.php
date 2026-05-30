<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SslLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime', 'expiry_date' => 'date', 'is_valid' => 'boolean'];
    }

    public function site() { return $this->belongsTo(Site::class); }
}
