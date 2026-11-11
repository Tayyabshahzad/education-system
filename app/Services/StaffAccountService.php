<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Creates non-member accounts (super admin, staff, teacher). They never join the referral tree. */
class StaffAccountService
{
    public function create(array $data, string $role, User $creator, array $permissions = []): User
    {
        return DB::transaction(function () use ($data, $role, $creator, $permissions) {
            $user = new User([
                'name' => $data['name'],
                'username' => $data['username'] ?? null, // null = system generates one
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
            ]);
            $user->created_by = $creator->id;
            $user->save();

            $user->forceFill(['status' => 'active'])->save();
            $user->assignRole($role);

            if ($role === User::ROLE_STAFF) {
                $user->syncPermissions($permissions);
            }

            return $user;
        });
    }
}
