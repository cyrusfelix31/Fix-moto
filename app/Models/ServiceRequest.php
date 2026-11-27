<?php

namespace App\Models;

use Database\Factories\ServiceRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequest extends Model
{
    /** @use HasFactory<ServiceRequestFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'services' => 'array',
        ];
    }

    public function serviceStatusLabel(): string
    {
        return [
            'pending' => 'Waiting for a mechanic',
            'accepted' => 'Request accepted',
            'on_the_way' => 'Mechanic on the way',
            'arrived' => 'Mechanic arrived',
            'diagnosing' => 'Mechanic arrived',
            'repairing' => 'Repair in progress',
            'completed' => 'Service completed',
            'cancelled' => 'Cancelled',
        ][$this->service_status] ?? str_replace('_', ' ', ucfirst($this->service_status));
    }

    public function rider()
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function mechanic()
    {
        return $this->belongsTo(User::class, 'mechanic_id');
    }

    public function assignedMechanicMember(): BelongsTo
    {
        return $this->belongsTo(MechanicShopMember::class, 'mechanic_shop_member_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function dispatchAttempts()
    {
        return $this->hasMany(DispatchAttempt::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(ServiceStatusHistory::class);
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
