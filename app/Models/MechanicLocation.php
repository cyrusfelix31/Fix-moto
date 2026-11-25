<?php

namespace App\Models;

use Database\Factories\MechanicLocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MechanicLocation extends Model
{
    /** @use HasFactory<MechanicLocationFactory> */
    use HasFactory;

    protected $guarded = [];
}
