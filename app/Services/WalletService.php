<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only place wallet_balance changes. Callers must already be inside a DB transaction
 * (credit/debit lock the user row so concurrent requests cannot double-spend).
 */
class WalletService
{
    public function credit(User $user, float $amount, string $type, string $description, ?Model $source = null, ?User $from = null, ?int $level = null): WalletTransaction
    {
        return $this->move($user, abs($amount), $type, $description, $source, $from, $level);
    }

    public function debit(User $user, float $amount, string $type, string $description, ?Model $source = null): WalletTransaction
    {
        return $this->move($user, -abs($amount), $type, $description, $source);
    }

    private function move(User $user, float $amount, string $type, string $description, ?Model $source, ?User $from = null, ?int $level = null): WalletTransaction
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Wallet changes must run inside a database transaction.');
        }

        $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
        $newBalance = round((float) $locked->wallet_balance + $amount, 2);

        if ($newBalance < 0) {
            throw ValidationException::withMessages(['amount' => 'Insufficient wallet balance.']);
        }

        $locked->wallet_balance = $newBalance;
        $locked->save();
        $user->wallet_balance = $newBalance;

        return WalletTransaction::create([
            'user_id' => $user->id,
            'type' => $type,
            'amount' => round($amount, 2),
            'balance_after' => $newBalance,
            'from_user_id' => $from?->id,
            'level' => $level,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'description' => $description,
        ]);
    }
}
