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

    /**
     * @param  array{method: string, bank_name?: ?string, account_title: string, account_number: string, account_details?: ?string}  $payout
     */
    public function request(User $user, float $amount, array $payout): Withdrawal
    {
        $min = Setting::money('min_withdrawal');
        if ($amount < $min) {
            throw ValidationException::withMessages(['amount' => 'Minimum withdrawal is $'.number_format($min, 2).'.']);
        }

        return DB::transaction(function () use ($user, $amount, $payout) {
            $withdrawal = Withdrawal::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'method' => $payout['method'],
                'bank_name' => $payout['bank_name'] ?? null,
                'account_title' => $payout['account_title'],
                'account_number' => $payout['account_number'],
                'account_details' => $payout['account_details'] ?? null,
            ]);

            $this->wallet->debit($user, $amount, 'withdrawal', "Withdrawal request #{$withdrawal->id} ({$payout['method']})", $withdrawal);

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
