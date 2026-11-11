<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = ['user_id', 'paid_by', 'amount', 'method', 'proof_path', 'reference', 'note'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by')->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function methodLabel(): string
    {
        return match ($this->method) {
            'proof' => 'Payment proof',
            'wallet' => 'Wallet',
            'admin' => 'Received by admin',
        };
    }
}
