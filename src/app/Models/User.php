<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\HasApiTokens;
use App\Jobs\SendEmailJob;
use App\Mail\EmailVerificationMail;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'two_factor_confirmed_at' => 'datetime',
    ];

    public function hasTwoFactorEnabled(): bool
    {
        return ! is_null($this->two_factor_confirmed_at);
    }

    public function hasValidTwoFactorCode(): bool
    {
        return ! is_null($this->two_factor_secret) && ! is_null($this->two_factor_confirmed_at);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function client(): HasOne
    {
        return $this->hasOne(Client::class, 'external_id', 'id');
    }

    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    public function hasAnyRole(array $roleNames): bool
    {
        return $this->roles()->whereIn('name', $roleNames)->exists();
    }

    public function hasAllRoles(array $roleNames): bool
    {
        return $this->roles()->whereIn('name', $roleNames)->count() === count($roleNames);
    }

    public function sendEmailVerificationNotification()
    {
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $this->getKey(), 'hash' => sha1($this->getEmailForVerification())]
        );
    
        SendEmailJob::dispatch(
            $this->email,
            new EmailVerificationMail($verificationUrl)
        );
    }
}
