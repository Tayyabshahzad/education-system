<x-app-layout title="Class Batches" subtitle="Group full-package members into batches, set a schedule and assign a teacher.">
    <x-slot:actions>
        <a href="{{ route('admin.batches.create') }}" class="btn btn-primary btn-sm">New batch</a>
    </x-slot:actions>

    @if ($batches->isEmpty())
        <div class="card"><x-empty icon="calendar" title="No batches yet" text="Create your first batch, choose a teacher and a schedule, then assign full-package members." /></div>
    @else
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($batches as $b)
                <a href="{{ route('admin.batches.show', $b) }}" class="card block p-5 transition hover:border-gold/40">
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="font-display text-lg font-semibold text-white">{{ $b->name }}</h3>
                        <x-status-badge :status="$b->status" />
                    </div>
                    <div class="mt-4 space-y-2 text-sm">
                        <div class="flex items-center gap-2 text-ink-200"><x-icon name="calendar" class="h-4 w-4 text-ink-400" /> {{ $b->scheduleLabel() }}</div>
                        <div class="flex items-center gap-2 text-ink-200"><x-icon name="teacher" class="h-4 w-4 text-ink-400" /> {{ $b->teacher?->name ?? 'No teacher' }}</div>
                        <div class="flex items-center gap-2 text-ink-200"><x-icon name="users" class="h-4 w-4 text-ink-400" /> {{ $b->members_count }} members</div>
                    </div>
                    <div class="mt-4 text-xs text-ink-400">Starts {{ $b->start_date?->format('d M Y') ?? 'TBA' }}</div>
                </a>
            @endforeach
        </div>
        <div class="mt-5">{{ $batches->links() }}</div>
    @endif
</x-app-layout>
