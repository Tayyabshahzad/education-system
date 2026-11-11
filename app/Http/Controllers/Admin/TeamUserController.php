<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Staff and teacher accounts share one controller; the route decides the role
 * ('staff' routes need staff.manage, 'teacher' routes need teachers.manage).
 */
class TeamUserController extends Controller
{
    public function index(Request $request): View
    {
        $role = $this->role($request);

        return view('admin.team-users.index', [
            'role' => $role,
            'users' => User::role($role)
                ->when($role === User::ROLE_TEACHER, fn ($q) => $q->withCount('teachingBatches'))
                ->with('permissions')
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        $role = $this->role($request);

        return view('admin.team-users.form', [
            'role' => $role,
            'user' => new User,
            'granted' => $role === User::ROLE_STAFF ? Permissions::STAFF_DEFAULTS : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $role = $this->role($request);
        $data = $this->validated($request, null);

        $user = new User(collect($data)->only(['name', 'username', 'email', 'phone', 'password'])->all());
        $user->created_by = $request->user()->id;
        $user->save();

        // Staff/teachers are active immediately and never join the referral tree.
        $user->forceFill(['status' => 'active'])->save();
        $user->assignRole($role);

        if ($role === User::ROLE_STAFF) {
            $user->syncPermissions($data['permissions'] ?? []);
        }

        return redirect()->route($this->routePrefix($role).'.index')->with('success', ucfirst($role)." {$user->name} created.");
    }

    public function edit(Request $request, User $user): View
    {
        $role = $this->role($request);
        abort_unless($user->hasRole($role), 404);

        return view('admin.team-users.form', [
            'role' => $role,
            'user' => $user,
            'granted' => $user->getDirectPermissions()->pluck('name')->all(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $role = $this->role($request);
        abort_unless($user->hasRole($role), 404);
        $data = $this->validated($request, $user);

        $user->fill(collect($data)->only(['name', 'username', 'email', 'phone'])->all());
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->forceFill(['status' => $data['status']])->save();

        if ($role === User::ROLE_STAFF) {
            $user->syncPermissions($data['permissions'] ?? []);
        }

        return redirect()->route($this->routePrefix($role).'.index')->with('success', ucfirst($role).' updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $role = $this->role($request);
        abort_unless($user->hasRole($role), 404);
        abort_if($user->is($request->user()), 422, 'You cannot delete your own account.');

        if ($role === User::ROLE_TEACHER) {
            $user->teachingBatches()->update(['teacher_id' => null]);
        }
        $user->forceFill(['email' => $user->email.'#deleted-'.$user->id, 'username' => $user->username.'#deleted-'.$user->id])->save();
        $user->delete();

        return back()->with('success', ucfirst($role).' removed.');
    }

    private function validated(Request $request, ?User $user): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:4', 'max:30', 'alpha_dash', Rule::unique('users')->ignore($user)],
            'email' => ['required', 'email', 'lowercase', 'max:255', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'status' => [$user ? 'required' : 'nullable', Rule::in(['active', 'blocked'])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::in(Permissions::all())],
        ]);
    }

    private function role(Request $request): string
    {
        return $request->route()->defaults['role'];
    }

    private function routePrefix(string $role): string
    {
        return $role === User::ROLE_STAFF ? 'admin.staff' : 'admin.teachers';
    }
}
