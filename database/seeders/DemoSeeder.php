<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\User;
use App\Services\MemberService;
use App\Services\WithdrawalService;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

/**
 * Local test data. Every account uses the password "password".
 *
 *   admin@gve.test    super admin (full access)
 *   staff@gve.test    staff (default permissions)
 *   teacher@gve.test  teacher (teaches the demo batch)
 *   member1@gve.test  top-level member, $200 paid
 *   member2@gve.test  under member1, $200 paid  -> member1 earned $30
 *   member3@gve.test  under member2, $25 paid   -> nobody earns until member3 tops up to $200
 */
class DemoSeeder extends Seeder
{
    public function run(MemberService $members, WithdrawalService $withdrawals): void
    {
        $admin = $this->staffUser('Super Admin', 'superadmin', 'admin@gve.test', User::ROLE_SUPER_ADMIN);

        $staff = $this->staffUser('Sara Staff', 'staff', 'staff@gve.test', User::ROLE_STAFF);
        $staff->syncPermissions(Permissions::STAFF_DEFAULTS);

        $teacher = $this->staffUser('Usman Mentor', 'teacher', 'teacher@gve.test', User::ROLE_TEACHER);

        $member1 = $members->create($this->details('Member One', 'member1', 200, 1), null, $admin, 'received', 200);
        $member2 = $members->create($this->details('Member Two', 'member2', 200, 2), $member1, $admin, 'received', 200);
        $member3 = $members->create($this->details('Member Three', 'member3', 25, 3), $member2, $admin, 'received', 25);

        $member1->update([
            'payout_method' => 'Easypaisa',
            'bank_name' => 'Easypaisa',
            'account_title' => 'Member One',
            'account_number' => '03001000001',
        ]);

        $batch = Batch::create([
            'name' => 'Forex Foundations — Batch 01',
            'teacher_id' => $teacher->id,
            'start_date' => now()->next('Monday'),
            'days' => 'Mon, Wed, Fri',
            'class_time' => '20:00',
            'duration_minutes' => 90,
            'description' => 'Market structure, support & resistance, and risk management basics.',
            'status' => 'upcoming',
            'created_by' => $admin->id,
        ]);
        $batch->members()->attach([$member1->id => ['assigned_by' => $admin->id], $member2->id => ['assigned_by' => $admin->id]]);

        // A pending withdrawal so the admin queue has something to review.
        $withdrawals->request($member1->fresh(), 10, [
            'method' => 'Easypaisa',
            'bank_name' => 'Easypaisa',
            'account_title' => 'Member One',
            'account_number' => '03001000001',
        ]);
    }

    private function staffUser(string $name, string $username, string $email, string $role): User
    {
        $user = User::create([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'phone' => '+92300'.random_int(1000000, 9999999),
            'password' => 'password',
        ]);
        $user->forceFill(['status' => 'active', 'email_verified_at' => now()])->save();
        $user->assignRole($role);

        return $user;
    }

    private function details(string $name, string $username, float $amount, int $n): array
    {
        return [
            'name' => $name,
            'username' => $username,
            'email' => $username.'@gve.test',
            'phone' => '+92300100000'.$n,
            'cnic' => '35202-123456'.$n.'-'.$n,
            'country' => 'Pakistan',
            'password' => 'password',
            'joining_amount' => $amount,
        ];
    }
}
