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
            'package' => 'full',
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
        $this->get('/register')->assertStatus(200)->assertSee('Choose your package');
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', $this->payload());

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::firstWhere('email', 'test@example.com');
        $this->assertSame('pending', $user->fresh()->status);
        $this->assertEquals(200, $user->package_amount);
        $this->assertEquals(0, $user->fresh()->paid_amount);
        $this->assertStringStartsWith('GVE', $user->referral_code);
        $this->assertNull($user->sponsor_id);
    }

    public function test_user_registered_via_referral_code_is_linked_to_sponsor(): void
    {
        $sponsor = User::factory()->create();

        $this->get('/register?ref='.$sponsor->referral_code)->assertSee($sponsor->name);

        $this->post('/register', $this->payload([
            'package' => 'basic',
            'sponsor_code' => strtolower($sponsor->referral_code),
        ]));

        $user = User::firstWhere('email', 'test@example.com');
        $this->assertSame($sponsor->id, $user->sponsor_id);
        $this->assertEquals(25, $user->package_amount);
    }

    public function test_invalid_referral_code_is_rejected(): void
    {
        $this->post('/register', $this->payload(['sponsor_code' => 'NOPE123']))
            ->assertSessionHasErrors('sponsor_code');

        $this->assertGuest();
    }

    public function test_unknown_package_is_rejected(): void
    {
        $this->post('/register', $this->payload(['package' => 'vip']))
            ->assertSessionHasErrors('package');
    }
}
