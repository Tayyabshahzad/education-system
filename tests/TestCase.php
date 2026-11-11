<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Roles & permissions must exist for every test that uses RefreshDatabase. */
    protected bool $seed = true;

    protected string $seeder = RolesAndPermissionsSeeder::class;

    protected function member(array $attributes = [], ?User $sponsor = null): User
    {
        return User::factory()->member()->create(array_merge(['sponsor_id' => $sponsor?->id], $attributes));
    }

    protected function superAdmin(): User
    {
        return User::factory()->withRole(User::ROLE_SUPER_ADMIN)->create();
    }

    protected function staff(array $permissions = []): User
    {
        $user = User::factory()->withRole(User::ROLE_STAFF)->create();
        $user->givePermissionTo($permissions);

        return $user;
    }
}
