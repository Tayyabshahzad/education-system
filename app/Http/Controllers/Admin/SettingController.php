<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings', ['settings' => Setting::values()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'min_join_fee' => ['required', 'numeric', 'min:1'],
            'full_fee' => ['required', 'numeric', 'gte:min_join_fee'],
            'max_fee' => ['required', 'numeric', 'gte:full_fee'],
            ...$this->commissionRules(),
            'min_withdrawal' => ['required', 'numeric', 'min:1'],
            'commission_fee_share' => ['required', 'numeric', 'min:0', 'max:100'],
            'withdrawal_methods' => ['required', 'string', 'max:500'],
            'payment_instructions' => ['nullable', 'string', 'max:2000'],
        ], [
            'full_fee.gte' => 'Full fee cannot be lower than the minimum joining fee.',
            'max_fee.gte' => 'Maximum fee cannot be lower than the full fee.',
        ]);

        $data['upline_must_be_full'] = $request->boolean('upline_must_be_full');

        $totalPercent = collect(array_keys($this->commissionRules()))->sum(fn ($key) => (float) $data[$key]);
        if ($totalPercent > 100) {
            return back()->withInput()->withErrors(['commission_level_1' => 'Total commission of all levels ('.$totalPercent.'%) cannot be more than 100%.']);
        }

        Setting::setMany($data);

        return back()->with('success', 'Settings saved. New values apply to payments approved from now on.');
    }

    /** One required percentage per commission level (commission_level_1 … commission_level_N). */
    private function commissionRules(): array
    {
        $rules = [];
        foreach (array_keys(Setting::commissionLevels()) as $level) {
            $rules["commission_level_{$level}"] = ['required', 'numeric', 'min:0', 'max:100'];
        }

        return $rules;
    }
}
