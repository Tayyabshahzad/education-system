<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Services\ReportService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function commission(User $user, float $amount, string $when): void
    {
        DB::transaction(fn () => app(WalletService::class)->credit($user, $amount, 'commission', 'test'));
        WalletTransaction::latest('id')->first()->forceFill(['created_at' => $when])->save();
    }

    public function test_admin_dashboard_lists_members_who_have_not_paid_the_full_fee(): void
    {
        $none = $this->member(['name' => 'Nadia Nopay']);
        $partial = $this->member(['name' => 'Parveen Partial']);
        $partial->forceFill(['status' => 'active', 'paid_amount' => 50])->save();
        $full = $this->member(['name' => 'Faisal Fullpay']);
        $full->forceFill(['status' => 'active', 'paid_amount' => 200])->save();
        $blocked = $this->member(['name' => 'Bilal Blocked']);
        $blocked->forceFill(['status' => 'blocked'])->save();

        $this->actingAs($this->superAdmin())->get(route('dashboard'))->assertOk()
            ->assertSee('Full fee not paid')
            ->assertSee('Nadia Nopay')
            ->assertSee('Parveen Partial')
            ->assertSee('$150.00') // remaining for the partial payer
            ->assertViewHas('unpaidCount', 2)
            ->assertViewHas('unpaidMembers', fn ($list) => $list->pluck('id')->sort()->values()->all() === collect([$none->id, $partial->id])->sort()->values()->all());

        // "View all" opens the members list filtered the same way.
        $this->actingAs($this->superAdmin())->get(route('admin.members.index', ['paid' => 'notfull']))->assertOk()
            ->assertSee('Nadia Nopay')->assertSee('Parveen Partial')->assertDontSee('Faisal Fullpay');

        // Staff without members.view do not get the list.
        $this->actingAs($this->staff())->get(route('dashboard'))->assertOk()->assertDontSee('Full fee not paid');
    }

    public function test_personal_chart_counts_team_joins_commission_and_withdrawals_per_month(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 15));
        $user = $this->member();
        $this->commission($user, 30, '2026-11-02 10:00:00');
        $this->commission($user, 20, '2026-11-20 10:00:00');
        $this->commission($user, 10, '2026-09-05 10:00:00');
        $this->commission($user, 99, '2025-10-30 10:00:00'); // 13 months ago: outside the window

        $direct = $this->member(sponsor: $user);
        $this->member(sponsor: $direct); // level 2, joins this month too
        $direct->forceFill(['created_at' => '2026-10-10 10:00:00'])->save();

        Withdrawal::create(['user_id' => $user->id, 'amount' => 25, 'method' => 'Bank', 'account_title' => 'A', 'account_number' => '1'])
            ->forceFill(['status' => 'approved', 'reviewed_at' => '2026-11-05 10:00:00'])->save();
        Withdrawal::create(['user_id' => $user->id, 'amount' => 40, 'method' => 'Bank', 'account_title' => 'A', 'account_number' => '1']); // pending: not counted

        $chart = app(ReportService::class)->personalChart($user, $user->downlineByLevel(3));
        $values = fn (string $metric) => array_column($chart['metrics'][$metric]['values'], 'value');

        $this->assertCount(12, $chart['months']);
        $this->assertSame('2025-12', $chart['months'][0]['key']);
        $this->assertSame('2026-11', $chart['months'][11]['key']);

        $this->assertSame(50.0, $values('commission')[11]);
        $this->assertSame(2, $chart['metrics']['commission']['values'][11]['count']);
        $this->assertSame(10.0, $values('commission')[9]);
        $this->assertSame(60.0, array_sum($values('commission')));

        $this->assertSame(1.0, $values('team')[10]); // October
        $this->assertSame(1.0, $values('team')[11]); // November
        $this->assertSame(25.0, array_sum($values('withdrawn')));
    }

    public function test_every_role_sees_their_performance_chart(): void
    {
        $users = [$this->member(), $this->superAdmin(), $this->staff(), User::factory()->withRole(User::ROLE_TEACHER)->create()];

        foreach ($users as $user) {
            $this->commission($user, 30, now()->toDateTimeString());
            $this->actingAs($user)->get(route('dashboard'))->assertOk()
                ->assertSee('My performance')
                ->assertSee('Team joined')
                ->assertSee(now()->format('M Y').': $30', false)
                ->assertSee('Hide details');
        }
    }

    public function test_business_overview_only_for_report_viewers(): void
    {
        $this->actingAs($this->superAdmin())->get(route('dashboard'))->assertOk()
            ->assertSee('Business overview')->assertSee('Members joined')->assertSee('Sales');
        $this->actingAs($this->staff())->get(route('dashboard'))->assertOk()->assertDontSee('Business overview');
        $this->actingAs($this->staff(['reports.view']))->get(route('dashboard'))->assertOk()->assertSee('Business overview');
    }
}
