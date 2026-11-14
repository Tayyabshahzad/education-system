<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->superAdmin();
    }

    /** Builds L4 -> L3 -> L2 -> L1 -> new member, uplines active and fully paid. */
    private function chain(): array
    {
        $l4 = $this->member();
        $l3 = $this->member(sponsor: $l4);
        $l2 = $this->member(sponsor: $l3);
        $l1 = $this->member(sponsor: $l2);
        foreach ([$l4, $l3, $l2, $l1] as $u) {
            $u->forceFill(['status' => 'active', 'paid_amount' => 200, 'commission_released_at' => now()])->save();
        }
        $new = $this->member(sponsor: $l1);

        return [$new, $l1, $l2, $l3, $l4];
    }

    private function pay(User $member, float $amount): void
    {
        app(PaymentService::class)->recordReceived($member->fresh(), $amount, $this->admin);
    }

    public function test_full_payment_pays_30_20_10_to_three_upline_levels_only(): void
    {
        [$new, $l1, $l2, $l3, $l4] = $this->chain();

        $this->pay($new, 200);

        $this->assertEquals(30, $l1->fresh()->wallet_balance);
        $this->assertEquals(20, $l2->fresh()->wallet_balance);
        $this->assertEquals(10, $l3->fresh()->wallet_balance);
        $this->assertEquals(0, $l4->fresh()->wallet_balance, 'Level 4 must not earn');
        $this->assertNotNull($new->fresh()->commission_released_at);
        $this->assertSame('active', $new->fresh()->status);
    }

    public function test_payment_below_full_fee_pays_no_commission(): void
    {
        [$new, $l1] = $this->chain();

        $this->pay($new, 199);

        $this->assertEquals(0, $l1->fresh()->wallet_balance);
        $this->assertNull($new->fresh()->commission_released_at);
        $this->assertSame('active', $new->fresh()->status, 'Any approved payment activates the account');
    }

    public function test_top_up_reaching_full_fee_releases_commission_exactly_once(): void
    {
        [$new, $l1, $l2] = $this->chain();
        Setting::setMany(['max_fee' => 300]);

        $this->pay($new, 25);
        $this->assertEquals(0, $l1->fresh()->wallet_balance);

        $this->pay($new, 175); // reaches 200
        $this->assertEquals(30, $l1->fresh()->wallet_balance);

        $this->pay($new, 50); // above full fee: nothing more
        $this->assertEquals(30, $l1->fresh()->wallet_balance);
        $this->assertEquals(20, $l2->fresh()->wallet_balance);
        $this->assertSame(1, $l1->walletTransactions()->where('type', 'commission')->count(), 'Only one commission row for L1');
    }

    public function test_blocked_and_pending_uplines_are_skipped_without_shifting_levels(): void
    {
        [$new, $l1, $l2, $l3] = $this->chain();
        $l1->forceFill(['status' => 'blocked'])->save();
        $l2->forceFill(['status' => 'pending'])->save();

        $this->pay($new, 200);

        $this->assertEquals(0, $l1->fresh()->wallet_balance);
        $this->assertEquals(0, $l2->fresh()->wallet_balance);
        $this->assertEquals(10, $l3->fresh()->wallet_balance, 'L3 still gets the level-3 amount, not a promoted one');
    }

    public function test_upline_must_be_full_setting_skips_partially_paid_uplines(): void
    {
        [$new, $l1, $l2] = $this->chain();
        $l1->forceFill(['paid_amount' => 25])->save();
        Setting::setMany(['upline_must_be_full' => true]);

        $this->pay($new, 200);

        $this->assertEquals(0, $l1->fresh()->wallet_balance);
        $this->assertEquals(20, $l2->fresh()->wallet_balance);
    }

    public function test_admin_changed_amounts_are_used(): void
    {
        [$new, $l1, $l2, $l3] = $this->chain();
        Setting::setMany(['commission_level_1' => 40, 'commission_level_2' => 15, 'commission_level_3' => 5, 'full_fee' => 150, 'max_fee' => 150]);

        $this->pay($new, 150);

        $this->assertEquals(40, $l1->fresh()->wallet_balance);
        $this->assertEquals(15, $l2->fresh()->wallet_balance);
        $this->assertEquals(5, $l3->fresh()->wallet_balance);
    }

    public function test_ledger_balance_matches_wallet(): void
    {
        [$new, $l1] = $this->chain();
        $this->pay($new, 200);

        $tx = $l1->fresh()->walletTransactions()->sole();
        $this->assertSame('commission', $tx->type);
        $this->assertSame(1, $tx->level);
        $this->assertSame($new->id, $tx->from_user_id);
        $this->assertEquals(30, $tx->balance_after);
    }

    public function test_teacher_staff_and_admin_earn_commission_from_their_own_tree(): void
    {
        // Staff -> L1 member -> new member, with "upline must be fully paid" switched on:
        // staff never pay a fee, so that rule must not block them.
        Setting::setMany(['upline_must_be_full' => true]);
        $staff = $this->staff();
        $l1 = $this->member(sponsor: $staff);
        $l1->forceFill(['status' => 'active', 'paid_amount' => 200, 'commission_released_at' => now()])->save();
        $new = $this->member(sponsor: $l1);

        $this->pay($new, 200);

        $this->assertEquals(30, $l1->fresh()->wallet_balance);
        $this->assertEquals(20, $staff->fresh()->wallet_balance, 'Staff earns level 2');

        foreach ([User::factory()->withRole(User::ROLE_TEACHER)->create(), $this->admin] as $sponsor) {
            $direct = $this->member(sponsor: $sponsor);
            $this->pay($direct, 200);
            $this->assertEquals(30, $sponsor->fresh()->wallet_balance, 'Direct sponsor earns level 1');
        }
    }
}
