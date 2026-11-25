<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemStatusIncident extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_at'=>'datetime','ended_at'=>'datetime'];
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
