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
     * By default every upline also gets enough full-fee directs ($directs in total) that
     * all 7 levels are open under the 7x7 rule; pass 1 for a bare single-line chain.
     *
     * @return array{0: User, 1: User, 2: User, 3: User, 4: User, 5: User, 6: User, 7: User, 8: User}
     */
    private function chain(int $uplines = 8, int $directs = 7): array
    {
        $chain = [];
        $sponsor = null;
        for ($level = $uplines; $level >= 1; $level--) {
            $sponsor = $this->fullMember($sponsor);
            $this->giveDirects($sponsor, $directs - 1); // the next chain member is the other direct
            $chain[$level] = $sponsor;
        }
        $chain[0] = $this->member(sponsor: $sponsor);
        ksort($chain);

        return $chain;
    }

    /** An active member who already paid the full fee (their own commission long released). */
    private function fullMember(?User $sponsor = null, array $attributes = []): User
    {
        $user = $this->member($attributes, $sponsor);
        $user->forceFill(['status' => 'active', 'paid_amount' => 200, 'commission_released_at' => now()])->save();

        return $user;
    }

    private function giveDirects(User $sponsor, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->fullMember($sponsor);
        }
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
        $l1 = $this->fullMember($staff);
        $this->giveDirects($staff, 1); // 2 full-fee directs open level 2 for the staff (7x7 rule)
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

    /* ---------- 7x7 rule ---------- */

    public function test_7x7_tayyab_example_level_n_needs_n_full_fee_directs(): void
    {
        // Tayyab -> Asim, Zahoor (2 full-fee directs). Asim -> Qasim -> Mavia.
        $tayyab = $this->fullMember(attributes: ['name' => 'Tayyab']);
        $asim = $this->member(['name' => 'Asim'], $tayyab);
        $zahoor = $this->member(['name' => 'Zahoor'], $tayyab);
        $this->pay($asim, 200);
        $this->pay($zahoor, 200);
        $this->assertEquals(60, $tayyab->fresh()->wallet_balance, 'Level 1: 15% from each direct');

        $qasim = $this->member(['name' => 'Qasim'], $asim);
        $this->pay($qasim, 200);
        $this->assertEquals(30, $asim->fresh()->wallet_balance, 'Asim: Qasim is his level 1');
        $this->assertEquals(78, $tayyab->fresh()->wallet_balance, 'Tayyab has 2 directs, so level 2 (9%) is open');

        $mavia = $this->member(['name' => 'Mavia'], $qasim);
        $this->pay($mavia, 200);
        $this->assertEquals(30, $qasim->fresh()->wallet_balance, 'Qasim: Mavia is his level 1');
        $this->assertEquals(30, $asim->fresh()->wallet_balance, 'Asim has only 1 direct: level 2 closed');
        $this->assertEquals(78, $tayyab->fresh()->wallet_balance, 'Mavia is Tayyab\'s level 3: needs 3 directs');
        $this->assertSame(0, $tayyab->walletTransactions()->where('level', 3)->count());
        // …so Mavia's 5% ($10) waits for Tayyab instead of being lost.
        $held = $tayyab->heldCommissions()->waiting()->sole();
        $this->assertSame([3, $mavia->id, '10.00'], [$held->level, $held->from_user_id, $held->amount]);

        // Usman, a third full-fee direct, opens level 3: Tayyab gets Usman's $30 AND Mavia's waiting $10.
        $usman = $this->member(['name' => 'Usman'], $tayyab);
        $this->pay($usman, 200);
        $this->assertEquals(118, $tayyab->fresh()->wallet_balance); // 78 + 30 + 10
        $this->assertNotNull($held->fresh()->released_at);
        $mavias = $tayyab->walletTransactions()->where('level', 3)->sole();
        $this->assertSame($mavia->id, $mavias->from_user_id);
        $this->assertStringContainsString('held since', $mavias->description);
        $this->assertSame(0, $tayyab->heldCommissions()->waiting()->count());

        // The next level-3 member pays straight away (3 directs = 3 slots on level 3).
        $this->pay($this->member(sponsor: $qasim), 200);
        $this->assertEquals(128, $tayyab->fresh()->wallet_balance);
        $this->assertSame(2, $tayyab->walletTransactions()->where('level', 3)->count());
    }

    public function test_7x7_an_open_level_pays_every_full_fee_member_on_it(): void
    {
        // Admin -> Muhammad (1 direct). Muhammad brings 10 members who pay $200.
        $muhammad = $this->member(['name' => 'Muhammad'], $this->admin);
        $this->pay($muhammad, 25);
        for ($i = 1; $i <= 10; $i++) {
            $this->pay($this->member(sponsor: $muhammad), 200);
        }
        $this->assertEquals(300, $muhammad->fresh()->wallet_balance, 'Muhammad: 10 × 15%');
        $this->assertEquals(0, $this->admin->fresh()->wallet_balance, 'Admin has 1 direct: level 2 closed');
        $this->assertSame(10, $this->admin->heldCommissions()->waiting()->count());

        // Admin brings a second direct: level 2 opens and ALL 10 waiting commissions are paid.
        $newDirect = $this->member(sponsor: $this->admin);
        $this->pay($newDirect, 25);
        $this->assertEquals(180, $this->admin->fresh()->wallet_balance, '10 × 9%');
        $this->assertSame(0, $this->admin->heldCommissions()->waiting()->count());

        // Later members on level 2 — from Muhammad or from the new direct — pay straight away.
        for ($i = 1; $i <= 3; $i++) {
            $this->pay($this->member(sponsor: $muhammad), 200);
        }
        $this->pay($this->member(sponsor: $newDirect), 200);
        $this->assertEquals(180 + 4 * 18, $this->admin->fresh()->wallet_balance);
        $this->assertSame(14, $this->admin->walletTransactions()->where('level', 2)->count());
    }

    public function test_7x7_waiting_commission_is_paid_when_admin_reactivates_a_direct(): void
    {
        $ali = $this->fullMember();
        $umer = $this->fullMember($ali);
        $blocked = $this->fullMember($ali);
        $blocked->forceFill(['status' => 'blocked'])->save(); // doesn't count while blocked

        $this->pay($this->member(sponsor: $umer), 200);
        $this->assertEquals(0, $ali->fresh()->wallet_balance);
        $this->assertSame(1, $ali->heldCommissions()->waiting()->count());

        $this->actingAs($this->admin)->patch(route('admin.members.status', $blocked), ['status' => 'active']);

        $this->assertEquals(18, $ali->fresh()->wallet_balance, 'Second full-fee direct is active again: level 2 opens');
        $this->assertSame(0, $ali->heldCommissions()->waiting()->count());
    }

    public function test_7x7_waiting_commission_keeps_its_original_amount_and_stays_until_unlocked(): void
    {
        $ali = $this->fullMember();
        $umer = $this->fullMember($ali);
        $this->pay($this->member(sponsor: $umer), 200); // Ali has 1 direct: level 2 waits ($18 at 9%)

        Setting::setMany(['commission_level_2' => 5]); // later rate change doesn't touch waiting money
        $this->pay($this->member(sponsor: $umer), 200);  // still 1 direct: waits ($10 at 5%)
        $this->assertEquals(0, $ali->fresh()->wallet_balance);
        $this->assertEquals(28, $ali->heldCommissions()->waiting()->sum('amount'));

        $this->pay($this->member(sponsor: $ali), 200); // 2nd direct: 2 level-2 slots → both released
        $this->assertEquals(30 + 18 + 10, $ali->fresh()->wallet_balance);

        $this->actingAs($this->admin)->get(route('admin.members.show', $ali))->assertOk()->assertDontSee('Waiting (7x7)');
    }

    public function test_dashboard_and_admin_page_show_waiting_commission(): void
    {
        $ali = $this->fullMember();
        $umer = $this->fullMember($ali);
        $this->pay($this->member(['name' => 'Saleem Waits'], $umer), 200);

        $this->actingAs($ali)->get(route('dashboard'))->assertOk()
            ->assertSee('$18.00</strong> commission waiting', false)
            ->assertSee('Bring 1 more active direct and')
            ->assertSee('goes into your wallet right away')
            ->assertSee('1 waiting');
        $this->actingAs($this->admin)->get(route('admin.members.show', $ali))->assertOk()
            ->assertSee('Waiting (7x7)')
            ->assertSee('Saleem Waits')
            ->assertSee('2 active directs (has 1)');
    }

    public function test_7x7_active_directs_count_even_below_full_fee_but_not_pending_or_blocked(): void
    {
        $ali = $this->fullMember();
        $umer = $this->fullMember($ali);
        $this->member(sponsor: $ali); // registered, never paid (pending): doesn't count
        $blocked = $this->fullMember($ali);
        $blocked->forceFill(['status' => 'blocked'])->save();

        $this->assertSame(1, $ali->qualifiedDirectCount());
        $this->pay($this->member(sponsor: $umer), 200);
        $this->assertEquals(0, $ali->fresh()->wallet_balance, 'Only 1 active direct: level 2 closed, $18 waits');
        $this->assertFalse($ali->commissionLevelStatus()[2]['open']);
        $this->assertTrue($ali->commissionLevelStatus()[1]['open']);

        // A new direct whose $25 joining fee is approved counts at once: level 2 opens, the $18 is released.
        $this->pay($this->member(sponsor: $ali), 25);
        $this->assertSame(2, $ali->qualifiedDirectCount());
        $this->assertEquals(18, $ali->fresh()->wallet_balance, 'No level-1 commission from a $25 member, but level 2 unlocked');
    }

    public function test_7x7_live_admin_case(): void
    {
        // admin -> muhammad ($25) -> qaisar, abduljabbar, azrar ($200), aftab ($200), muhammadz, qazi, raja.
        $muhammad = $this->member(['name' => 'Muhammad'], $this->admin);
        $this->pay($muhammad, 25);
        foreach (['Qaisar' => 25, 'Abduljabbar' => 0, 'Azrar' => 200, 'Aftab' => 200, 'MuhammadZ' => 25, 'Qazi' => 0, 'Raja' => 25] as $name => $paid) {
            $m = $this->member(['name' => $name], $muhammad);
            if ($paid) {
                $this->pay($m, $paid);
            }
        }

        $this->assertEquals(60, $muhammad->fresh()->wallet_balance, 'Muhammad: 15% from Azrar and Aftab only');
        $this->assertEquals(0, $this->admin->fresh()->wallet_balance, 'Admin: nothing yet (1 active direct)');
        $this->assertEquals(36, $this->admin->heldCommissions()->waiting()->sum('amount'), '9% + 9% waiting');

        // Admin brings one more member who pays the $25 joining fee: 2 active directs, the 9% + 9% is paid.
        $this->pay($this->member(sponsor: $this->admin), 25);
        $this->assertEquals(36, $this->admin->fresh()->wallet_balance);

        // Muhammad completes $200: admin gets Muhammad's level-1 15% too.
        $this->pay($muhammad, 175);
        $this->assertEquals(66, $this->admin->fresh()->wallet_balance);
    }

    public function test_dashboard_shows_which_levels_are_unlocked(): void
    {
        $ali = $this->fullMember();
        $this->fullMember($ali);
        $this->fullMember($ali);

        $this->actingAs($ali)->get(route('dashboard'))->assertOk()
            ->assertSee('2 active directs')
            ->assertSee('Needs 3 directs');
    }

    public function test_next_unlock_tells_how_many_directs_release_how_much(): void
    {
        // Tayyab (1 direct: Asim). Asim brings Qasim and Hamza (level 2, $18 each, needs 2 directs);
        // Qasim brings Mavia (level 3, $10, needs 3 directs).
        $tayyab = $this->fullMember();
        $asim = $this->fullMember($tayyab);
        $qasim = $this->member(sponsor: $asim);
        $this->pay($qasim, 200);
        $this->pay($this->member(['name' => 'Hamza'], $asim), 200);
        $this->pay($this->member(['name' => 'Mavia'], $qasim), 200);

        $unlock = $tayyab->nextCommissionUnlock();
        $this->assertSame(46.0, $unlock['total']);
        $this->assertSame(3, $unlock['waitingCount']);
        $this->assertSame(1, $unlock['directsNeeded'], 'One more direct opens level 2');
        $this->assertSame(36.0, $unlock['releaseAmount'], 'Level 2 releases both $18; Mavia still needs a 3rd direct');

        $this->actingAs($tayyab)->get(route('dashboard'))->assertOk()
            ->assertSee('$46.00</strong> commission waiting', false)
            ->assertSee('from 3 members in your team');

        $this->pay($this->member(sponsor: $tayyab), 25); // 2nd direct
        $unlock = $tayyab->fresh()->nextCommissionUnlock();
        $this->assertSame(10.0, $unlock['total']);
        $this->assertSame(1, $unlock['directsNeeded']);

        // After the direct arrives everything is released and the banner disappears.
        $this->pay($this->member(sponsor: $tayyab), 200);
        $this->assertNull($tayyab->fresh()->nextCommissionUnlock());
        $this->actingAs($tayyab->fresh())->get(route('dashboard'))->assertOk()->assertDontSee('commission waiting');
    }

    public function test_staff_and_admin_dashboards_show_the_waiting_banner_too(): void
    {
        $staff = $this->staff();
        $direct = $this->fullMember($staff);
        $this->pay($this->member(sponsor: $direct), 200); // staff has 1 direct: level 2 waits

        $this->actingAs($staff)->get(route('dashboard'))->assertOk()
            ->assertSee('commission waiting')
            ->assertSee('Bring 1 more active direct');
    }

    public function test_super_admin_dashboard_shows_their_own_waiting_banner(): void
    {
        $direct = $this->fullMember($this->admin);
        $this->pay($this->member(sponsor: $direct), 200); // admin has 1 direct: their level-2 $18 waits

        $html = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()
            ->assertSee('$18.00</strong> commission waiting', false)
            ->assertSee('Bring 1 more active direct')
            ->getContent();

        // The banner is the first thing on the page, above the business stats.
        $this->assertLessThan(strpos($html, 'Full package members'), strpos($html, 'commission waiting'));
    }

    public function test_apply_7x7_command_moves_old_commission_to_waiting_and_it_comes_back(): void
    {
        // Before the rule: admin (1 direct who paid only $25) got two level-2 commissions.
        $muhammad = $this->member(sponsor: $this->admin);
        $muhammad->forceFill(['status' => 'active', 'paid_amount' => 25])->save();
        foreach (['aftab', 'azrar'] as $i => $name) {
            $m = $this->member(['name' => $name], $muhammad);
            $m->forceFill(['status' => 'active', 'paid_amount' => 200, 'commission_released_at' => now()])->save();
            DB::transaction(fn () => app(WalletService::class)->credit($this->admin, 18, 'commission', 'old', null, $m, 2));
        }
        $this->assertEquals(36, $this->admin->fresh()->wallet_balance);

        // Dry run changes nothing.
        $this->artisan('app:apply-7x7')->expectsOutputToContain('waiting (needs 2 directs)')->assertExitCode(0);
        $this->assertEquals(36, $this->admin->fresh()->wallet_balance);

        $this->artisan('app:apply-7x7', ['--apply' => true])->assertExitCode(0);
        $this->assertEquals(0, $this->admin->fresh()->wallet_balance);
        $this->assertSame(0, $this->admin->walletTransactions()->count());
        $this->assertEquals(36, $this->admin->heldCommissions()->waiting()->sum('amount'));
        $this->artisan('app:apply-7x7')->expectsOutputToContain('Nothing to change')->assertExitCode(0);

        // Muhammad tops up to the full fee and a second full-fee direct joins: level 2 opens, both come back.
        $this->pay($muhammad, 175);
        $this->assertEquals(30, $this->admin->fresh()->wallet_balance, 'Level 1 from Muhammad; level 2 still needs 2 directs');
        $this->pay($this->member(sponsor: $this->admin), 200);
        $this->assertEquals(30 + 30 + 36, $this->admin->fresh()->wallet_balance);
        $this->assertSame(0, $this->admin->heldCommissions()->waiting()->count());
    }

    public function test_apply_7x7_leaves_open_levels_alone_and_moves_closed_ones(): void
    {
        // Ali has 2 directs and earlier got 3 level-2 and 1 level-3 commissions.
        $ali = $this->fullMember();
        $umer = $this->fullMember($ali);
        $this->fullMember($ali);
        foreach ([1, 2, 3] as $i) {
            $m = $this->fullMember($umer);
            DB::transaction(fn () => app(WalletService::class)->credit($ali, 18, 'commission', "L2 old {$i}", null, $m, 2));
        }
        $deep = $this->fullMember($m);
        DB::transaction(fn () => app(WalletService::class)->credit($ali, 10, 'commission', 'L3 old', null, $deep, 3));

        $this->artisan('app:apply-7x7', ['--apply' => true])->assertExitCode(0);

        // Level 2 is open (2 directs): all three stay. Level 3 needs 3 directs: it waits.
        $this->assertEquals(54, $ali->fresh()->wallet_balance);
        $this->assertSame(['L2 old 1', 'L2 old 2', 'L2 old 3'], $ali->walletTransactions()->orderBy('id')->pluck('description')->all());
        $this->assertSame([3], $ali->heldCommissions()->waiting()->pluck('level')->all());
    }

    public function test_apply_7x7_refuses_when_money_was_already_withdrawn(): void
    {
        $muhammad = $this->member(sponsor: $this->admin);
        $m = $this->fullMember($muhammad);
        DB::transaction(fn () => app(WalletService::class)->credit($this->admin, 18, 'commission', 'old', null, $m, 2));
        DB::transaction(fn () => app(WalletService::class)->debit($this->admin, 10, 'withdrawal', 'paid out'));

        $this->artisan('app:apply-7x7', ['--apply' => true])->expectsOutputToContain('already withdrawn')->assertExitCode(1);
        $this->assertEquals(8, $this->admin->fresh()->wallet_balance);
        $this->assertSame(0, $this->admin->heldCommissions()->count());
    }
}
