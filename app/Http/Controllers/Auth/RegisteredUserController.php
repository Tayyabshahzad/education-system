<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\MemberDetailsRules;
use App\Models\Setting;
use App\Models\User;
use App\Services\MemberService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        $refCode = strtoupper((string) $request->query('ref', old('sponsor_code', '')));
        $sponsor = $refCode ? User::role(User::ROLE_MEMBER)->where('referral_code', $refCode)->first() : null;

        return view('auth.register', [
            'sponsor' => $sponsor,
            'refCode' => $refCode,
            'minFee' => Setting::money('min_join_fee'),
            'fullFee' => Setting::money('full_fee'),
            'maxFee' => Setting::money('max_fee'),
        ]);
    }

    public function store(Request $request, MemberService $members): RedirectResponse
    {
        $request->merge(['sponsor_code' => strtoupper(trim((string) $request->sponsor_code))]);

        $validated = $request->validate([
            'sponsor_code' => ['nullable', 'string', Rule::exists('users', 'referral_code')->whereNull('deleted_at')],
            'terms' => ['accepted'],
            ...MemberDetailsRules::rules(),
        ], [
            'sponsor_code.exists' => 'This referral code is not valid.',
        ]);

        $sponsor = $validated['sponsor_code']
            ? User::role(User::ROLE_MEMBER)->where('referral_code', $validated['sponsor_code'])->first()
            : null;

        if ($validated['sponsor_code'] && ! $sponsor) {
            return back()->withInput()->withErrors(['sponsor_code' => 'This referral code is not valid.']);
        }

        $user = $members->create($validated, $sponsor);

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('payments.index')
            ->with('success', 'Account created. Submit your payment of $'.number_format((float) $user->joining_amount, 2).' to activate it.');
    }
}
