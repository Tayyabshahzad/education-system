<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentAndWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
    }

    public function test_member_uploads_proof_and_staff_approves_it(): void
    {
        $sponsor = $this->member();
        $sponsor->forceFill(['status' => 'active', 'paid_amount' => 200])->save();
        $member = $this->member(sponsor: $sponsor);

        $this->actingAs($member)->post(route('payments.store'), [
            'method' => 'proof',
            'amount' => 200,
            'proof' => UploadedFile::fake()->image('receipt.jpg'),
            'reference' => 'TX-1',
        ])->assertSessionHasNoErrors();

        $payment = Payment::sole();
        $this->assertSame('pending', $payment->status);
        Storage::assertExists($payment->proof_path);
        $this->assertSame('pending', $member->fresh()->status);

        // Member can view own proof; another member cannot.
        $this->actingAs($member)->get(route('payments.proof', $payment))->assertOk();
        $this->actingAs($this->member())->get(route('payments.proof', $payment))->assertForbidden();

        $staff = $this->staff(['payments.view', 'payments.review']);
        $this->actingAs($staff)->post(route('admin.payments.approve', $payment))->assertSessionHasNoErrors();

        $this->assertSame('approved', $payment->fresh()->status);
        $this->assertSame('active', $member->fresh()->status);
        $this->assertEquals(200, $member->fresh()->paid_amount);
        $this->assertEquals(30, $sponsor->fresh()->wallet_balance);

        // Approving twice does nothing.
        $this->actingAs($staff)->post(route('admin.payments.approve', $payment))->assertSessionHasErrors('payment');
        $this->assertEquals(200, $member->fresh()->paid_amount);
    }

    public function test_rejected_payment_needs_a_reason_and_changes_nothing(): void
    {
        $member = $this->member();
        $payment = Payment::create(['user_id' => $member->id, 'paid_by' => $member->id, 'amount' => 25, 'method' => 'proof']);
        $staff = $this->staff(['payments.view', 'payments.review']);

        $this->actingAs($staff)->post(route('admin.payments.reject', $payment))->assertSessionHasErrors('note');
        $this->actingAs($staff)->post(route('admin.payments.reject', $payment), ['note' => 'Blurry'])->assertSessionHasNoErrors();

        $this->assertSame('rejected', $payment->fresh()->status);
        $this->assertEquals(0, $member->fresh()->paid_amount);
    }

    public function test_first_payment_must_cover_joining_fee_and_total_cannot_exceed_max(): void
    {
        $member = $this->member();

        $this->actingAs($member)->post(route('payments.store'), [
            'method' => 'proof', 'amount' => 10, 'proof' => UploadedFile::fake()->image('r.jpg'),
        ])->assertSessionHasErrors('amount');

        $this->actingAs($member)->post(route('payments.store'), [
            'method' => 'proof', 'amount' => 25, 'proof' => UploadedFile::fake()->image('r.jpg'),
        ])->assertSessionHasNoErrors();

        // 25 pending, so only 175 more may be submitted.
        $this->actingAs($member)->post(route('payments.store'), [
            'method' => 'proof', 'amount' => 180, 'proof' => UploadedFile::fake()->image('r.jpg'),
        ])->assertSessionHasErrors('amount');
    }

    public function test_member_tops_up_from_wallet_instantly(): void
    {
        $member = $this->member();
        $member->forceFill(['status' => 'active', 'paid_amount' => 25, 'wallet_balance' => 200])->save();

        $this->actingAs($member)->post(route('payments.store'), ['method' => 'wallet', 'amount' => 175])
            ->assertSessionHasNoErrors();

        $member->refresh();
        $this->assertEquals(200, $member->paid_amount);
        $this->assertEquals(25, $member->wallet_balance);
        $this->assertSame('approved', Payment::sole()->status);
    }

    public function test_wallet_payment_fails_without_enough_balance(): void
    {
        $member = $this->member();
        $member->forceFill(['status' => 'active', 'paid_amount' => 25, 'wallet_balance' => 50])->save();

        $this->actingAs($member)->post(route('payments.store'), ['method' => 'wallet', 'amount' => 175])
            ->assertSessionHasErrors('amount');

        $this->assertEquals(25, $member->fresh()->paid_amount);
        $this->assertEquals(50, $member->fresh()->wallet_balance);
        $this->assertSame(0, Payment::count());
    }

    public function test_withdrawal_holds_funds_and_rejection_refunds_them(): void
    {
        $member = $this->member();
        $member->forceFill(['status' => 'active', 'wallet_balance' => 60])->save();

        $this->actingAs($member)->post(route('withdrawals.store'), [
            'amount' => 50, 'method' => 'Easypaisa', 'account_details' => '0300',
        ])->assertSessionHasNoErrors();
        $this->assertEquals(10, $member->fresh()->wallet_balance);

        // Cannot request more than the remaining balance.
        $this->actingAs($member)->post(route('withdrawals.store'), [
            'amount' => 20, 'method' => 'Easypaisa', 'account_details' => '0300',
        ])->assertSessionHasErrors('amount');

        $staff = $this->staff(['withdrawals.view', 'withdrawals.review']);
        $this->actingAs($staff)->post(route('admin.withdrawals.reject', Withdrawal::sole()), ['note' => 'Wrong number']);

        $this->assertEquals(60, $member->fresh()->wallet_balance);
        $this->assertSame('rejected', Withdrawal::sole()->status);
    }

    public function test_approved_withdrawal_keeps_funds_deducted(): void
    {
        $member = $this->member();
        $member->forceFill(['status' => 'active', 'wallet_balance' => 30])->save();
        $this->actingAs($member)->post(route('withdrawals.store'), ['amount' => 30, 'method' => 'Bank Transfer', 'account_details' => 'IBAN']);

        $this->actingAs($this->superAdmin())->post(route('admin.withdrawals.approve', Withdrawal::sole()), ['note' => 'REF9']);

        $this->assertSame('approved', Withdrawal::sole()->status);
        $this->assertEquals(0, $member->fresh()->wallet_balance);
    }

    public function test_withdrawal_below_minimum_is_rejected(): void
    {
        $member = $this->member();
        $member->forceFill(['wallet_balance' => 100])->save();

        $this->actingAs($member)->post(route('withdrawals.store'), ['amount' => 5, 'method' => 'Easypaisa', 'account_details' => 'x'])
            ->assertSessionHasErrors('amount');
    }
}
