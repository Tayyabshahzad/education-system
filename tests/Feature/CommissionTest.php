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

    /**
     * Builds L8 -> L7 -> … -> L1 -> new member, uplines active and fully paid.
     *
     * @return array{0: User, 1: User, 2: User, 3: User, 4: User, 5: User, 6: User, 7: User, 8: User}
     */
    private function chain(int $uplines = 8): array
    {
        $chain = [];
        $sponsor = null;
        for ($level = $uplines; $level >= 1; $level--) {
            $sponsor = $this->member(sponsor: $sponsor);
            $sponsor->forceFill(['status' => 'active', 'paid_amount' => 200, 'commission_released_at' => now()])->save();
            $chain[$level] = $sponsor;
        }
        $chain[0] = $this->member(sponsor: $sponsor);
        ksort($chain);

        return $chain;
    }

    private function pay(User $member, float $amount): void
    {
        app(PaymentService::class)->recordReceived($member->fresh(), $amount, $this->admin);
    }

    public function test_full_payment_pays_all_seven_upline_levels_only(): void
    {
        $chain = $this->chain();
        $new = $chain[0];

        $this->pay($new, 200);

        $expected = [1 => 30, 2 => 20, 3 => 10, 4 => 5, 5 => 5, 6 => 5, 7 => 5];
        foreach ($expected as $level => $amount) {
            $this->assertEquals($amount, $chain[$level]->fresh()->wallet_balance, "Level {$level} earns \${$amount}");
            $this->assertSame($level, $chain[$level]->walletTransactions()->value('level'));
        }
        $this->assertEquals(0, $chain[8]->fresh()->wallet_balance, 'Level 8 must not earn');
        $this->assertNotNull($new->fresh()->commission_released_at);
        $this->assertSame('active', $new->fresh()->status);
    }

    public function test_a_level_set_to_zero_is_skipped_but_higher_levels_still_earn(): void
    {
        Setting::setMany(['commission_level_5' => 0]);
        $chain = $this->chain();

        $this->pay($chain[0], 200);

        $this->assertEquals(0, $chain[5]->fresh()->wallet_balance);
        $this->assertEquals(5, $chain[6]->fresh()->wallet_balance);
        $this->assertEquals(5, $chain[7]->fresh()->wallet_balance);
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

    public function test_commission_amounts_for_all_seven_levels_are_stored_in_the_database(): void
    {
        foreach ([1 => 30, 2 => 20, 3 => 10, 4 => 5, 5 => 5, 6 => 5, 7 => 5] as $level => $amount) {
            $this->assertDatabaseHas('settings', ['key' => "commission_level_{$level}", 'value' => $amount]);
        }

        // Re-running never overwrites what an admin saved; it only adds missing keys.
        Setting::setMany(['commission_level_4' => 12]);
        Setting::query()->where('key', 'commission_level_7')->delete();
        $this->assertSame(['commission_level_7'], Setting::insertMissingDefaults());
        $this->assertEquals(12, Setting::money('commission_level_4'));
        $this->assertEquals(5, Setting::money('commission_level_7'));
    }

    public function test_admin_settings_page_edits_all_seven_levels(): void
    {
        $this->actingAs($this->admin)->get(route('admin.settings.edit'))->assertOk()
            ->assertSee('Referral commission (7 levels)')
            ->assertSee('name="commission_level_7"', false);

        $amounts = [1 => 40, 2 => 20, 3 => 10, 4 => 8, 5 => 6, 6 => 4, 7 => 2];
        $payload = ['min_join_fee' => 25, 'full_fee' => 200, 'max_fee' => 200, 'min_withdrawal' => 10, 'withdrawal_methods' => 'Bank'];
        foreach ($amounts as $level => $amount) {
            $payload["commission_level_{$level}"] = $amount;
        }
        $this->actingAs($this->admin)->put(route('admin.settings.update'), $payload)->assertSessionHasNoErrors();
        $this->assertSame([1 => 40.0, 2 => 20.0, 3 => 10.0, 4 => 8.0, 5 => 6.0, 6 => 4.0, 7 => 2.0], Setting::commissionLevels());

        // All seven levels together may not exceed the full fee.
        $this->actingAs($this->admin)->put(route('admin.settings.update'), ['commission_level_7' => 150] + $payload)
            ->assertSessionHasErrors('commission_level_1');
        unset($payload['commission_level_6']);
        $this->actingAs($this->admin)->put(route('admin.settings.update'), $payload)->assertSessionHasErrors('commission_level_6');
    }

    public function test_team_page_shows_seven_levels(): void
    {
        $chain = $this->chain();

        $this->actingAs($chain[8])->get(route('team.index'))->assertOk()
            ->assertSee('Level 7')->assertDontSee('Level 8')
            ->assertSee($chain[1]->username); // 7 levels below L8
    }
}
