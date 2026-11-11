<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        $refCode = $request->query('ref', old('sponsor_code'));
        $sponsor = $refCode ? User::where('referral_code', $refCode)->first() : null;

        return view('auth.register', [
            'packages' => config('education.packages'),
            'sponsor' => $sponsor,
            'refCode' => $refCode,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['sponsor_code' => strtoupper(trim((string) $request->sponsor_code))]);

        $validated = $request->validate([
            'sponsor_code' => ['nullable', 'string', Rule::exists('users', 'referral_code')],
            'package' => ['required', Rule::in(array_keys(config('education.packages')))],
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:4', 'max:30', 'alpha_dash', 'unique:'.User::class],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['required', 'string', 'max:30'],
            'country' => ['required', 'string', 'max:80'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms' => ['accepted'],
        ], [
            'sponsor_code.exists' => 'This referral code is not valid.',
        ]);

        $sponsorId = $validated['sponsor_code']
            ? User::where('referral_code', $validated['sponsor_code'])->value('id')
            : null;

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'country' => $validated['country'],
            'sponsor_id' => $sponsorId,
            'package' => $validated['package'],
            'package_amount' => config("education.packages.{$validated['package']}.amount"),
            'password' => $validated['password'],
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
