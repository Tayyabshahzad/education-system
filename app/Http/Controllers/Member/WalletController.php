<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayoutRules;
use App\Models\Setting;
use App\Services\WithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WalletController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $type = $request->query('type');

        return view('member.wallet', [
            'user' => $user,
            'transactions' => $user->walletTransactions()
                ->when($type, fn ($q) => $q->where('type', $type))
                ->with('fromUser:id,username')
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'type' => $type,
            'totalEarned' => (float) $user->walletTransactions()->where('type', 'commission')->sum('amount'),
        ]);
    }

    public function withdrawals(Request $request): View
    {
        $user = $request->user();

        return view('member.withdrawals', [
            'user' => $user,
            'withdrawals' => $user->withdrawals()->latest()->paginate(10),
            'methods' => Setting::withdrawalMethods(),
            'minWithdrawal' => Setting::money('min_withdrawal'),
        ]);
    }

    public function requestWithdrawal(Request $request, WithdrawalService $withdrawals): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            ...PayoutRules::rules(),
            'save_details' => ['boolean'],
        ]);

        $user = $request->user();
        $withdrawals->request($user, round((float) $data['amount'], 2), $data);

        // Remember these details for next time (on by default).
        if ($request->boolean('save_details', true)) {
            $user->update([
                'payout_method' => $data['method'],
                'bank_name' => $data['bank_name'] ?? null,
                'account_title' => $data['account_title'],
                'account_number' => $data['account_number'],
            ]);
        }

        return back()->with('success', 'Withdrawal request submitted. The amount is on hold until it is processed.');
    }
}
