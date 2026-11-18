<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Fixed-amount referral commission over Setting::levelCount() upline levels (7 by default).
 *
 * Uplines are paid exactly once per member, at the moment that member's approved
 * paid total first reaches the full fee. A member who has paid less earns nobody anything.
 */
class CommissionService
{
    public function __construct(private WalletService $wallet) {}

    /**
     * Pays uplines once the member is BOTH active (payment approved / account activated)
     * AND has paid the full fee. Called after every approved payment and whenever an admin
     * activates a member, so a member blocked at approval time releases commission later.
     *
     * Must be called inside a transaction with $member locked.
     */
    public function releaseIfQualified(User $member, ?Model $source = null): bool
    {
        if ($member->commission_released_at !== null || $member->status !== 'active' || ! $member->isFullyPaid()) {
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
        // Every role earns commission from its own tree (members, teachers, staff, super admins).
        if ($upline->trashed() || $upline->status !== 'active') {
            return false;
        }

        // "Upline must be fully paid" only applies to members; other roles never pay a fee.
        return ! $requireFullUpline || ! $upline->isMember() || $upline->isFullyPaid();
    }
}
