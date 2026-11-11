<?php

namespace Tests\Feature\Auth;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private User $sponsor;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->sponsor = $this->member();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'sponsor_code' => $this->sponsor->referral_code,
            'joining_amount' => 200,
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
            'proof' => UploadedFile::fake()->image('receipt.png', 1800, 3200),
            'reference' => 'TX-77',
        ], $overrides);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get('/register')->assertOk()->assertSee('Choose your amount')->assertSee('Payment screenshot');
    }

    public function test_registration_creates_pending_member_with_pending_payment(): void
    {
        $response = $this->post('/register', $this->payload());

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));

        $user = User::firstWhere('email', 'test@example.com');
        $this->assertTrue($user->isMember());
        $this->assertSame('pending', $user->status);
        $this->assertSame($this->sponsor->id, $user->sponsor_id);
        $this->assertEquals(0, $user->paid_amount);

        $payment = Payment::sole();
        $this->assertSame($user->id, $payment->user_id);
        $this->assertEquals(200, $payment->amount);
        $this->assertSame('pending', $payment->status);
        $this->assertSame('TX-77', $payment->reference);
        Storage::assertExists($payment->proof_path);
    }

    public function test_referral_code_is_required(): void
    {
        $this->post('/register', $this->payload(['sponsor_code' => '']))->assertSessionHasErrors('sponsor_code');
        $this->post('/register', $this->payload(['sponsor_code' => 'NOPE123']))->assertSessionHasErrors('sponsor_code');

        $this->assertGuest();
        $this->assertSame(1, User::count());
    }

    public function test_referral_code_is_case_insensitive_and_link_prefills_sponsor(): void
    {
        $this->get('/register?ref='.$this->sponsor->referral_code)->assertSee($this->sponsor->name);

        $this->post('/register', $this->payload(['sponsor_code' => strtolower($this->sponsor->referral_code)]))
            ->assertSessionHasNoErrors();
    }

    public function test_payment_screenshot_is_required_and_must_be_an_image(): void
    {
        $this->post('/register', $this->payload(['proof' => null]))->assertSessionHasErrors('proof');
        $this->post('/register', $this->payload(['proof' => UploadedFile::fake()->create('r.pdf', 100, 'application/pdf')]))
            ->assertSessionHasErrors('proof');

        $this->assertGuest();
        $this->assertSame(0, Payment::count());
    }

    public function test_staff_referral_code_cannot_be_used_as_sponsor(): void
    {
        $staff = $this->staff();

        $this->post('/register', $this->payload(['sponsor_code' => $staff->referral_code]))
            ->assertSessionHasErrors('sponsor_code');
    }

    public function test_amount_outside_min_and_max_is_rejected(): void
    {
        $this->post('/register', $this->payload(['joining_amount' => 10]))->assertSessionHasErrors('joining_amount');
        $this->post('/register', $this->payload(['joining_amount' => 500]))->assertSessionHasErrors('joining_amount');
    }
}
