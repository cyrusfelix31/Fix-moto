<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'brand',
        'model',
        'year',
        'plate_number',
        'vehicle_type',
        'vehicle_category',
        'notes',
        'is_active',
    ];

    public function rider()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
