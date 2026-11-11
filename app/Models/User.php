<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'country',
        'sponsor_id',
        'package',
        'package_amount',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'package_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'is_admin' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->referral_code ??= static::generateReferralCode();
        });
    }

    public static function generateReferralCode(): string
    {
        do {
            $code = 'GVE'.strtoupper(Str::random(6));
        } while (static::where('referral_code', $code)->exists());

        return $code;
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sponsor_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'sponsor_id');
    }

    public function referralLink(): string
    {
        return route('register', ['ref' => $this->referral_code]);
    }

    /** Uplines become eligible for commission only once this member has paid the qualifying amount. */
    public function qualifiesForCommission(): bool
    {
        return (float) $this->paid_amount >= (float) config('education.commission_qualifying_amount');
    }
}
