<?php

namespace App\Http\Requests;

/** Validation for a payment screenshot upload (images only, compressed after upload). */
class ProofRules
{
    public static function required(): array
    {
        return [
            'proof' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    public static function messages(): array
    {
        return [
            'proof.required' => 'Please attach a screenshot of your payment.',
            'proof.image' => 'The payment proof must be an image (JPG, PNG or WEBP).',
            'proof.mimes' => 'The payment proof must be an image (JPG, PNG or WEBP).',
            'proof.max' => 'The screenshot is too large (max 10 MB).',
        ];
    }
}
