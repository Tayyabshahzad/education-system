<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MemberDetailsRules;
use App\Models\Setting;
use App\Models\User;
use App\Services\GenealogyTree;
use App\Services\MemberService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = $request->query('status');
        $paid = $request->query('paid');
        $fullFee = Setting::money('full_fee');

        $members = User::role(User::ROLE_MEMBER)
            ->with('sponsor:id,username')
            ->withCount('referrals')
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('referral_code', $search)))
            ->when(in_array($status, ['pending', 'active', 'blocked'], true), fn ($q) => $q->where('status', $status))
            ->when($paid === 'full', fn ($q) => $q->where('paid_amount', '>=', $fullFee))
            ->when($paid === 'partial', fn ($q) => $q->where('paid_amount', '>', 0)->where('paid_amount', '<', $fullFee))
            ->when($paid === 'none', fn ($q) => $q->where('paid_amount', 0))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.members.index', compact('members', 'search', 'status', 'paid', 'fullFee'));
    }

    public function show(User $member): View
    {
        $this->ensureMember($member);

        return view('admin.members.show', [
            'member' => $member->load(['sponsor', 'batches.teacher']),
            'levels' => $member->downlineByLevel(3),
            'payments' => $member->payments()->latest()->limit(10)->get(),
            'transactions' => $member->walletTransactions()->with('fromUser:id,username')->latest()->limit(10)->get(),
            'withdrawals' => $member->withdrawals()->latest()->limit(10)->get(),
            'fullFee' => Setting::money('full_fee'),
            'remaining' => $member->payableRemaining(),
            'minimum' => $member->minimumNextPayment(),
        ]);
    }

    public function create(Request $request): View
    {
        $sponsor = $request->query('sponsor') ? User::role(User::ROLE_MEMBER)->find($request->query('sponsor')) : null;

        return view('admin.members.create', [
            'sponsor' => $sponsor,
            'minFee' => Setting::money('min_join_fee'),
            'fullFee' => Setting::money('full_fee'),
            'maxFee' => Setting::money('max_fee'),
        ]);
    }

    public function store(Request $request, MemberService $members): RedirectResponse
    {
        $validated = $request->validate([
            ...MemberDetailsRules::rules(),
            'sponsor' => ['nullable', 'string'],
            'mark_paid' => ['boolean'],
        ]);

        $sponsor = $this->resolveSponsor($validated['sponsor'] ?? null);
        $markPaid = $request->boolean('mark_paid');

        $member = $members->create(
            $validated,
            $sponsor,
            $request->user(),
            $markPaid ? 'received' : null,
            (float) $validated['joining_amount'],
        );

        return redirect()->route('admin.members.show', $member)->with('success', "Member {$member->username} created".($markPaid ? ' and payment recorded.' : '.'));
    }

    public function edit(User $member): View
    {
        $this->ensureMember($member);

        return view('admin.members.edit', ['member' => $member->load('sponsor')]);
    }

    public function update(Request $request, User $member, MemberService $members): RedirectResponse
    {
        $this->ensureMember($member);

        $validated = $request->validate([
            ...MemberDetailsRules::rules($member, passwordRequired: false),
            'sponsor' => ['nullable', 'string'],
        ]);

        $member->fill(collect($validated)->only(['name', 'username', 'email', 'phone', 'country'])->all());
        if (! empty($validated['password'])) {
            $member->password = $validated['password'];
        }
        $member->save();

        $newSponsor = $this->resolveSponsor($validated['sponsor'] ?? null);
        if ($newSponsor?->id !== $member->sponsor_id) {
            $members->changeSponsor($member, $newSponsor);
        }

        return redirect()->route('admin.members.show', $member)->with('success', 'Member updated.');
    }

    public function updateStatus(Request $request, User $member): RedirectResponse
    {
        $this->ensureMember($member);
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'blocked', 'pending'])]]);

        $member->status = $data['status'];
        $member->save();

        return back()->with('success', "{$member->username} is now {$data['status']}.");
    }

    /** Record money received outside the system (cash / bank) straight from the member page. */
    public function recordPayment(Request $request, User $member, PaymentService $payments): RedirectResponse
    {
        $this->ensureMember($member);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $payments->recordReceived($member, round((float) $data['amount'], 2), $request->user(), $data['note'] ?? null);

        return back()->with('success', 'Payment of $'.number_format((float) $data['amount'], 2).' recorded.');
    }

    public function destroy(User $member, MemberService $members): RedirectResponse
    {
        $this->ensureMember($member);
        $username = $member->username;
        $members->delete($member);

        return redirect()->route('admin.members.index')->with('success', "{$username} deleted. Their direct team moved up to their sponsor.");
    }

    public function tree(Request $request, GenealogyTree $tree, ?User $root = null): View
    {
        $root ??= User::role(User::ROLE_MEMBER)->whereNull('sponsor_id')->orderBy('id')->first();

        return view('genealogy.tree', [
            'tree' => $root ? $tree->build($root) : null,
            'root' => $root,
            'home' => null,
            'routeName' => 'admin.genealogy',
            'canGoUp' => (bool) $root?->sponsor_id,
            'topLevel' => User::role(User::ROLE_MEMBER)->whereNull('sponsor_id')->orderBy('id')->get(['id', 'username', 'name']),
        ]);
    }

    private function resolveSponsor(?string $value): ?User
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $sponsor = User::role(User::ROLE_MEMBER)
            ->where(fn ($q) => $q->where('username', $value)->orWhere('referral_code', strtoupper($value)))
            ->first();

        if (! $sponsor) {
            throw ValidationException::withMessages(['sponsor' => 'No member found with that username or referral code.']);
        }

        return $sponsor;
    }

    private function ensureMember(User $user): void
    {
        abort_unless($user->isMember(), 404);
    }
}
