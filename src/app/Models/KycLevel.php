<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KycLevel extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'level_name',
        'description',
        'requirements'
    ];

    protected $casts = [
        'requirements' => 'json'
    ];
}