<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MechanicDocument extends Model
{
    public const TYPES = [
        'valid_id' => "Owner's Valid ID",
        'business_permit' => 'Business Permit',
        'mayors_permit' => "Mayor's Permit",
        'bir_certificate' => 'BIR Certificate of Registration',
    ];

    protected $fillable = [
        'mechanic_profile_id',
        'document_type',
        'document_path',
        'original_filename',
        'expiration_date',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $hidden = [
        'document_path',
    ];

    protected function casts(): array
    {
        return [
            'expiration_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function mechanicProfile(): BelongsTo
    {
        return $this->belongsTo(MechanicProfile::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by'
        );
    }

    public function getLabelAttribute(): string
    {
        return self::TYPES[$this->document_type]
            ?? $this->document_type;
    }

    public function isExpired(): bool
    {
        return $this->expiration_date !== null
            && $this->expiration_date->lt(today());
    }
}
