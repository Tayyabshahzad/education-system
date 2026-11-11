<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Normalises identity fields before validation so "35202 1234567 1", "3520212345671" and
 * "35202-1234567-1" are the same CNIC, and "0300-1234567" / "+92 300 1234567" the same phone.
 * Uniqueness rules then compare like with like.
 */
class Identity
{
    /** 13 digits -> 12345-1234567-1. Anything else is returned trimmed (and fails validation). */
    public static function cnic(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);
        if ($digits === '') {
            return null;
        }

        return strlen($digits) === 13
            ? substr($digits, 0, 5).'-'.substr($digits, 5, 7).'-'.substr($digits, 12, 1)
            : trim((string) $value);
    }

    /** Strips spaces/dashes; Pakistani 03xxxxxxxxx and 923xxxxxxxxx become +923xxxxxxxxx. */
    public static function phone(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $plus = str_starts_with($value, '+') || str_starts_with($value, '00');
        $digits = preg_replace('/\D/', '', $value);
        if (str_starts_with($value, '00')) {
            $digits = substr($digits, 2);
        }

        if (! $plus && strlen($digits) === 11 && str_starts_with($digits, '03')) {
            return '+92'.substr($digits, 1);
        }
        if (! $plus && strlen($digits) === 12 && str_starts_with($digits, '923')) {
            return '+'.$digits;
        }

        return ($plus ? '+' : '').$digits;
    }

    /** Apply both normalisers to an incoming request, in place. */
    public static function normalize(Request $request): void
    {
        $changes = [];
        if ($request->has('cnic')) {
            $changes['cnic'] = static::cnic($request->input('cnic'));
        }
        if ($request->has('phone')) {
            $changes['phone'] = static::phone($request->input('phone'));
        }
        $request->merge($changes);
    }
}
