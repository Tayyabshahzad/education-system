<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\PaymentService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

        // Default rates 15 / 9 / 5 / 3 / 3 / 0 / 0 % of the $200 paid.
        $expected = [1 => 30, 2 => 18, 3 => 10, 4 => 6, 5 => 6, 6 => 0, 7 => 0];
        foreach ($expected as $level => $amount) {
            $this->assertEquals($amount, $chain[$level]->fresh()->wallet_balance, "Level {$level} earns \${$amount}");
            $this->assertSame($amount > 0 ? $level : null, $chain[$level]->walletTransactions()->value('level'));
        }
        $this->assertStringContainsString('15% of $200.00', $chain[1]->walletTransactions()->value('description'));
        $this->assertEquals(0, $chain[8]->fresh()->wallet_balance, 'Level 8 must not earn');
        $this->assertNotNull($new->fresh()->commission_released_at);
        $this->assertSame('active', $new->fresh()->status);
    }

    public function test_a_level_set_to_zero_is_skipped_but_higher_levels_still_earn(): void
    {
        Setting::setMany(['commission_level_5' => 0, 'commission_level_6' => 2, 'commission_level_7' => 1]);
        $chain = $this->chain();

        $this->pay($chain[0], 200);

        $this->assertEquals(0, $chain[5]->fresh()->wallet_balance);
        $this->assertEquals(4, $chain[6]->fresh()->wallet_balance); // 2% of 200
        $this->assertEquals(2, $chain[7]->fresh()->wallet_balance); // 1% of 200
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
        $this->assertEquals(18, $l2->fresh()->wallet_balance);
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
        $this->assertEquals(18, $l2->fresh()->wallet_balance);
    }

    public function test_admin_changed_percentages_are_used(): void
    {
        [$new, $l1, $l2, $l3] = $this->chain();
        Setting::setMany(['commission_level_1' => 40, 'commission_level_2' => 15, 'commission_level_3' => 5, 'full_fee' => 150, 'max_fee' => 150]);

        $this->pay($new, 150);

        $this->assertEquals(60, $l1->fresh()->wallet_balance);   // 40% of 150
        $this->assertEquals(22.5, $l2->fresh()->wallet_balance); // 15% of 150
        $this->assertEquals(7.5, $l3->fresh()->wallet_balance);  // 5% of 150
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
        $this->assertEquals(18, $staff->fresh()->wallet_balance, 'Staff earns level 2 (9%)');

        foreach ([User::factory()->withRole(User::ROLE_TEACHER)->create(), $this->admin] as $sponsor) {
            $direct = $this->member(sponsor: $sponsor);
            $this->pay($direct, 200);
            $this->assertEquals(30, $sponsor->fresh()->wallet_balance, 'Direct sponsor earns level 1');
        }
    }

    public function test_commission_amounts_for_all_seven_levels_are_stored_in_the_database(): void
    {
        foreach ([1 => 15, 2 => 9, 3 => 5, 4 => 3, 5 => 3, 6 => 0, 7 => 0] as $level => $amount) {
            $this->assertDatabaseHas('settings', ['key' => "commission_level_{$level}", 'value' => $amount]);
        }

        // Re-running never overwrites what an admin saved; it only adds missing keys.
        Setting::setMany(['commission_level_4' => 12]);
        Setting::query()->where('key', 'commission_level_7')->delete();
        $this->assertSame(['commission_level_7'], Setting::insertMissingDefaults());
        $this->assertEquals(12, Setting::money('commission_level_4'));
        $this->assertEquals(0, Setting::money('commission_level_7'));
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

        // All seven levels together may not exceed 100%, and no single level may exceed 100%.
        $this->actingAs($this->admin)->put(route('admin.settings.update'), ['commission_level_7' => 20] + $payload)
            ->assertSessionHasErrors('commission_level_1'); // 40+20+10+8+6+4+20 = 108%
        $this->actingAs($this->admin)->put(route('admin.settings.update'), ['commission_level_7' => 150] + $payload)
            ->assertSessionHasErrors('commission_level_7');
        unset($payload['commission_level_6']);
        $this->actingAs($this->admin)->put(route('admin.settings.update'), $payload)->assertSessionHasErrors('commission_level_6');
    }

    public function test_recalculate_command_reprices_commission_paid_at_old_rates(): void
    {
        // Commission goes out at old rates (30% / 10%), then the admin lowers them to 15% / 9%.
        Setting::setMany(['commission_level_1' => 30, 'commission_level_2' => 10]);
        $chain = $this->chain();
        $this->pay($chain[0], 200);
        DB::transaction(fn () => app(WalletService::class)->credit($chain[1], 5, 'adjustment', 'later bonus'));
        $this->assertEquals(65, $chain[1]->fresh()->wallet_balance); // 60 + 5
        Setting::setMany(['commission_level_1' => 15, 'commission_level_2' => 9]);

        // --since is required; a dry run changes nothing.
        $this->artisan('app:recalculate-commissions')->assertExitCode(1);
        $this->artisan('app:recalculate-commissions', ['--since' => now()->toDateString()])->assertExitCode(0);
        $this->assertEquals(65, $chain[1]->fresh()->wallet_balance);

        $this->artisan('app:recalculate-commissions', ['--since' => now()->toDateString(), '--apply' => true])->assertExitCode(0);

        $l1Tx = $chain[1]->walletTransactions()->where('type', 'commission')->sole();
        $this->assertEquals(30, $l1Tx->amount);
        $this->assertStringContainsString('15% of $200.00', $l1Tx->description);
        $this->assertEquals(35, $chain[1]->fresh()->wallet_balance);
        // Every balance_after in the history is rebuilt (commission 30, then +5 = 35).
        $this->assertSame(['30.00', '35.00'], $chain[1]->walletTransactions()->orderBy('id')->pluck('balance_after')->all());
        $this->assertEquals(18, $chain[2]->fresh()->wallet_balance);

        // Running it again finds nothing to change.
        $this->artisan('app:recalculate-commissions', ['--since' => now()->toDateString()])
            ->expectsOutputToContain('Nothing to change')->assertExitCode(0);
    }

    public function test_recalculate_command_refuses_when_money_was_already_withdrawn(): void
    {
        Setting::setMany(['commission_level_1' => 30]);
        $chain = $this->chain();
        $this->pay($chain[0], 200);
        DB::transaction(fn () => app(WalletService::class)->debit($chain[1], 50, 'withdrawal', 'paid out'));
        Setting::setMany(['commission_level_1' => 15]);

        $this->artisan('app:recalculate-commissions', ['--since' => now()->toDateString(), '--apply' => true])
            ->expectsOutputToContain('already withdrawn')->assertExitCode(1);

        $this->assertEquals(60, $chain[1]->walletTransactions()->where('type', 'commission')->sole()->amount, 'Nothing changed');
        $this->assertEquals(10, $chain[1]->fresh()->wallet_balance);
    }

    public function test_team_page_shows_seven_levels(): void
    {
        $chain = $this->chain();

        $this->actingAs($chain[8])->get(route('team.index'))->assertOk()
            ->assertSee('Level 7')->assertDontSee('Level 8')
            ->assertSee($chain[1]->username); // 7 levels below L8
    }

    public function test_admin_member_page_shows_commission_by_level_even_after_withdrawal(): void
    {
        // L1 earns from two different chains: once as level 1 ($30), once as level 2 ($18).
        $chain = $this->chain();
        $earner = $chain[1];
        $this->pay($chain[0], 200);
        $direct = $this->member(['name' => 'Second Direct'], $earner);
        $grandchild = $this->member(['name' => 'Grand Child'], $direct);
        $direct->forceFill(['status' => 'active', 'paid_amount' => 200, 'commission_released_at' => now()])->save();
        $this->pay($grandchild, 200);

        $this->assertEquals(48, $earner->fresh()->wallet_balance);
        DB::transaction(fn () => app(WalletService::class)->debit($earner, 40, 'withdrawal', 'paid out'));
        Withdrawal::create(['user_id' => $earner->id, 'amount' => 40, 'method' => 'Bank', 'account_title' => 'A', 'account_number' => '1'])
            ->forceFill(['status' => 'approved', 'reviewed_at' => now()])->save();

        $response = $this->actingAs($this->admin)->get(route('admin.members.show', $earner))->assertOk();

        $byLevel = $response->viewData('commissionByLevel');
        $this->assertEquals(30, $byLevel[1]->total);
        $this->assertEquals(18, $byLevel[2]->total);
        $this->assertSame(1, (int) $byLevel[1]->items);
        $this->assertEquals(48, $response->viewData('commissionTotal'), 'Lifetime commission is not reduced by the withdrawal');
        $this->assertEquals(40, $response->viewData('withdrawnTotal'));

        $response->assertSee('Commission earned')
            ->assertSee('Level 1 (direct)')
            ->assertSee('$48.00')
            ->assertSee('15% of $200.00')
            ->assertSee('9% of $200.00')
            ->assertSee($chain[0]->username)
            ->assertSee($grandchild->username)
            ->assertSee(now()->format('d M Y'));
    }
}
