<?php

namespace App\Models;

use Database\Factories\MechanicProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MechanicProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shop_name',
        'shop_phone',
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
        'verification_status',
    ];

    protected function casts(): array
    {
        return [
            'services' => 'array',
            'is_verified' => 'boolean',
            'is_online' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(MechanicShopMember::class);
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->where('is_active', true);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MechanicDocument::class);
    }

    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function allDocumentsApproved(): bool
    {
        $documents = $this->documents()
            ->get()
            ->keyBy('document_type');

        foreach (MechanicDocument::TYPES as $type => $label) {

            $document = $documents->get($type);

            if (!$document || $document->status !== 'approved') {
                return false;
            }

            if (
                in_array($type, [
                    'business_permit',
                    'mayors_permit'
                ], true)
                && !$document->expiration_date
            ) {
                return false;
            }

            if ($document->isExpired()) {
                return false;
            }
        }

        return true;
    }

    public function distanceTo(
        float $latitude,
        float $longitude
    ): float
    {
        $earthRadius = 6371;

        $latitudeDifference = deg2rad(
            $latitude - (float) $this->latitude
        );

        $longitudeDifference = deg2rad(
            $longitude - (float) $this->longitude
        );

        $distance = sin($latitudeDifference / 2) ** 2

            + cos(deg2rad((float) $this->latitude))

            * cos(deg2rad($latitude))

            * sin($longitudeDifference / 2) ** 2;

        return $earthRadius * 2 * atan2(
            sqrt($distance),
            sqrt(max(0, 1 - $distance))
        );
    }
}
