<x-app-layout :title="$batch->name">
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat label="Schedule" :value="$batch->days ?: '—'" :hint="$batch->scheduleLabel()" icon="calendar" />
        <x-stat label="Starts" :value="$batch->start_date?->format('d M Y') ?? 'TBA'" icon="clock" />
        <x-stat label="Students" :value="$batch->members->count()" icon="users" />
    </div>

    @if ($batch->meeting_link)
        <a href="{{ $batch->meeting_link }}" target="_blank" rel="noopener" class="btn btn-primary mb-6"><x-icon name="link" class="h-4 w-4" /> Open class link</a>
    @endif

    <div class="card">
        <div class="card-header"><h3 class="card-title">Students</h3></div>
        @if ($batch->members->isEmpty())
            <x-empty icon="users" title="No students yet" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Name</th><th>Username</th><th>Phone</th><th>Email</th></tr></thead>
                    <tbody>
                        @foreach ($batch->members as $m)
                            <tr>
                                <td class="font-medium text-ink-100">{{ $m->name }}</td>
                                <td class="text-ink-400">{{ $m->username }}</td>
                                <td>{{ $m->phone ?: '—' }}</td>
                                <td class="text-ink-400">{{ $m->email }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
