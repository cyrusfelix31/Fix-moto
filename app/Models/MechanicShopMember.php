<?php

namespace App\Models;

use Database\Factories\MechanicShopMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MechanicShopMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_active',
        'profile_picture',
        'avatar_path',
        'phone',
        'specialization',
        'years_of_experience',
        'years_experience',
    ];

    protected $hidden = [
        'profile_picture',
        'avatar_path',
        'phone',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'years_of_experience' => 'integer',
            'years_experience' => 'integer',
        ];
    }

    public function mechanicProfile(): BelongsTo
    {
        return $this->belongsTo(
            MechanicProfile::class
        );
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(
            ServiceRequest::class,
            'mechanic_shop_member_id'
        );
    }
}
