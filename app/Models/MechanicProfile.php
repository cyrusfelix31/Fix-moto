<?php

namespace App\Models;

use Database\Factories\MechanicProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MechanicProfile extends Model
{
    /** @use HasFactory<MechanicProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shop_name',
        'service_address',
        'barangay',
        'barangay_id',
        'barangay_match_status',
        'services',
        'coverage_radius_km',
        'latitude',
        'longitude',
        'is_verified',
        'is_online',
    ];

    protected function casts(): array
    {
        return ['services' => 'array', 'is_verified' => 'boolean', 'is_online' => 'boolean', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    }

    public function members(): HasMany
    {
        return $this->hasMany(MechanicShopMember::class);
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->where('is_active', true);
    }

    public function distanceTo(float $latitude, float $longitude): float
    {
        $earthRadius = 6371;
        $latitudeDifference = deg2rad($latitude - (float) $this->latitude);
        $longitudeDifference = deg2rad($longitude - (float) $this->longitude);
        $distance = sin($latitudeDifference / 2) ** 2
            + cos(deg2rad((float) $this->latitude)) * cos(deg2rad($latitude))
            * sin($longitudeDifference / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($distance), sqrt(1 - $distance));
    }

    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }
}
