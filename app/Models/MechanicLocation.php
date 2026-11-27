<?php

namespace App\Models;

use Database\Factories\MechanicLocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MechanicLocation extends Model
{
    /** @use HasFactory<MechanicLocationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy_meters' => 'decimal:2',
        ];
    }

    protected $guarded = [];
}
