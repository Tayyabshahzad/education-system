<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Requested amounts leave the wallet immediately (held), so the same money cannot be
 * requested twice. Rejection refunds it; approval just records that it was paid out.
 */
class WithdrawalService
{
    public function __construct(private WalletService $wallet) {}

    public function request(User $user, float $amount, string $method, string $accountDetails): Withdrawal
    {
        $min = Setting::money('min_withdrawal');
        if ($amount < $min) {
            throw ValidationException::withMessages(['amount' => 'Minimum withdrawal is $'.number_format($min, 2).'.']);
        }

        return DB::transaction(function () use ($user, $amount, $method, $accountDetails) {
            $withdrawal = Withdrawal::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'method' => $method,
                'account_details' => $accountDetails,
            ]);

            $this->wallet->debit($user, $amount, 'withdrawal', "Withdrawal request #{$withdrawal->id} ({$method})", $withdrawal);

            return $withdrawal;
        });
    }

    public function approve(Withdrawal $withdrawal, User $reviewer, ?string $note = null): void
    {
        DB::transaction(function () use ($withdrawal, $reviewer, $note) {
            $withdrawal = $this->lockPending($withdrawal);
            $this->close($withdrawal, 'approved', $reviewer, $note);
        });
    }

    public function reject(Withdrawal $withdrawal, User $reviewer, ?string $note = null): void
    {
        DB::transaction(function () use ($withdrawal, $reviewer, $note) {
            $withdrawal = $this->lockPending($withdrawal);
            $this->close($withdrawal, 'rejected', $reviewer, $note);

            $this->wallet->credit($withdrawal->user, (float) $withdrawal->amount, 'withdrawal_refund', "Refund for rejected withdrawal #{$withdrawal->id}", $withdrawal);
        });
    }

    private function lockPending(Withdrawal $withdrawal): Withdrawal
    {
        $locked = Withdrawal::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
        if ($locked->status !== 'pending') {
            throw ValidationException::withMessages(['withdrawal' => 'This request has already been '.$locked->status.'.']);
        }

        return $locked;
    }

    private function close(Withdrawal $withdrawal, string $status, User $reviewer, ?string $note): void
    {
        $withdrawal->status = $status;
        $withdrawal->note = $note;
        $withdrawal->reviewed_by = $reviewer->id;
        $withdrawal->reviewed_at = now();
        $withdrawal->save();
    }
}
