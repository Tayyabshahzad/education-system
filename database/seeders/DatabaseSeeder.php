<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        // Demo accounts are for local testing only.
        if (app()->environment('local', 'testing')) {
            $this->call(DemoSeeder::class);
        }
    }
}
