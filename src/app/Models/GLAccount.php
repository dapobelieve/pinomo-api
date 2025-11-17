<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GLAccount extends Model
{
    use HasFactory, SoftDeletes;

    // Account types
    public const TYPE_ASSET = 'asset';
    public const TYPE_LIABILITY = 'liability';
    public const TYPE_EQUITY = 'equity';
    public const TYPE_INCOME = 'income';
    public const TYPE_EXPENSE = 'expense';

    protected $table = 'gl_accounts';

    protected $fillable = [
        'account_code',
        'account_name',
        'account_type',
        'currency',
        'parent_account_id',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'current_balance' => 'decimal:4',
    ];

    // Parent account relationship
    public function parent()
    {
        return $this->belongsTo(GLAccount::class, 'parent_account_id');
    }

    // Child accounts relationship
    public function children()
    {
        return $this->hasMany(GLAccount::class, 'parent_account_id');
    }

    // Scope for active accounts
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope for specific account types
    public function scopeOfType($query, $type)
    {
        return $query->where('account_type', $type);
    }

    // Check if account has children
    public function hasChildren()
    {
        return $this->children()->exists();
    }

    // Get all ancestors
    public function ancestors()
    {
        return $this->parent ? $this->parent->ancestors()->push($this->parent) : collect();
    }

    // Get all descendants
    public function descendants()
    {
        return $this->children()->with('descendants')->get();
    }
}