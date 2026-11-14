<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\PaymentService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 20)->setTime(12, 0));
        $this->admin = $this->superAdmin();
    }

    /**
     * November: sponsor (joined in October) + 2 new members; one pays $200 cash (releases
     * $30 commission to the sponsor), one pays $50 via screenshot; sponsor withdraws $20.
     */
    private function scenario(): array
    {
        $sponsor = $this->member(['name' => 'Sara Sponsor']);
        $sponsor->forceFill(['status' => 'active', 'paid_amount' => 200, 'commission_released_at' => now(), 'created_at' => '2026-10-05'])->save();

        $a = $this->member(['name' => 'Ali Fullpay'], $sponsor);
        $b = $this->member(['name' => 'Bina Partial'], $sponsor);
        $payments = app(PaymentService::class);
        $payments->recordReceived($a, 200, $this->admin, 'cash');
        $proof = Payment::create(['user_id' => $b->id, 'paid_by' => $b->id, 'amount' => 50, 'method' => 'proof', 'reference' => 'TX-9']);
        $payments->approve($proof, $this->admin);
        Payment::create(['user_id' => $b->id, 'paid_by' => $b->id, 'amount' => 20, 'method' => 'proof']); // pending: not a sale

        $withdrawal = Withdrawal::create(['user_id' => $sponsor->id, 'amount' => 20, 'method' => 'Bank Transfer', 'account_title' => 'Sara', 'account_number' => 'PK001']);
        DB::transaction(fn () => app(WalletService::class)->debit($sponsor, 20, 'withdrawal', 'w', $withdrawal));
        $withdrawal->forceFill(['status' => 'approved', 'reviewed_at' => now(), 'reviewed_by' => $this->admin->id, 'payout_reference' => 'IBFT-1'])->save();

        return [$sponsor, $a, $b];
    }

    public function test_summary_tallies_with_the_records(): void
    {
        $this->scenario();

        $response = $this->actingAs($this->admin)->get(route('admin.reports.index'))->assertOk();
        $summary = $response->viewData('summary');

        $this->assertSame(2, $summary['members']); // sponsor joined in October
        $this->assertSame(1, $summary['membersFullyPaid']);
        $this->assertEquals(250, $summary['sales']);
        $this->assertSame(2, $summary['salesCount']);
        $this->assertEquals(50, $summary['salesProof']);
        $this->assertEquals(200, $summary['salesAdmin']);
        $this->assertEquals(30, $summary['commission']);
        $this->assertEquals(20, $summary['withdrawn']);
        $this->assertEquals(230, $summary['netCash']); // 250 cash in - 20 out
        $this->assertEquals(20, $summary['pendingPayments']);

        $response->assertSee('Ali Fullpay')->assertSee('Bina Partial')->assertDontSee('Sara Sponsor</a>', false);

        // Each tab lists exactly the records behind its total.
        $this->actingAs($this->admin)->get(route('admin.reports.index', ['type' => 'payments']))->assertOk()
            ->assertSee('TX-9')->assertSee('Received by admin');
        $this->actingAs($this->admin)->get(route('admin.reports.index', ['type' => 'commissions']))->assertOk()
            ->assertSee('Sara Sponsor')->assertSee('Level 1');
        $this->actingAs($this->admin)->get(route('admin.reports.index', ['type' => 'withdrawals']))->assertOk()
            ->assertSee('IBFT-1')->assertSee('PK001');
    }

    public function test_date_range_limits_the_report(): void
    {
        $this->scenario();

        $october = $this->actingAs($this->admin)->get(route('admin.reports.index', ['from' => '2026-10-01', 'to' => '2026-10-31']))->assertOk();
        $this->assertSame(1, $october->viewData('summary')['members']);
        $this->assertEquals(0, $october->viewData('summary')['sales']);

        $this->actingAs($this->admin)->get(route('admin.reports.index', ['from' => '2026-11-10', 'to' => '2026-11-01']))
            ->assertSessionHasErrors('to');
    }

    public function test_month_end_csv_export_contains_member_details(): void
    {
        [, $a] = $this->scenario();

        $response = $this->actingAs($this->admin)->get(route('admin.reports.export', ['type' => 'members', 'from' => '2026-11-01', 'to' => '2026-11-30']));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('members-2026-11-01_to_2026-11-30.csv', $response->headers->get('content-disposition'));

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $lines = array_values(array_filter(explode("\n", trim(substr($csv, 3)))));
        $this->assertCount(3, $lines); // header + 2 members
        $this->assertStringStartsWith('Joined,Name,Username,Email,Mobile,CNIC', $lines[0]);
        $this->assertStringContainsString($a->cnic, $csv);
        $this->assertStringContainsString($a->email, $csv);

        $payments = $this->actingAs($this->admin)->get(route('admin.reports.export', ['type' => 'payments']))->streamedContent();
        $this->assertStringContainsString('TX-9', $payments);
    }

    public function test_reports_need_the_reports_permission(): void
    {
        $this->actingAs($this->member())->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($this->staff(['members.view']))->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($this->staff(['members.view']))->get(route('admin.reports.export'))->assertForbidden();

        $this->actingAs($this->staff(['reports.view']))->get(route('admin.reports.index'))->assertOk()->assertSee('Money tally');
    }
}
