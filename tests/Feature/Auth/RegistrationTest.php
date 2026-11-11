<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'joining_amount' => 200,
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
        ], $overrides);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get('/register')->assertOk()->assertSee('Choose your amount');
    }

    public function test_new_users_can_register_as_pending_members(): void
    {
        $response = $this->post('/register', $this->payload());

        $this->assertAuthenticated();
        $response->assertRedirect(route('payments.index'));

        $user = User::firstWhere('email', 'test@example.com');
        $this->assertTrue($user->isMember());
        $this->assertSame('pending', $user->status);
        $this->assertEquals(200, $user->joining_amount);
        $this->assertEquals(0, $user->paid_amount);
        $this->assertStringStartsWith('GVE', $user->referral_code);
        $this->assertNull($user->sponsor_id);
    }

    public function test_user_registered_via_referral_code_is_linked_to_sponsor(): void
    {
        $sponsor = $this->member();

        $this->get('/register?ref='.$sponsor->referral_code)->assertSee($sponsor->name);

        $this->post('/register', $this->payload([
            'joining_amount' => 25,
            'sponsor_code' => strtolower($sponsor->referral_code),
        ]));

        $user = User::firstWhere('email', 'test@example.com');
        $this->assertSame($sponsor->id, $user->sponsor_id);
        $this->assertEquals(25, $user->joining_amount);
    }

    public function test_invalid_referral_code_is_rejected(): void
    {
        $this->post('/register', $this->payload(['sponsor_code' => 'NOPE123']))
            ->assertSessionHasErrors('sponsor_code');

        $this->assertGuest();
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
