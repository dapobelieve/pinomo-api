<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'webhook_id',
        'url',
        'payload',
        'status',
        'attempt',
        'response_status',
        'response_body',
        'error_message',
        'scheduled_at',
        'delivered_at',
        'failed_at'
    ];

    protected $casts = [
        'payload' => 'array',
        'scheduled_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime'
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_FAILED = 'failed';
    const STATUS_PERMANENTLY_FAILED = 'permanently_failed';

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', self::STATUS_DELIVERED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopePermanentlyFailed($query)
    {
        return $query->where('status', self::STATUS_PERMANENTLY_FAILED);
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    public function isFailed(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_PERMANENTLY_FAILED]);
    }

    public function isPermanentlyFailed(): bool
    {
        return $this->status === self::STATUS_PERMANENTLY_FAILED;
    }
}