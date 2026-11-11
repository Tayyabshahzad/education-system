<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayoutRules;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'methods' => Setting::withdrawalMethods(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /** Member's default bank / wallet details, pre-filled on every withdrawal request. */
    public function updatePayout(Request $request): RedirectResponse
    {
        $data = $request->validate(PayoutRules::rules());

        $request->user()->update([
            'payout_method' => $data['method'],
            'bank_name' => $data['bank_name'] ?? null,
            'account_title' => $data['account_title'],
            'account_number' => $data['account_number'],
        ]);

        return Redirect::route('profile.edit')->with('success', 'Payout details saved.');
    }
}
