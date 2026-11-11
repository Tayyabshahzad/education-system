<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('member.payments', [
            'user' => $user,
            'payments' => $user->payments()->latest()->paginate(10),
            'remaining' => $user->payableRemaining(),
            'minimum' => $user->minimumNextPayment(),
            'suggested' => (float) $user->paid_amount > 0
                ? min($user->payableRemaining(), max(0, Setting::money('full_fee') - (float) $user->paid_amount)) ?: $user->payableRemaining()
                : (float) $user->joining_amount,
            'fullFee' => Setting::money('full_fee'),
            'maxFee' => Setting::money('max_fee'),
            'instructions' => Setting::get('payment_instructions'),
        ]);
    }

    public function store(Request $request, PaymentService $payments): RedirectResponse
    {
        $data = $request->validate([
            'method' => ['required', Rule::in(['proof', 'wallet'])],
            'amount' => ['required', 'numeric', 'min:1'],
            'proof' => ['required_if:method,proof', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();
        $amount = round((float) $data['amount'], 2);

        if ($data['method'] === 'wallet') {
            $payments->payFromWallet($user, $user, $amount);

            return back()->with('success', '$'.number_format($amount, 2).' paid from your wallet.');
        }

        $payments->submitProof($user, $amount, $request->file('proof'), $data['reference'] ?? null);

        return back()->with('success', 'Payment proof submitted. It will be reviewed shortly.');
    }

    /** Proof files live on the private disk; owners and payment staff can view them. */
    public function proof(Request $request, Payment $payment): StreamedResponse
    {
        $user = $request->user();
        abort_unless($payment->user_id === $user->id || $user->can('payments.view'), 403);
        abort_unless($payment->proof_path && Storage::exists($payment->proof_path), 404);

        return Storage::response($payment->proof_path);
    }
}
