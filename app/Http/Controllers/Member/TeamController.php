<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\MemberDetailsRules;
use App\Models\Setting;
use App\Models\User;
use App\Services\GenealogyTree;
use App\Services\MemberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function store(Request $request, MemberService $members): RedirectResponse
    {
        $sponsor = $request->user();

        $validated = $request->validate([
            ...MemberDetailsRules::rules(),
            'pay_from_wallet' => ['boolean'],
        ]);

        $payNow = $request->boolean('pay_from_wallet');
        if ($payNow && (float) $sponsor->wallet_balance < (float) $validated['joining_amount']) {
            return back()->withInput()->withErrors(['pay_from_wallet' => 'Your wallet balance is not enough to pay this fee.']);
        }

        $member = $members->create(
            $validated,
            $sponsor,
            $sponsor,
            $payNow ? 'wallet' : null,
            (float) $validated['joining_amount'],
        );

        return redirect()->route('team.index')->with('success', $payNow
            ? "{$member->username} registered and activated with \${$member->paid_amount} paid from your wallet."
            : "{$member->username} registered under you. They can log in and submit their payment.");
    }
}
