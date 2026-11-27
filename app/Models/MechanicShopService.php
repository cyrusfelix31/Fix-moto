<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MechanicShopService extends Model
{
    public const DEFAULT_SERVICES = [
        'Flat Tire Repair', 'Tire Replacement', 'Battery Replacement',
        'Battery Jump-start', 'Brake Repair', 'Engine Repair',
        'Electrical Repair', 'Chain Repair and Adjustment', 'Change Oil',
        'Spark Plug Replacement', 'Preventive Maintenance',
        'General Motorcycle Repair',
    ];

    protected $fillable = ['name', 'is_enabled'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    public function mechanicProfile(): BelongsTo
    {
        return $this->belongsTo(MechanicProfile::class);
    }
}
