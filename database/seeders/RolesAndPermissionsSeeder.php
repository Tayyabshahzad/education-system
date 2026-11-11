<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Idempotent: safe to run on production after every deploy. */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate(User::ROLE_SUPER_ADMIN, 'web'); // all access via Gate::before
        Role::findOrCreate(User::ROLE_TEACHER, 'web');
        Role::findOrCreate(User::ROLE_MEMBER, 'web');

        // The staff role itself carries no permissions: each staff member is given
        // their own set directly (Admin > Staff), so duties can differ per person.
        Role::findOrCreate(User::ROLE_STAFF, 'web');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
