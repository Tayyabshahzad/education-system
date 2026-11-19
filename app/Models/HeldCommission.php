<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Commission waiting for the upline to unlock its level under the 7x7 rule (see CommissionService). */
class HeldCommission extends Model
{
    protected $fillable = ['user_id', 'from_user_id', 'level', 'percent', 'base', 'amount', 'source_type', 'source_id'];

    protected function casts(): array
    {
        return [
            'percent' => 'decimal:2',
            'base' => 'decimal:2',
            'amount' => 'decimal:2',
            'released_at' => 'datetime',
        ];
    }

    public function scopeWaiting(Builder $query): Builder
    {
        return $query->whereNull('released_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id')->withTrashed();
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
