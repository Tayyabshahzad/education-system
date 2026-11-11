<x-app-layout :title="$batch->name">
    <x-slot:actions>
        <a href="{{ route('admin.batches.edit', $batch) }}" class="btn btn-secondary btn-sm">Edit batch</a>
    </x-slot:actions>

    <div class="mb-6 grid gap-4 sm:grid-cols-4">
        <x-stat label="Teacher" :value="$batch->teacher?->name ?? '—'" icon="teacher" />
        <x-stat label="Schedule" :value="$batch->days ?: '—'" :hint="$batch->scheduleLabel()" icon="calendar" />
        <x-stat label="Starts" :value="$batch->start_date?->format('d M Y') ?? 'TBA'" icon="clock" />
        <x-stat label="Members" :value="$batch->members->count()" icon="users" />
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Enrolled members</h3></div>
            @if ($batch->members->isEmpty())
                <x-empty icon="users" title="No members yet" text="Pick members from the list to add them to this batch." />
            @else
                <div class="table-wrap">
                    <table class="table">
                        <tbody>
                            @foreach ($batch->members as $m)
                                <tr>
                                    <td><div class="font-medium text-ink-100">{{ $m->name }}</div><div class="text-xs text-ink-400">{{ $m->username }} · {{ $m->phone }}</div></td>
                                    <td class="text-ink-400">Added {{ $m->pivot->created_at->format('d M Y') }}</td>
                                    <td class="text-right">
                                        <form method="POST" action="{{ route('admin.batches.unassign', [$batch, $m]) }}">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-danger btn-sm">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Add members</h3>
                <span class="text-xs text-ink-400">Active members with ${{ number_format($fullFee, 0) }}+ paid</span>
            </div>
            <div class="card-body border-b border-ink-700">
                <form method="GET" class="flex gap-2">
                    <x-text-input name="q" :value="$search" placeholder="Search name or username" />
                    <button class="btn btn-secondary">Search</button>
                </form>
            </div>
            @if ($eligible->isEmpty())
                <x-empty icon="users" title="No eligible members" text="Only active members who have paid the full fee and are not already in this batch appear here." />
            @else
                <form method="POST" action="{{ route('admin.batches.assign', $batch) }}" x-data="{ picked: [] }">
                    @csrf
                    <div class="max-h-[28rem] divide-y divide-ink-700/60 overflow-y-auto">
                        @foreach ($eligible as $m)
                            <label class="flex cursor-pointer items-center gap-3 px-5 py-3 hover:bg-ink-800/40">
                                <input type="checkbox" name="members[]" value="{{ $m->id }}" x-model="picked" class="rounded border-ink-600 bg-ink-800 text-gold focus:ring-gold/40 focus:ring-offset-ink-950">
                                <span class="flex-1">
                                    <span class="block text-sm font-medium text-ink-100">{{ $m->name }}</span>
                                    <span class="block text-xs text-ink-400">{{ $m->username }}</span>
                                </span>
                                @if ($m->batches_count)
                                    <span class="badge badge-gray">In {{ $m->batches_count }} other</span>
                                @else
                                    <span class="badge badge-gold">No batch</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                    <div class="flex items-center justify-between gap-3 border-t border-ink-700 px-5 py-3">
                        <span class="text-xs text-ink-400"><span x-text="picked.length">0</span> selected</span>
                        <button class="btn btn-primary btn-sm" :disabled="picked.length === 0">Assign to batch</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
