<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KycStorageConfig extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'driver',
        'settings',
        'is_active'
    ];

    protected $casts = [
        'settings' => 'json',
        'is_active' => 'boolean'
    ];
}