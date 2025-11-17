<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ClientKycDocument extends Model
{
    use HasUuids;

    protected $fillable = [
        'client_id',
        'document_type',
        'issue_date',
        'expiry_date',
        'issuing_authority',
        'file_path',
        'status',
        'notes',
        'uploaded_by_user_id',
        'reviewed_by_user_id',
        'review_date'
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'review_date' => 'datetime'
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}