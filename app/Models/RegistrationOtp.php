<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationOtp extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'email',
        'purpose',
        'otp_hash',
        'payload',
        'attempts',
        'expires_at',
        'resend_available_at',
        'verified_at',
    ];

    protected $hidden = ['otp_hash', 'payload'];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'resend_available_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }
}
