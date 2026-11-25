<?php

namespace App\Models;

use Database\Factories\ServiceStatusHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceStatusHistory extends Model
{
    /** @use HasFactory<ServiceStatusHistoryFactory> */
    use HasFactory;

    protected $guarded = [];
}
