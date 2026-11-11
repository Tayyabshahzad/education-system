<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        if ($user->isStaffSide()) {
            return $this->admin();
        }

        if ($user->hasRole(User::ROLE_TEACHER)) {
            return view('teacher.dashboard', [
                'batches' => $user->teachingBatches()->withCount('members')->orderBy('start_date')->get(),
            ]);
        }

        return $this->member($user);
    }

    private function member(User $user): View
    {
        $levels = $user->downlineByLevel(3);
        $commissions = $user->walletTransactions()->where('type', 'commission');

        return view('member.dashboard', [
            'user' => $user,
            'levels' => $levels,
            'totalEarned' => (float) (clone $commissions)->sum('amount'),
            'earningsByLevel' => (clone $commissions)->selectRaw('level, SUM(amount) as total')->groupBy('level')->pluck('total', 'level'),
            'withdrawn' => (float) $user->withdrawals()->where('status', 'approved')->sum('amount'),
            'pendingWithdrawal' => (float) $user->withdrawals()->where('status', 'pending')->sum('amount'),
            'pendingPayment' => $user->payments()->where('status', 'pending')->latest()->first(),
            'recent' => $user->walletTransactions()->with('fromUser:id,username')->latest()->limit(6)->get(),
            'batches' => $user->batches()->with('teacher:id,name')->get(),
            'fullFee' => Setting::money('full_fee'),
            'commissionLevels' => Setting::commissionLevels(),
        ]);
    }

    private function admin(): View
    {
        $members = User::role(User::ROLE_MEMBER);

        return view('admin.dashboard', [
            'stats' => [
                'members' => (clone $members)->count(),
                'active' => (clone $members)->where('status', 'active')->count(),
                'fullyPaid' => (clone $members)->fullyPaid()->count(),
                'pendingMembers' => (clone $members)->where('status', 'pending')->count(),
                'collected' => (float) Payment::where('status', 'approved')->sum('amount'),
                'commissionPaid' => (float) WalletTransaction::where('type', 'commission')->sum('amount'),
                'walletLiability' => (float) User::sum('wallet_balance'),
                'withdrawn' => (float) Withdrawal::where('status', 'approved')->sum('amount'),
                'batches' => Batch::count(),
            ],
            'pendingPayments' => Payment::where('status', 'pending')->with('user:id,name,username')->latest()->limit(6)->get(),
            'pendingPaymentsCount' => Payment::where('status', 'pending')->count(),
            'pendingWithdrawals' => Withdrawal::where('status', 'pending')->with('user:id,name,username')->latest()->limit(6)->get(),
            'pendingWithdrawalsCount' => Withdrawal::where('status', 'pending')->count(),
            'latestMembers' => (clone $members)->with('sponsor:id,username')->latest()->limit(6)->get(),
        ]);
    }
}
