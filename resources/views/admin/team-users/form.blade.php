@php
    $isStaff = $role === \App\Models\User::ROLE_STAFF;
    $prefix = $isStaff ? 'admin.staff' : 'admin.teachers';
    $noun = $isStaff ? 'staff member' : 'teacher';
    $granted = old('permissions', $granted);
@endphp
<x-app-layout :title="$user->exists ? 'Edit '.$user->name : 'Add '.$noun">
    <form method="POST" action="{{ $user->exists ? route($prefix.'.update', $user) : route($prefix.'.store') }}" class="grid max-w-5xl gap-6 {{ $isStaff ? 'xl:grid-cols-2' : '' }}">
        @csrf
        @if ($user->exists) @method('PUT') @endif

        <div class="card h-max">
            <div class="card-header"><h3 class="card-title">Account</h3></div>
            <div class="card-body grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="name" value="Full name" />
                    <x-text-input id="name" name="name" :value="old('name', $user->name)" required />
                </div>
                <div>
                    <x-input-label for="email" value="Email (login)" />
                    <x-text-input id="email" type="email" name="email" :value="old('email', $user->email)" required />
                </div>
                <div>
                    <x-input-label for="phone" value="Phone" />
                    <x-text-input id="phone" name="phone" :value="old('phone', $user->phone)" />
                </div>
                <div>
                    <x-input-label for="password" :value="$user->exists ? 'New password (optional)' : 'Password'" />
                    <x-text-input id="password" type="password" name="password" :required="! $user->exists" autocomplete="new-password" />
                </div>
                <div>
                    <x-input-label for="password_confirmation" value="Confirm password" />
                    <x-text-input id="password_confirmation" type="password" name="password_confirmation" :required="! $user->exists" autocomplete="new-password" />
                </div>
                @if ($user->exists)
                    <div>
                        <x-input-label for="status" value="Status" />
                        <select id="status" name="status" class="field-select">
                            <option value="active" @selected(old('status', $user->status) === 'active')>Active</option>
                            <option value="blocked" @selected(old('status', $user->status) === 'blocked')>Blocked (cannot log in)</option>
                        </select>
                    </div>
                @endif
            </div>
        </div>

        @if ($isStaff && ! auth()->user()->isSuperAdmin())
            <div class="card">
                <div class="card-body text-sm text-ink-300">Permissions can only be changed by a super admin.</div>
            </div>
        @elseif ($isStaff)
            <div class="card">
                <div class="card-header"><h3 class="card-title">What can this person do?</h3></div>
                <div class="card-body space-y-5">
                    @foreach (\App\Support\Permissions::GROUPS as $group => $perms)
                        <fieldset>
                            <legend class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-400">{{ $group }}</legend>
                            <div class="space-y-2">
                                @foreach ($perms as $key => $label)
                                    <label class="flex items-center gap-3 rounded-lg border border-ink-700 bg-ink-800/40 px-3 py-2.5 text-sm">
                                        <input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key, $granted, true))
                                               class="rounded border-ink-600 bg-ink-800 text-gold focus:ring-gold/40 focus:ring-offset-ink-950">
                                        <span class="text-ink-200">{{ $label }}</span>
                                        @if ($key === 'staff.manage' || $key === 'settings.manage')
                                            <span class="badge badge-red ml-auto">Sensitive</span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="flex gap-3 {{ $isStaff ? 'xl:col-span-2' : '' }}">
            <button class="btn btn-primary">{{ $user->exists ? 'Save changes' : 'Create '.$noun }}</button>
            <a href="{{ route($prefix.'.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</x-app-layout>
