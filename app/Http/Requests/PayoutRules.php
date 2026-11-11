<?php

namespace App\Http\Requests;

use App\Models\Setting;
use Illuminate\Validation\Rule;

/** Bank / wallet details a member is paid out to. */
class PayoutRules
{
    public static function rules(bool $required = true): array
    {
        $req = $required ? 'required' : 'nullable';

        return [
            'method' => [$req, Rule::in(Setting::withdrawalMethods())],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'account_title' => [$req, 'string', 'max:100'],
            'account_number' => [$req, 'string', 'max:100'],
            'account_details' => ['nullable', 'string', 'max:500'],
        ];
    }
}
