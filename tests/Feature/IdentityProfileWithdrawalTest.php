<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\PaymentService;
use App\Support\Identity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IdentityProfileWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    private function registerPayload(User $sponsor, array $overrides = []): array
    {
        return array_merge([
            'sponsor_code' => $sponsor->referral_code, 'joining_amount' => 200,
            'name' => 'Ali Raza', 'username' => 'aliraza', 'email' => 'ali@example.com',
            'phone' => '0301 2345678', 'cnic' => '35202-7654321-9', 'country' => 'Pakistan',
            'password' => 'password', 'password_confirmation' => 'password', 'terms' => '1',
            'proof' => UploadedFile::fake()->image('r.jpg'),
        ], $overrides);
    }

    /* ---------- CNIC / phone / referral code ---------- */

    public function test_identity_values_are_normalised(): void
    {
        $this->assertSame('35202-1234567-1', Identity::cnic('3520212345671'));
        $this->assertSame('35202-1234567-1', Identity::cnic('35202 1234567 1'));
        $this->assertSame('+923001234567', Identity::phone('0300-1234567'));
        $this->assertSame('+923001234567', Identity::phone('+92 300 1234567'));
        $this->assertSame('+923001234567', Identity::phone('00923001234567'));
        $this->assertSame('+923001234567', Identity::phone('923001234567'));
    }

    public function test_registration_stores_normalised_cnic_and_phone(): void
    {
        Storage::fake();
        $this->post('/register', $this->registerPayload($this->member(), ['cnic' => '3520276543219', 'phone' => '0301-2345678']))
            ->assertSessionHasNoErrors();

        $user = User::firstWhere('username', 'aliraza');
        $this->assertSame('35202-7654321-9', $user->cnic);
        $this->assertSame('+923012345678', $user->phone);
    }

    public function test_cnic_phone_email_and_username_must_each_be_unique(): void
    {
        Storage::fake();
        $existing = $this->member(['cnic' => '35202-7654321-9', 'phone' => '+923012345678', 'email' => 'taken@example.com', 'username' => 'takenname']);

        // Same CNIC / phone typed in a different format is still a duplicate.
        $this->post('/register', $this->registerPayload($existing, ['cnic' => '3520276543219']))->assertSessionHasErrors('cnic');
        $this->post('/register', $this->registerPayload($existing, ['cnic' => '11111-1111111-1', 'phone' => '+92 301 2345678']))->assertSessionHasErrors('phone');
        $this->post('/register', $this->registerPayload($existing, ['cnic' => '11111-1111111-1', 'phone' => '03009999999', 'email' => 'taken@example.com']))->assertSessionHasErrors('email');
        $this->post('/register', $this->registerPayload($existing, ['cnic' => '11111-1111111-1', 'phone' => '03009999999', 'username' => 'takenname']))->assertSessionHasErrors('username');

        $this->assertSame(1, User::count());
    }

    public function test_cnic_is_required_and_must_be_13_digits(): void
    {
        Storage::fake();
        $sponsor = $this->member();
        $this->post('/register', $this->registerPayload($sponsor, ['cnic' => '']))->assertSessionHasErrors('cnic');
        $this->post('/register', $this->registerPayload($sponsor, ['cnic' => '12345-123']))->assertSessionHasErrors('cnic');
    }

    public function test_referral_code_is_generated_from_username_name_and_cnic(): void
    {
        $user = $this->member(['username' => 'tayyab', 'name' => 'Muhammad Tayyab', 'cnic' => '35202-1234567-1']);
        $this->assertSame('TAYYMT5671', $user->referral_code);

        // Same ingredients again -> still unique, with a random suffix.
        $twin = $this->member(['username' => 'tayyab2', 'name' => 'Muhammad Tayyab', 'cnic' => '35202-9995671-1']);
        $this->assertStringStartsWith('TAYYMT', $twin->referral_code);
        $this->assertNotSame($user->referral_code, $twin->referral_code);
    }

    public function test_members_cannot_set_their_own_referral_code(): void
    {
        Storage::fake();
        $this->post('/register', $this->registerPayload($this->member(), ['referral_code' => 'MYCODE']))->assertSessionHasNoErrors();

        $this->assertNotSame('MYCODE', User::firstWhere('username', 'aliraza')->referral_code);
    }

    /* ---------- Profile picture ---------- */

    public function test_member_uploads_and_removes_profile_picture(): void
    {
        Storage::fake();
        $member = $this->member();
        $this->assertStringContainsString('default-avatar.svg', $member->avatarUrl());

        $this->actingAs($member)->post(route('profile.avatar'), ['avatar' => UploadedFile::fake()->image('me.png', 1200, 900)])
            ->assertSessionHasNoErrors();

        $path = $member->fresh()->avatar_path;
        Storage::assertExists($path);
        [$w, $h] = getimagesizefromstring(Storage::get($path));
        $this->assertSame([320, 320], [$w, $h], 'Cropped to a 320px square');
        $this->actingAs($member)->get(route('avatars.show', $member))->assertOk();

        $this->actingAs($member)->delete(route('profile.avatar.destroy'));
        $this->assertNull($member->fresh()->avatar_path);
        Storage::assertMissing($path);
    }

    /* ---------- Withdrawals ---------- */

    public function test_withdrawal_saves_payout_details_and_admin_sees_full_detail_page(): void
    {
        $member = $this->member(['cnic' => '35202-5555555-5']);
        $member->forceFill(['status' => 'active', 'wallet_balance' => 80])->save();

        $this->actingAs($member)->post(route('withdrawals.store'), [
            'amount' => 50, 'method' => 'Bank Transfer', 'bank_name' => 'Meezan Bank',
            'account_title' => 'Ali Raza', 'account_number' => 'PK36MEZN0000001234567890', 'save_details' => '1',
        ])->assertSessionHasNoErrors();

        $withdrawal = Withdrawal::sole();
        $this->assertSame('Meezan Bank', $withdrawal->bank_name);
        $this->assertSame('PK36MEZN0000001234567890', $member->fresh()->account_number, 'Saved as default for next time');

        $this->actingAs($this->staff(['withdrawals.view']))
            ->get(route('admin.withdrawals.show', $withdrawal))
            ->assertOk()
            ->assertSee('PK36MEZN0000001234567890')
            ->assertSee('Meezan Bank')
            ->assertSee('35202-5555555-5')
            ->assertSee($member->phone)
            ->assertSee($member->email)
            ->assertDontSee('Approve &amp; mark paid', false); // view-only staff cannot act

        $this->actingAs($this->superAdmin())->get(route('admin.withdrawals.show', $withdrawal))->assertSee('Approve');
    }

    public function test_withdrawal_requires_account_title_and_number(): void
    {
        $member = $this->member();
        $member->forceFill(['wallet_balance' => 80])->save();

        $this->actingAs($member)->post(route('withdrawals.store'), ['amount' => 50, 'method' => 'Easypaisa'])
            ->assertSessionHasErrors(['account_title', 'account_number']);
    }

    /* ---------- Commission only for active, fully-paid members ---------- */

    public function test_commission_waits_while_member_is_blocked_and_releases_on_activation(): void
    {
        $sponsor = $this->member();
        $sponsor->forceFill(['status' => 'active', 'paid_amount' => 200])->save();
        $member = $this->member(sponsor: $sponsor);
        $member->forceFill(['status' => 'blocked'])->save();
        $payment = Payment::create(['user_id' => $member->id, 'paid_by' => $member->id, 'amount' => 200, 'method' => 'proof']);
        $admin = $this->superAdmin();

        app(PaymentService::class)->approve($payment, $admin);
        $this->assertEquals(200, $member->fresh()->paid_amount);
        $this->assertEquals(0, $sponsor->fresh()->wallet_balance, 'No commission while blocked');

        $this->actingAs($admin)->patch(route('admin.members.status', $member), ['status' => 'active'])
            ->assertSessionHas('success');
        $this->assertEquals(30, $sponsor->fresh()->wallet_balance, 'Released when activated');

        // Toggling status again never pays twice.
        $this->actingAs($admin)->patch(route('admin.members.status', $member), ['status' => 'blocked']);
        $this->actingAs($admin)->patch(route('admin.members.status', $member), ['status' => 'active']);
        $this->assertEquals(30, $sponsor->fresh()->wallet_balance);
    }

    public function test_activating_a_partially_paid_member_pays_no_commission(): void
    {
        $sponsor = $this->member();
        $sponsor->forceFill(['status' => 'active', 'paid_amount' => 200])->save();
        $member = $this->member(sponsor: $sponsor);
        $member->forceFill(['paid_amount' => 25])->save();

        $this->actingAs($this->superAdmin())->patch(route('admin.members.status', $member), ['status' => 'active']);

        $this->assertEquals(0, $sponsor->fresh()->wallet_balance);
    }

    /* ---------- Referral link copy button ---------- */

    public function test_member_sees_referral_copy_button_on_every_page(): void
    {
        $member = $this->member();
        foreach (['dashboard', 'team.index', 'wallet.index', 'payments.index'] as $route) {
            $this->actingAs($member)->get(route($route))->assertSee('Copy referral link')->assertSee($member->referralLink());
        }

        $this->actingAs($this->superAdmin())->get(route('dashboard'))->assertDontSee('Copy referral link');
    }
}
