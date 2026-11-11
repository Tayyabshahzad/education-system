<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccessAndTeamTest extends TestCase
{
    use RefreshDatabase;

    private function newMemberData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Person', 'username' => 'newperson', 'email' => 'new@example.com',
            'phone' => '0300 7654321', 'cnic' => '3520276543217', 'country' => 'Pakistan', 'joining_amount' => 200,
            'password' => 'password', 'password_confirmation' => 'password',
        ], $overrides);
    }

    public function test_member_cannot_open_admin_pages(): void
    {
        $this->actingAs($this->member())->get(route('admin.members.index'))->assertForbidden();
        $this->actingAs($this->member())->get(route('admin.settings.edit'))->assertForbidden();
    }

    public function test_staff_only_reaches_sections_they_were_given(): void
    {
        $staff = $this->staff(['members.view']);

        $this->actingAs($staff)->get(route('admin.members.index'))->assertOk();
        $this->actingAs($staff)->get(route('admin.payments.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.settings.edit'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.staff.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('team.index'))->assertForbidden();
    }

    public function test_super_admin_reaches_everything(): void
    {
        $admin = $this->superAdmin();
        foreach (['admin.members.index', 'admin.payments.index', 'admin.withdrawals.index', 'admin.batches.index',
            'admin.teachers.index', 'admin.staff.index', 'admin.settings.edit', 'admin.genealogy'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }

    public function test_super_admin_grants_staff_permissions(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->post(route('admin.users.store'), [
            'role' => User::ROLE_STAFF,
            'name' => 'Ops', 'username' => 'opsuser', 'email' => 'ops@example.com',
            'password' => 'password', 'password_confirmation' => 'password',
            'permissions' => ['payments.view', 'payments.review'],
        ])->assertSessionHasNoErrors();

        $ops = User::firstWhere('email', 'ops@example.com');
        $this->assertTrue($ops->hasRole(User::ROLE_STAFF));
        $this->assertNull($ops->sponsor_id, 'Staff never join the referral tree');
        $this->assertSame('active', $ops->status);
        $this->assertTrue($ops->can('payments.review'));
        $this->assertFalse($ops->can('settings.manage'));
    }

    public function test_admin_creates_teacher_and_super_admin_with_roles(): void
    {
        $admin = $this->superAdmin();
        $this->member(); // a member exists, but staff-side roles never need a sponsor

        foreach ([User::ROLE_TEACHER => 'teach1', User::ROLE_SUPER_ADMIN => 'boss1'] as $role => $username) {
            $this->actingAs($admin)->post(route('admin.users.store'), [
                'role' => $role, 'name' => 'X', 'username' => $username, 'email' => "{$username}@example.com",
                'password' => 'password', 'password_confirmation' => 'password',
            ])->assertSessionHasNoErrors();

            $this->assertTrue(User::firstWhere('email', "{$username}@example.com")->hasRole($role));
        }
    }

    public function test_staff_can_only_create_roles_they_manage(): void
    {
        $staff = $this->staff(['members.create']);

        $this->actingAs($staff)->get(route('admin.users.create'))->assertOk()->assertDontSee('Super Admin');
        $this->actingAs($staff)->post(route('admin.users.store'), [
            'role' => User::ROLE_STAFF, 'name' => 'X', 'username' => 'sneaky', 'email' => 's@example.com',
            'password' => 'password', 'password_confirmation' => 'password',
        ])->assertSessionHasErrors('role');

        $this->actingAs($this->staff(['members.view']))->get(route('admin.users.create'))->assertForbidden();
    }

    public function test_new_member_must_have_a_sponsor_once_the_tree_has_a_root(): void
    {
        $admin = $this->superAdmin();

        // First member ever may be the root.
        $this->actingAs($admin)->post(route('admin.users.store'), ['role' => User::ROLE_MEMBER] + $this->newMemberData(['username' => 'rootuser', 'email' => 'root@example.com']))
            ->assertSessionHasNoErrors();

        // After that every member needs a sponsor.
        $this->actingAs($admin)->post(route('admin.users.store'), ['role' => User::ROLE_MEMBER] + $this->newMemberData())
            ->assertSessionHasErrors('sponsor');
        $this->assertNull(User::firstWhere('email', 'new@example.com'));
    }

    public function test_member_sponsor_cannot_be_cleared_on_edit(): void
    {
        $a = $this->member();
        $b = $this->member(sponsor: $a);

        $this->actingAs($this->superAdmin())->put(route('admin.members.update', $b), [
            'name' => $b->name, 'username' => $b->username, 'email' => $b->email,
            'phone' => $b->phone, 'cnic' => $b->cnic, 'country' => 'PK', 'sponsor' => '',
        ])->assertSessionHasErrors('sponsor');

        $this->assertSame($a->id, $b->fresh()->sponsor_id);
    }

    public function test_blocked_user_is_logged_out(): void
    {
        $member = $this->member();
        $member->forceFill(['status' => 'blocked'])->save();

        $this->actingAs($member)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_member_registers_downline_and_pays_from_wallet(): void
    {
        $sponsor = $this->member();
        $sponsor->forceFill(['status' => 'active', 'paid_amount' => 200, 'wallet_balance' => 250])->save();

        $this->actingAs($sponsor)->post(route('team.store'), $this->newMemberData(['pay_from_wallet' => '1']))
            ->assertSessionHasNoErrors();

        $new = User::firstWhere('email', 'new@example.com');
        $this->assertSame($sponsor->id, $new->sponsor_id);
        $this->assertSame('active', $new->status);
        $this->assertEquals(200, $new->paid_amount);
        // 250 - 200 fee + 30 level-1 commission for the new member
        $this->assertEquals(80, $sponsor->fresh()->wallet_balance);
    }

    public function test_member_registers_downline_with_payment_screenshot(): void
    {
        Storage::fake();
        $sponsor = $this->member();

        // Without wallet payment, a screenshot is required.
        $this->actingAs($sponsor)->post(route('team.store'), $this->newMemberData())->assertSessionHasErrors('proof');
        $this->assertNull(User::firstWhere('email', 'new@example.com'));

        $this->actingAs($sponsor)->post(route('team.store'), $this->newMemberData([
            'proof' => UploadedFile::fake()->image('pay.jpg', 1000, 1600),
        ]))->assertSessionHasNoErrors();

        $new = User::firstWhere('email', 'new@example.com');
        $this->assertSame($sponsor->id, $new->sponsor_id, 'Members can only register people under themselves');
        $this->assertSame('pending', $new->status);

        $payment = Payment::sole();
        $this->assertSame($new->id, $payment->user_id);
        $this->assertSame($sponsor->id, $payment->paid_by);
        $this->assertSame('pending', $payment->status);
    }

    public function test_member_cannot_choose_a_different_sponsor_when_registering(): void
    {
        Storage::fake();
        $sponsor = $this->member();
        $other = $this->member();

        $this->actingAs($sponsor)->post(route('team.store'), $this->newMemberData([
            'sponsor_id' => $other->id, 'sponsor' => $other->username,
            'proof' => UploadedFile::fake()->image('pay.jpg'),
        ]));

        $this->assertSame($sponsor->id, User::firstWhere('email', 'new@example.com')->sponsor_id);
    }

    public function test_member_can_only_view_own_downline_tree(): void
    {
        $a = $this->member();
        $b = $this->member(sponsor: $a);
        $c = $this->member(sponsor: $b);
        $stranger = $this->member();

        $this->actingAs($a)->get(route('team.tree'))->assertOk()->assertSee($b->username)->assertSee($c->username);
        $this->actingAs($a)->get(route('team.tree', $c))->assertOk();
        $this->actingAs($a)->get(route('team.tree', $stranger))->assertForbidden();
        $this->actingAs($b)->get(route('team.tree', $a))->assertForbidden();
    }

    public function test_admin_creates_member_under_any_sponsor_with_payment(): void
    {
        $sponsor = $this->member();
        $sponsor->forceFill(['status' => 'active', 'paid_amount' => 200])->save();

        $this->actingAs($this->superAdmin())->post(route('admin.users.store'), $this->newMemberData([
            'role' => User::ROLE_MEMBER, 'sponsor' => $sponsor->username, 'mark_paid' => '1',
        ]))->assertSessionHasNoErrors();

        $new = User::firstWhere('email', 'new@example.com');
        $this->assertSame($sponsor->id, $new->sponsor_id);
        $this->assertSame('active', $new->status);
        $this->assertEquals(30, $sponsor->fresh()->wallet_balance);
    }

    public function test_sponsor_cannot_be_moved_under_own_downline(): void
    {
        $a = $this->member();
        $b = $this->member(sponsor: $a);
        $admin = $this->superAdmin();

        $this->actingAs($admin)->put(route('admin.members.update', $a), [
            'name' => $a->name, 'username' => $a->username, 'email' => $a->email,
            'phone' => $a->phone, 'cnic' => $a->cnic, 'country' => 'PK', 'sponsor' => $b->username,
        ])->assertSessionHasErrors('sponsor');

        $this->assertNull($a->fresh()->sponsor_id);
    }

    public function test_deleting_member_moves_their_team_up(): void
    {
        $a = $this->member();
        $b = $this->member(sponsor: $a);
        $c = $this->member(sponsor: $b);

        $this->actingAs($this->superAdmin())->delete(route('admin.members.destroy', $b))->assertSessionHasNoErrors();

        $this->assertSoftDeleted($b);
        $this->assertSame($a->id, $c->fresh()->sponsor_id);
    }

    public function test_settings_validate_fee_order(): void
    {
        $admin = $this->superAdmin();
        $base = ['commission_level_1' => 30, 'commission_level_2' => 20, 'commission_level_3' => 10,
            'min_withdrawal' => 10, 'withdrawal_methods' => 'Bank'];

        $this->actingAs($admin)->put(route('admin.settings.update'), $base + ['min_join_fee' => 50, 'full_fee' => 40, 'max_fee' => 100])
            ->assertSessionHasErrors('full_fee');

        $this->actingAs($admin)->put(route('admin.settings.update'), $base + ['min_join_fee' => 30, 'full_fee' => 250, 'max_fee' => 300])
            ->assertSessionHasNoErrors();
        $this->assertEquals(250, Setting::money('full_fee'));
    }

    public function test_only_fully_paid_active_members_can_join_a_batch(): void
    {
        $full = $this->member();
        $full->forceFill(['status' => 'active', 'paid_amount' => 200])->save();
        $partial = $this->member();
        $partial->forceFill(['status' => 'active', 'paid_amount' => 25])->save();
        $batch = Batch::create(['name' => 'B1', 'status' => 'upcoming']);

        $this->actingAs($this->staff(['batches.manage']))
            ->post(route('admin.batches.assign', $batch), ['members' => [$full->id, $partial->id]]);

        $this->assertSame([$full->id], $batch->members()->pluck('users.id')->all());
    }

    public function test_teacher_sees_only_their_own_batches(): void
    {
        $teacher = User::factory()->withRole(User::ROLE_TEACHER)->create();
        $other = User::factory()->withRole(User::ROLE_TEACHER)->create();
        $mine = Batch::create(['name' => 'Mine', 'teacher_id' => $teacher->id, 'status' => 'running']);
        $theirs = Batch::create(['name' => 'Theirs', 'teacher_id' => $other->id, 'status' => 'running']);

        $this->actingAs($teacher)->get(route('dashboard'))->assertSee('Mine')->assertDontSee('Theirs');
        $this->actingAs($teacher)->get(route('teacher.batches.show', $mine))->assertOk();
        $this->actingAs($teacher)->get(route('teacher.batches.show', $theirs))->assertForbidden();
    }
}
