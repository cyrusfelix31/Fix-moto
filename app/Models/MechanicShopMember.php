<?php

namespace App\Models;

use Database\Factories\MechanicShopMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MechanicShopMember extends Model
{
    /** @use HasFactory<MechanicShopMemberFactory> */
    use HasFactory;

    protected $fillable = ['name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function mechanicProfile(): BelongsTo
    {
        return $this->belongsTo(MechanicProfile::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'mechanic_shop_member_id');
    }
}
