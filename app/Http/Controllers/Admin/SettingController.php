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
            'commission_level_1' => ['required', 'numeric', 'min:0'],
            'commission_level_2' => ['required', 'numeric', 'min:0'],
            'commission_level_3' => ['required', 'numeric', 'min:0'],
            'min_withdrawal' => ['required', 'numeric', 'min:1'],
            'withdrawal_methods' => ['required', 'string', 'max:500'],
            'payment_instructions' => ['nullable', 'string', 'max:2000'],
        ], [
            'full_fee.gte' => 'Full fee cannot be lower than the minimum joining fee.',
            'max_fee.gte' => 'Maximum fee cannot be lower than the full fee.',
        ]);

        $data['upline_must_be_full'] = $request->boolean('upline_must_be_full');

        $totalCommission = $data['commission_level_1'] + $data['commission_level_2'] + $data['commission_level_3'];
        if ($totalCommission > $data['full_fee']) {
            return back()->withInput()->withErrors(['commission_level_1' => 'Total commission ($'.$totalCommission.') cannot exceed the full fee.']);
        }

        Setting::setMany($data);

        return back()->with('success', 'Settings saved. New values apply to payments approved from now on.');
    }
}
