<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\MemberDetailsRules;
use App\Http\Requests\ProofRules;
use App\Models\Setting;
use App\Models\User;
use App\Services\GenealogyTree;
use App\Services\MemberService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('member.team.index', [
            'levels' => $user->downlineByLevel(3),
            'commissionLevels' => Setting::commissionLevels(),
            'fullFee' => Setting::money('full_fee'),
        ]);
    }

    public function tree(Request $request, GenealogyTree $tree, ?User $root = null): View
    {
        $user = $request->user();
        $root ??= $user;

        abort_unless($root->is($user) || $user->hasInDownline($root), 403);

        return view('genealogy.tree', [
            'tree' => $tree->build($root),
            'root' => $root,
            'home' => $user,
            'routeName' => 'team.tree',
            'canGoUp' => ! $root->is($user) && $root->sponsor_id,
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        return view('member.team.create', [
            'minFee' => Setting::money('min_join_fee'),
            'fullFee' => Setting::money('full_fee'),
            'maxFee' => Setting::money('max_fee'),
            'wallet' => (float) $user->wallet_balance,
        ]);
    }

    /**
     * A member can only register people directly under themselves. The fee is either
     * paid from the sponsor's wallet (instant) or a payment screenshot is attached (reviewed).
     */
    public function store(Request $request, MemberService $members, PaymentService $payments): RedirectResponse
    {
        $sponsor = $request->user();
        $payNow = $request->boolean('pay_from_wallet');

        $validated = $request->validate([
            ...MemberDetailsRules::rules(),
            'pay_from_wallet' => ['boolean'],
            ...($payNow ? [] : ProofRules::required()),
        ], ProofRules::messages());

        if ($payNow && (float) $sponsor->wallet_balance < (float) $validated['joining_amount']) {
            return back()->withInput()->withErrors(['pay_from_wallet' => 'Your wallet balance is not enough to pay this fee.']);
        }

        $member = DB::transaction(function () use ($members, $payments, $validated, $sponsor, $payNow, $request) {
            $member = $members->create($validated, $sponsor, $sponsor, $payNow ? 'wallet' : null, (float) $validated['joining_amount']);

            if (! $payNow) {
                $payments->submitProof($member, (float) $member->joining_amount, $request->file('proof'), $validated['reference'] ?? null, $sponsor);
            }

            return $member;
        });

        return redirect()->route('team.index')->with('success', $payNow
            ? "{$member->username} registered and activated with \${$member->paid_amount} paid from your wallet."
            : "{$member->username} registered under you. Their payment screenshot is awaiting review.");
    }
}
