<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Fixed-amount, 3-level referral commission.
 *
 * Uplines are paid exactly once per member, at the moment that member's approved
 * paid total first reaches the full fee. A member who has paid less earns nobody anything.
 */
class CommissionService
{
    public function __construct(private WalletService $wallet) {}

    /** Must be called inside a transaction with $member locked. */
    public function releaseIfQualified(User $member, ?Model $source = null): bool
    {
        if ($member->commission_released_at !== null || ! $member->isFullyPaid()) {
            return false;
        }

        $member->commission_released_at = now();
        $member->save();

        $requireFullUpline = Setting::flag('upline_must_be_full');
        $upline = $member->sponsor;

        foreach (Setting::commissionLevels() as $level => $amount) {
            if (! $upline) {
                break;
            }

            if ($amount > 0 && $this->eligible($upline, $requireFullUpline)) {
                $this->wallet->credit(
                    $upline,
                    $amount,
                    'commission',
                    "Level {$level} commission from {$member->username}",
                    $source,
                    $member,
                    $level,
                );
            }

            $upline = $upline->sponsor;
        }

        return true;
    }

    private function eligible(User $upline, bool $requireFullUpline): bool
    {
        if ($upline->trashed() || $upline->status !== 'active' || ! $upline->isMember()) {
            return false;
        }

        return ! $requireFullUpline || $upline->isFullyPaid();
    }
}
