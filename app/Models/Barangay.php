<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Barangay extends Model
{
    protected $fillable = [
        'psgc_code',
        'name',
        'latitude',
        'longitude',
        'boundary_geojson',
        'geojson_reference',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'boundary_geojson' => 'array',
        ];
    }

    public function mechanicProfiles(): HasMany
    {
        return $this->hasMany(MechanicProfile::class);
    }
}
