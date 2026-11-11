<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Renders every page with the demo data to catch view errors. */
class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    private function user(string $username): User
    {
        return User::firstWhere('username', $username);
    }

    public function test_member_pages_render(): void
    {
        foreach (['member1', 'member3'] as $username) {
            $member = $this->user($username);
            foreach (['dashboard', 'team.index', 'team.tree', 'team.create', 'payments.index', 'wallet.index', 'withdrawals.index', 'classes.index', 'profile.edit'] as $route) {
                $this->actingAs($member)->get(route($route))->assertOk();
            }
        }
    }

    public function test_admin_pages_render(): void
    {
        $admin = $this->user('superadmin');
        $member = $this->user('member1');
        $batch = Batch::first();
        $teacher = $this->user('teacher');
        $staff = $this->user('staff');

        $urls = [
            route('dashboard'),
            route('admin.members.index'),
            route('admin.members.index', ['status' => 'active', 'paid' => 'full', 'q' => 'member']),
            route('admin.users.create'),
            route('admin.users.create', ['role' => 'member', 'sponsor' => $member->id]),
            route('admin.users.create', ['role' => 'staff']),
            route('admin.users.create', ['role' => 'teacher']),
            route('admin.members.show', $member),
            route('admin.members.edit', $member),
            route('admin.genealogy'),
            route('admin.genealogy', $member),
            route('admin.payments.index', ['status' => 'all']),
            route('admin.withdrawals.index'),
            route('admin.batches.index'),
            route('admin.batches.create'),
            route('admin.batches.show', $batch),
            route('admin.batches.edit', $batch),
            route('admin.teachers.index'),
            route('admin.teachers.edit', $teacher),
            route('admin.staff.index'),
            route('admin.staff.edit', $staff),
            route('admin.settings.edit'),
        ];

        foreach ($urls as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        $this->actingAs($staff)->get(route('dashboard'))->assertOk();
    }

    public function test_teacher_pages_render(): void
    {
        $teacher = $this->user('teacher');

        $this->actingAs($teacher)->get(route('dashboard'))->assertOk()->assertSee('Forex Foundations');
        $this->actingAs($teacher)->get(route('teacher.batches.show', Batch::first()))->assertOk()->assertSee('Member One');
    }
}
