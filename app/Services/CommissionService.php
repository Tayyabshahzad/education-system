<?php

namespace App\Services;

use App\Models\HeldCommission;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Percentage referral commission over Setting::levelCount() upline levels (7 by default):
 * each level earns its configured % (Admin > Settings) of the member's paid total.
 *
 * Uplines are paid exactly once per member, at the moment that member's approved
 * paid total first reaches the full fee. A member who has paid less earns nobody anything.
 * Levels 2–7 follow the 7x7 rule (see levelOpen()); commission that cannot be paid yet
 * is held (HeldCommission) and paid by releaseHeld() once the upline has enough directs.
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
        // Commission is a percentage of what the member has paid at this moment (normally the full fee).
        $base = (float) $member->paid_amount;

        foreach (Setting::commissionLevels() as $level => $percent) {
            if (! $upline) {
                break;
            }

            $amount = Setting::commissionAmount($level, $base);
            if ($amount > 0 && $this->eligible($upline, $requireFullUpline)) {
                if ($this->levelOpen($upline, $level)) {
                    $this->wallet->credit(
                        $upline,
                        $amount,
                        'commission',
                        "Level {$level} commission ({$this->percentLabel($percent)} of \$".number_format($base, 2).") from {$member->username}",
                        $source,
                        $member,
                        $level,
                    );
                } else {
                    // 7x7: not enough directs yet — keep it waiting for this upline.
                    HeldCommission::create([
                        'user_id' => $upline->id,
                        'from_user_id' => $member->id,
                        'level' => $level,
                        'percent' => $percent,
                        'base' => $base,
                        'amount' => $amount,
                        'source_type' => $source?->getMorphClass(),
                        'source_id' => $source?->getKey(),
                    ]);
                }
            }

            $upline = $upline->sponsor;
        }

        // This member (now active and full-fee) is a direct of their sponsor, which may open
        // levels / slots for the sponsor's waiting commission.
        $this->releaseHeld($member->sponsor);

        return true;
    }

    /**
     * Pays ALL of an upline's waiting (held) commission on every level that is open now,
     * oldest first. Call whenever the upline may have gained an active direct.
     * Must run inside a transaction. Returns the number released.
     */
    public function releaseHeld(?User $upline): int
    {
        if (! $upline) {
            return 0;
        }

        $upline = User::withTrashed()->whereKey($upline->id)->lockForUpdate()->first();
        if (! $upline || ! $this->eligible($upline, Setting::flag('upline_must_be_full'))) {
            return 0;
        }

        $directs = $upline->qualifiedDirectCount();
        $released = 0;

        foreach (array_keys(Setting::commissionLevels()) as $level) {
            if ($level === 1 || $directs < $level) {
                continue;
            }

            $waiting = HeldCommission::waiting()->where('user_id', $upline->id)->where('level', $level)
                ->with(['fromUser', 'source'])->orderBy('id')->lockForUpdate()->get();

            foreach ($waiting as $held) {
                $tx = $this->wallet->credit(
                    $upline,
                    (float) $held->amount,
                    'commission',
                    "Level {$level} commission ({$this->percentLabel((float) $held->percent)} of \$".number_format((float) $held->base, 2).") from {$held->fromUser?->username}"
                        .' — held since '.$held->created_at->format('d M Y').', released when level '.$level.' opened',
                    $held->source,
                    $held->fromUser,
                    $level,
                );
                $held->forceFill(['released_at' => now(), 'wallet_transaction_id' => $tx->id])->save();
                $released++;
            }
        }

        return $released;
    }

    /**
     * The 7x7 rule. Level 1 (the upline's own direct) always pays. Level N ≥ 2 is open
     * once the upline has at least N active directs, and then pays for EVERY full-fee member
     * on that level (no limit on how many). Checked when the commission is released; if the
     * level is closed it is held and paid automatically once the upline has enough directs.
     */
    private function levelOpen(User $upline, int $level): bool
    {
        return $level === 1 || $upline->qualifiedDirectCount() >= $level;
    }

    private function percentLabel(float $percent): string
    {
        return rtrim(rtrim(number_format($percent, 2), '0'), '.').'%';
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
