<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ClientKycStatusHistory extends Model
{
    use HasUuids;

    protected $table = 'client_kyc_status_history';

    protected $fillable = [
        'client_id',
        'old_status',
        'new_status',
        'action_by_user_id',
        'notes',
        'changed_at'
    ];

    protected $casts = [
        'changed_at' => 'datetime'
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function actionBy()
    {
        return $this->belongsTo(User::class, 'action_by_user_id');
    }
}
