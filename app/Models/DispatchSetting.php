<?php

namespace App\Models;

use Database\Factories\DispatchSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DispatchSetting extends Model
{
    /** @use HasFactory<DispatchSettingFactory> */
    use HasFactory;

    protected $guarded = [];
}
