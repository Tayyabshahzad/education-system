<?php

namespace App\Http\Requests;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Shared validation for every form that creates a member account. */
class MemberDetailsRules
{
    public static function rules(?User $ignore = null, bool $passwordRequired = true): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:4', 'max:30', 'alpha_dash', Rule::unique('users')->ignore($ignore)],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($ignore)],
            'phone' => ['required', 'string', 'max:30'],
            'country' => ['required', 'string', 'max:80'],
            'joining_amount' => $ignore ? ['sometimes'] : ['required', 'numeric', 'min:'.Setting::money('min_join_fee'), 'max:'.Setting::money('max_fee')],
            'password' => $passwordRequired ? ['required', 'confirmed', Password::defaults()] : ['nullable', 'confirmed', Password::defaults()],
        ];
    }
}
