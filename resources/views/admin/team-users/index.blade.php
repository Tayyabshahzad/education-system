@php
    $isStaff = $role === \App\Models\User::ROLE_STAFF;
    $prefix = $isStaff ? 'admin.staff' : 'admin.teachers';
    $allPermissions = collect(\App\Support\Permissions::GROUPS)->collapse();
@endphp
<x-app-layout :title="$isStaff ? 'Staff & Access' : 'Teachers'"
              :subtitle="$isStaff ? 'Staff members only see the sections you allow. The super admin always has full access.' : 'Teachers can log in to see their batches, schedules and student lists.'">
    <x-slot:actions>
        <a href="{{ route('admin.users.create', ['role' => $role]) }}" class="btn btn-primary btn-sm">{{ $isStaff ? 'Add staff' : 'Add teacher' }}</a>
    </x-slot:actions>

    <div class="card">
        @if ($users->isEmpty())
            <x-empty :icon="$isStaff ? 'shield' : 'teacher'" :title="$isStaff ? 'No staff yet' : 'No teachers yet'" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Name</th><th>Contact</th><th>{{ $isStaff ? 'Access' : 'Batches' }}</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $u)
                            <tr>
                                <td><div class="font-medium text-ink-100">{{ $u->name }}</div><div class="text-xs text-ink-400">{{ $u->username }}</div></td>
                                <td><div>{{ $u->email }}</div><div class="text-xs text-ink-400">{{ $u->phone ?: '—' }}</div></td>
                                <td class="!whitespace-normal">
                                    @if ($isStaff)
                                        <div class="flex max-w-md flex-wrap gap-1">
                                            @forelse ($u->permissions as $p)
                                                <span class="badge badge-gray">{{ $allPermissions[$p->name] ?? $p->name }}</span>
                                            @empty
                                                <span class="text-xs text-ink-400">No access</span>
                                            @endforelse
                                        </div>
                                    @else
                                        {{ $u->teaching_batches_count }}
                                    @endif
                                </td>
                                <td><x-status-badge :status="$u->status" /></td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route($prefix.'.edit', $u) }}" class="btn btn-secondary btn-sm">Edit</a>
                                        @unless ($u->is(auth()->user()))
                                            <form method="POST" action="{{ route($prefix.'.destroy', $u) }}" onsubmit="return confirm('Remove {{ $u->name }}?')">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-danger btn-sm">Remove</button>
                                            </form>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $users->links() }}
        @endif
    </div>
</x-app-layout>
