<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MemberDetailsRules;
use App\Models\Setting;
use App\Models\User;
use App\Services\MemberService;
use App\Services\StaffAccountService;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * One "Add user" screen for every role. The admin picks the role first:
 *  - member: must be placed under a sponsor (any member), payment can be recorded
 *  - staff / teacher / super admin: no sponsor or referral; staff get a permission set
 */
class UserController extends Controller
{
    public const ROLE_LABELS = [
        User::ROLE_MEMBER => 'Member',
        User::ROLE_STAFF => 'Staff',
        User::ROLE_TEACHER => 'Teacher',
        User::ROLE_SUPER_ADMIN => 'Super Admin',
    ];

    public function create(Request $request): View
    {
        $roles = $this->allowedRoles($request->user());
        abort_if($roles === [], 403);

        $role = in_array($request->query('role'), $roles, true) ? $request->query('role') : $roles[0];
        $sponsor = $request->query('sponsor') ? User::role(User::ROLE_MEMBER)->find($request->query('sponsor')) : null;

        return view('admin.users.create', [
            'roles' => array_intersect_key(self::ROLE_LABELS, array_flip($roles)),
            'role' => old('role', $role),
            'sponsor' => $sponsor,
            'sponsorRequired' => $this->sponsorRequired(),
            'minFee' => Setting::money('min_join_fee'),
            'fullFee' => Setting::money('full_fee'),
            'maxFee' => Setting::money('max_fee'),
            'granted' => old('permissions', Permissions::STAFF_DEFAULTS),
        ]);
    }

    public function store(Request $request, MemberService $members, StaffAccountService $accounts): RedirectResponse
    {
        $roles = $this->allowedRoles($request->user());
        $role = $request->validate(['role' => ['required', Rule::in($roles)]])['role'];

        if ($role === User::ROLE_MEMBER) {
            return $this->storeMember($request, $members);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:4', 'max:30', 'alpha_dash', Rule::unique('users')],
            'email' => ['required', 'email', 'lowercase', 'max:255', Rule::unique('users')],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::in(Permissions::all())],
        ]);

        $user = $accounts->create($data, $role, $request->user(), $data['permissions'] ?? []);

        $redirect = match ($role) {
            User::ROLE_STAFF => route('admin.staff.index'),
            User::ROLE_TEACHER => route('admin.teachers.index'),
            default => route('dashboard'),
        };

        return redirect($redirect)->with('success', self::ROLE_LABELS[$role]." {$user->name} created.");
    }

    private function storeMember(Request $request, MemberService $members): RedirectResponse
    {
        $validated = $request->validate([
            ...MemberDetailsRules::rules(),
            'sponsor' => [$this->sponsorRequired() ? 'required' : 'nullable', 'string'],
            'mark_paid' => ['boolean'],
        ], [
            'sponsor.required' => 'Every member must be placed under a sponsor. Enter the sponsor\'s username or referral code.',
        ]);

        $sponsor = $this->resolveSponsor($validated['sponsor'] ?? null);
        $markPaid = $request->boolean('mark_paid') && $request->user()->can('payments.review');

        $member = $members->create(
            $validated,
            $sponsor,
            $request->user(),
            $markPaid ? 'received' : null,
            (float) $validated['joining_amount'],
        );

        return redirect()->route('admin.members.show', $member)
            ->with('success', "Member {$member->username} created".($sponsor ? " under {$sponsor->username}" : '').($markPaid ? ' and payment recorded.' : '.'));
    }

    /** Only the very first member may be created without a sponsor (they become the tree root). */
    private function sponsorRequired(): bool
    {
        return User::role(User::ROLE_MEMBER)->exists();
    }

    private function resolveSponsor(?string $value): ?User
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return User::role(User::ROLE_MEMBER)
            ->where(fn ($q) => $q->where('username', $value)->orWhere('referral_code', strtoupper($value)))
            ->first()
            ?? throw ValidationException::withMessages(['sponsor' => 'No member found with that username or referral code.']);
    }

    /** @return list<string> */
    private function allowedRoles(User $user): array
    {
        return array_values(array_filter([
            $user->can('members.create') ? User::ROLE_MEMBER : null,
            $user->can('staff.manage') ? User::ROLE_STAFF : null,
            $user->can('teachers.manage') ? User::ROLE_TEACHER : null,
            $user->isSuperAdmin() ? User::ROLE_SUPER_ADMIN : null,
        ]));
    }
}
