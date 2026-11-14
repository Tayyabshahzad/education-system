<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| First super admin on a fresh server (demo accounts are only seeded locally):
|   php artisan app:create-super-admin
| Asks for name, email and password; the username and referral code are generated.
*/
Artisan::command('app:create-super-admin {--name=} {--email=} {--password=}', function () {
    $data = [
        'name' => $this->option('name') ?: $this->ask('Full name'),
        'email' => strtolower(trim((string) ($this->option('email') ?: $this->ask('Email')))),
        'password' => $this->option('password') ?: $this->secret('Password (min 8 characters)'),
    ];

    $validator = Validator::make($data, [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        'password' => ['required', Password::defaults()],
    ]);
    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }

    $user = DB::transaction(function () use ($data) {
        $user = User::create($data);
        $user->forceFill(['status' => 'active', 'email_verified_at' => now()])->save();
        $user->assignRole(User::ROLE_SUPER_ADMIN);

        return $user;
    });

    $this->info("Super admin created. Log in with {$user->email} (username: {$user->username}, referral code: {$user->referral_code}).");

    return 0;
})->purpose('Create a super admin account (use once on a fresh server)');
