<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MechanicProfile extends Model
{
    use HasFactory;

    public const ACTIVE_SERVICE_STATUSES = [
        'accepted', 'on_the_way', 'arrived', 'diagnosing', 'repairing',
    ];

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
        'logo_path',
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

    public function availableMembers(): HasMany
    {
        return $this->members()
            ->where('is_active', true)
            ->whereDoesntHave('serviceRequests', fn ($query) => $query->whereIn('service_status', self::ACTIVE_SERVICE_STATUSES));
    }

    public function availableMechanicCount(): int
    {
        $freeMembers = $this->availableMembers()->count();
        $waitingForAssignment = \App\Models\ServiceRequest::query()
            ->where('mechanic_id', $this->user_id)
            ->whereNull('mechanic_shop_member_id')
            ->whereIn('service_status', self::ACTIVE_SERVICE_STATUSES)
            ->count();

        return max(0, $freeMembers - $waitingForAssignment);
    }

    public function hasAvailableMechanic(): bool
    {
        return $this->availableMechanicCount() > 0;
    }

    public function shopServices(): HasMany
    {
        return $this->hasMany(MechanicShopService::class);
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

            if (! $document || $document->status !== 'approved') {
                return false;
            }

            if (
                in_array($type, [
                    'business_permit',
                    'mayors_permit',
                ], true)
                && ! $document->expiration_date
            ) {
                return false;
            }

            if ($document->isExpired()) {
                return false;
            }
        }

        return true;
    }

    public function verificationProgress(): array
    {
        $documents = $this->documents()->get()->keyBy('document_type');
        $total = count(MechanicDocument::TYPES);
        $approved = 0;

        foreach (MechanicDocument::TYPES as $type => $label) {
            $document = $documents->get($type);
            if ($document?->status === 'approved' && !$document->isExpired()) {
                $approved++;
            }
        }

        return [
            'approved' => $approved,
            'total' => $total,
            'percentage' => $total ? (int) round($approved / $total * 100) : 0,
        ];
    }

    public function refreshVerificationStatus(): string
    {
        $documents = $this->documents()->get()->keyBy('document_type');
        $approved = 0;
        $complete = true;

        foreach (MechanicDocument::TYPES as $type => $label) {
            $document = $documents->get($type);
            if ($document?->status === 'approved' && !$document->isExpired()) {
                $approved++;
            } else {
                $complete = false;
            }
        }

        $hasRejected = $documents->contains(
            fn (MechanicDocument $document): bool => $document->status === 'rejected'
        );

        $status = $complete
            ? 'fully_verified'
            : (($approved > 0 || $hasRejected) ? 'partially_verified' : 'under_review');

        $this->update([
            'verification_status' => $status,
            'is_verified' => $status === 'fully_verified',
            'is_online' => $status === 'fully_verified' ? $this->is_online : false,
        ]);

        return $status;
    }

    public function distanceTo(
        float $latitude,
        float $longitude
    ): float {
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
