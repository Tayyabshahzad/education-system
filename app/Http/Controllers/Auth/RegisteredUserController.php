<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\MemberDetailsRules;
use App\Http\Requests\ProofRules;
use App\Models\Setting;
use App\Models\User;
use App\Services\MemberService;
use App\Services\PaymentService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            'instructions' => Setting::get('payment_instructions'),
        ]);
    }

    /**
     * Every member must sit under a sponsor, so a valid member referral code is required.
     * The payment screenshot is submitted together with the registration.
     */
    public function store(Request $request, MemberService $members, PaymentService $payments): RedirectResponse
    {
        $request->merge(['sponsor_code' => strtoupper(trim((string) $request->sponsor_code))]);

        $validated = $request->validate([
            'sponsor_code' => ['required', 'string'],
            'terms' => ['accepted'],
            ...MemberDetailsRules::rules(),
            ...ProofRules::required(),
        ], [
            'sponsor_code.required' => 'A referral code is required. Ask the person who invited you for their link.',
            ...ProofRules::messages(),
        ]);

        $sponsor = User::role(User::ROLE_MEMBER)->where('referral_code', $validated['sponsor_code'])->first();
        if (! $sponsor) {
            return back()->withInput()->withErrors(['sponsor_code' => 'This referral code is not valid.']);
        }

        $user = DB::transaction(function () use ($members, $payments, $validated, $sponsor, $request) {
            $user = $members->create($validated, $sponsor);
            $payments->submitProof($user, (float) $user->joining_amount, $request->file('proof'), $validated['reference'] ?? null);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('dashboard')
            ->with('success', 'Account created. Your payment screenshot has been submitted and will be reviewed shortly.');
    }
}
