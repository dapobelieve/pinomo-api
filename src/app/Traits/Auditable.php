<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    protected static function bootAuditable()
    {
        static::created(function ($model) {
            self::logAuditTrail('create', $model);
        });

        static::updated(function ($model) {
            self::logAuditTrail('update', $model);
        });

        static::deleted(function ($model) {
            self::logAuditTrail('delete', $model);
        });
    }

    protected static function logAuditTrail(string $action, $model)
    {
        $request = request();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action_type' => $action,
            'resource_type' => class_basename($model),
            'resource_id' => $model->getKey(),
            'old_values' => $action === 'update' ? $model->getOriginal() : null,
            'new_values' => $action === 'delete' ? null : $model->getAttributes(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}