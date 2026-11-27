<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'phone',
    'profile_picture',
])]

#[Hidden([
    'password',
    'remember_token',
    'profile_picture',
])]

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_suspended' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function mechanicProfile(): HasOne
    {
        return $this->hasOne(MechanicProfile::class);
    }

    public function riderRequests(): HasMany
    {
        return $this->hasMany(
            ServiceRequest::class,
            'rider_id'
        );
    }

    public function assignedRequests(): HasMany
    {
        return $this->hasMany(
            ServiceRequest::class,
            'mechanic_id'
        );
    }

    public function mechanicVerifications(): HasMany
    {
        return $this->hasMany(
            MechanicVerification::class,
            'mechanic_id'
        );
    }

    public function scopePendingVerification(
        Builder $query
    ): void
    {
        $query->where('role', 'mechanic')
            ->whereHas(
                'mechanicProfile',
                function (Builder $profile) {
                    $profile
                        ->where('is_verified', false)
                        ->where(
                            'verification_status',
                            'pending'
                        );
                }
            );
    }
}