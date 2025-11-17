<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'client_type',
        'owner_client_id',
        'organization_business_ids',
        'external_id',
        // Business fields
        'business_name',
        'business_registration_number',
        'business_registration_date',
        'business_type',
        'tax_identification_number',
        'industry_sector',
        // Representative fields
        'representative_first_name',
        'representative_last_name',
        'representative_position',
        // Individual fields
        'first_name',
        'last_name',
        'date_of_birth',
        'gender',
        'marital_status',
        'nationality',
        'email',
        'phone_number',
        'address',
        'kyc_status'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'business_registration_date' => 'date',
        'address' => 'json',
        'organization_business_ids' => 'json'
    ];

    // Boot method to ensure business rules
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($client) {
            if ($client->client_type === 'organization' && !$client->owner_client_id) {
                throw new \InvalidArgumentException('Organization clients must have an owner_client_id');
            }

            if ($client->client_type === 'individual' && $client->owner_client_id) {
                throw new \InvalidArgumentException('Individual clients cannot have an owner_client_id');
            }
        });

        static::updating(function ($client) {
            if ($client->client_type === 'organization' && !$client->owner_client_id) {
                throw new \InvalidArgumentException('Organization clients must have an owner_client_id');
            }

            if ($client->client_type === 'individual' && $client->owner_client_id) {
                throw new \InvalidArgumentException('Individual clients cannot have an owner_client_id');
            }
        });
    }

    // Helper methods for business clients
    public function isBusinessClient(): bool
    {
        return $this->client_type === 'organization';
    }

    public function isIndividualClient(): bool
    {
        return $this->client_type === 'individual';
    }

    public function getDisplayName(): string
    {
        return $this->isBusinessClient()
            ? $this->business_name
            : $this->first_name . ' ' . $this->last_name;
    }

    // Check if individual client owns any businesses
    public function hasBusinesses(): bool
    {
        return $this->isIndividualClient() && $this->ownedBusinesses()->exists();
    }

    // Get count of owned businesses
    public function getBusinessCount(): int
    {
        return $this->isIndividualClient() ? $this->ownedBusinesses()->count() : 0;
    }

    // Add a business relationship (for individual clients)
    public function addBusinessRelationship(string $businessClientId): void
    {
        if (!$this->isIndividualClient()) {
            throw new \InvalidArgumentException('Only individual clients can own businesses');
        }

        $businessIds = $this->organization_business_ids ?? [];

        if (!in_array($businessClientId, $businessIds)) {
            $businessIds[] = $businessClientId;
            $this->organization_business_ids = $businessIds;
            $this->save();
        }
    }

    // Remove a business relationship (for individual clients)
    public function removeBusinessRelationship(string $businessClientId): void
    {
        if (!$this->isIndividualClient()) {
            throw new \InvalidArgumentException('Only individual clients can own businesses');
        }

        $businessIds = $this->organization_business_ids ?? [];
        $businessIds = array_values(array_filter($businessIds, fn($id) => $id !== $businessClientId));

        $this->organization_business_ids = $businessIds;
        $this->save();
    }

    // Relationships

    // For individual clients: businesses they own
    public function ownedBusinesses()
    {
        return $this->hasMany(Client::class, 'owner_client_id')
            ->where('client_type', 'organization');
    }

    // For organization clients: the individual client who owns them
    public function owner()
    {
        return $this->belongsTo(Client::class, 'owner_client_id')
            ->where('client_type', 'individual');
    }

    // Existing relationships
    public function kycDocuments()
    {
        return $this->hasMany(ClientKycDocument::class);
    }

    public function kycStatusHistory()
    {
        return $this->hasMany(ClientKycStatusHistory::class);
    }

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'external_id', 'id');
    }

    // Scopes
    public function scopeIndividuals($query)
    {
        return $query->where('client_type', 'individual');
    }

    public function scopeOrganizations($query)
    {
        return $query->where('client_type', 'organization');
    }

    public function scopeOwnedBy($query, $ownerClientId)
    {
        return $query->where('owner_client_id', $ownerClientId);
    }

    // Validation methods
    public function validateBusinessOwnership(): bool
    {
        if ($this->isBusinessClient()) {
            return $this->owner && $this->owner->isIndividualClient();
        }
        return true;
    }

    public function canBeDeleted(): bool
    {
        // Individual clients cannot be deleted if they own businesses
        if ($this->isIndividualClient() && $this->hasBusinesses()) {
            return false;
        }
        return true;
    }
}