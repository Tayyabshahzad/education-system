<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');

        return view('admin.payments', [
            'payments' => Payment::with(['user:id,name,username,paid_amount', 'payer:id,username', 'reviewer:id,name'])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'status' => $status,
            'counts' => Payment::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function approve(Request $request, Payment $payment, PaymentService $payments): RedirectResponse
    {
        $payments->approve($payment, $request->user(), $request->input('note'));

        return back()->with('success', 'Payment approved for '.$payment->user->username.'.');
    }

    public function reject(Request $request, Payment $payment, PaymentService $payments): RedirectResponse
    {
        $request->validate(['note' => ['required', 'string', 'max:255']], ['note.required' => 'Please give a reason for rejecting.']);
        $payments->reject($payment, $request->user(), $request->input('note'));

        return back()->with('success', 'Payment rejected.');
    }
}
