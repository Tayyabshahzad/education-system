<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Super admin passes every permission check.
        Gate::before(fn (User $user) => $user->hasRole(User::ROLE_SUPER_ADMIN) ? true : null);

        Paginator::defaultView('components.pagination');
    }
}
