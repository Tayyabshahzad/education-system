<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WalletTransaction extends Model
{
    protected $fillable = ['user_id', 'type', 'amount', 'balance_after', 'from_user_id', 'level', 'source_type', 'source_id', 'description'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
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

    public function typeLabel(): string
    {
        return match ($this->type) {
            'commission' => 'Commission',
            'withdrawal' => 'Withdrawal',
            'withdrawal_refund' => 'Withdrawal refund',
            'payment' => 'Fee payment',
            'adjustment' => 'Adjustment',
        };
    }
}
