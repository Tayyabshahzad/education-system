<?php

namespace App\Http\Requests;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Shared validation for every form that creates or edits a member account.
 * Email, CNIC and mobile number must each be unique across all accounts.
 * Username and referral code are never entered; the system generates both (see User::booted).
 */
class MemberDetailsRules
{
    public static function rules(?User $ignore = null, bool $passwordRequired = true): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($ignore)],
            // CNIC & phone arrive already normalised by the NormalizeIdentityFields middleware.
            'cnic' => ['required', 'regex:/^\d{5}-\d{7}-\d$/', Rule::unique('users')->ignore($ignore)],
            'phone' => ['required', 'string', 'regex:/^\+?\d{10,15}$/', Rule::unique('users')->ignore($ignore)],
            'country' => ['required', 'string', 'max:80'],
            'joining_amount' => $ignore ? ['sometimes'] : ['required', 'numeric', 'min:'.Setting::money('min_join_fee'), 'max:'.Setting::money('max_fee')],
            'password' => $passwordRequired ? ['required', 'confirmed', Password::defaults()] : ['nullable', 'confirmed', Password::defaults()],
        ];
    }

    public static function messages(): array
    {
        return [
            'cnic.required' => 'Please enter your CNIC number.',
            'cnic.regex' => 'CNIC must be 13 digits, e.g. 35202-1234567-1.',
            'cnic.unique' => 'An account with this CNIC already exists.',
            'phone.regex' => 'Please enter a valid mobile number, e.g. 0300 1234567.',
            'phone.unique' => 'An account with this mobile number already exists.',
            'email.unique' => 'An account with this email already exists.',
        ];
    }
}
