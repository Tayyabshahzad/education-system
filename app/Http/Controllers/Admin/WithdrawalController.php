<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WithdrawalController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');

        return view('admin.withdrawals', [
            'withdrawals' => Withdrawal::with(['user:id,name,username,phone', 'reviewer:id,name'])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'status' => $status,
            'counts' => Withdrawal::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function approve(Request $request, Withdrawal $withdrawal, WithdrawalService $withdrawals): RedirectResponse
    {
        $withdrawals->approve($withdrawal, $request->user(), $request->input('note'));

        return back()->with('success', 'Withdrawal marked as paid.');
    }

    public function reject(Request $request, Withdrawal $withdrawal, WithdrawalService $withdrawals): RedirectResponse
    {
        $request->validate(['note' => ['required', 'string', 'max:255']], ['note.required' => 'Please give a reason for rejecting.']);
        $withdrawals->reject($withdrawal, $request->user(), $request->input('note'));

        return back()->with('success', 'Withdrawal rejected and amount returned to the member wallet.');
    }
}
