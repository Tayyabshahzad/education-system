<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private WalletService $wallet) {}

    /** Member uploads a payment proof; it waits for staff review. */
    public function submitProof(User $user, float $amount, UploadedFile $proof, ?string $reference): Payment
    {
        $this->assertAmountAllowed($user, $amount);

        return Payment::create([
            'user_id' => $user->id,
            'paid_by' => $user->id,
            'amount' => $amount,
            'method' => 'proof',
            'proof_path' => $proof->store('payment-proofs'),
            'reference' => $reference,
        ]);
    }

    /**
     * Pay a member's fee from $payer's wallet (the member themselves or their sponsor).
     * Wallet payments need no review, so they are approved immediately.
     */
    public function payFromWallet(User $payer, User $member, float $amount): Payment
    {
        return DB::transaction(function () use ($payer, $member, $amount) {
            $member = User::whereKey($member->id)->lockForUpdate()->firstOrFail();
            $this->assertAmountAllowed($member, $amount);

            $payment = Payment::create([
                'user_id' => $member->id,
                'paid_by' => $payer->id,
                'amount' => $amount,
                'method' => 'wallet',
            ]);

            $description = $payer->is($member)
                ? 'Fee payment from wallet'
                : "Fee payment for {$member->username}";
            $this->wallet->debit($payer, $amount, 'payment', $description, $payment);

            $this->markApproved($payment, $payer);

            return $payment;
        });
    }

    /** Admin records cash/bank money received directly (e.g. when creating a user). */
    public function recordReceived(User $member, float $amount, User $admin, ?string $note = null): Payment
    {
        return DB::transaction(function () use ($member, $amount, $admin, $note) {
            $member = User::whereKey($member->id)->lockForUpdate()->firstOrFail();
            $this->assertAmountAllowed($member, $amount);

            $payment = Payment::create([
                'user_id' => $member->id,
                'paid_by' => $admin->id,
                'amount' => $amount,
                'method' => 'admin',
                'note' => $note,
            ]);
            $this->markApproved($payment, $admin);

            return $payment;
        });
    }

    public function approve(Payment $payment, User $reviewer, ?string $note = null): void
    {
        DB::transaction(function () use ($payment, $reviewer, $note) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $this->assertPending($payment);

            // Pending proof is already counted in payableRemaining(), so only guard the hard cap.
            $member = $payment->user;
            if ((float) $member->paid_amount + (float) $payment->amount > Setting::money('max_fee') + 0.001) {
                throw ValidationException::withMessages([
                    'payment' => 'Approving this would take the member above the maximum fee of $'.Setting::money('max_fee').'.',
                ]);
            }

            $payment->note = $note;
            $this->markApproved($payment, $reviewer);
        });
    }

    public function reject(Payment $payment, User $reviewer, ?string $note = null): void
    {
        DB::transaction(function () use ($payment, $reviewer, $note) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $this->assertPending($payment);

            $payment->status = 'rejected';
            $payment->note = $note;
            $payment->reviewed_by = $reviewer->id;
            $payment->reviewed_at = now();
            $payment->save();
        });
    }

    /** Adds the amount to the member's paid total, activates them and releases upline commission once. */
    private function markApproved(Payment $payment, User $reviewer): void
    {
        $payment->status = 'approved';
        $payment->reviewed_by = $reviewer->id;
        $payment->reviewed_at = now();
        $payment->save();

        $member = User::whereKey($payment->user_id)->lockForUpdate()->firstOrFail();
        $member->paid_amount = round((float) $member->paid_amount + (float) $payment->amount, 2);
        if ($member->status === 'pending') {
            $member->status = 'active';
        }
        $member->save();

        app(CommissionService::class)->releaseIfQualified($member, $payment);
    }

    private function assertPending(Payment $payment): void
    {
        if ($payment->status !== 'pending') {
            throw ValidationException::withMessages(['payment' => 'This payment has already been '.$payment->status.'.']);
        }
    }

    public function assertAmountAllowed(User $member, float $amount): void
    {
        $min = $member->minimumNextPayment();
        $max = $member->payableRemaining();

        if ($max <= 0) {
            throw ValidationException::withMessages(['amount' => 'This account has already paid the maximum fee (or has a payment awaiting review).']);
        }
        if ($amount < $min || $amount > $max) {
            throw ValidationException::withMessages(['amount' => sprintf('Amount must be between $%s and $%s.', number_format($min, 2), number_format($max, 2))]);
        }
    }
}
