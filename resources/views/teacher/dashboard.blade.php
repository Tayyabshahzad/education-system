<x-app-layout title="Dashboard" subtitle="Your referral link, earnings and assigned class batches.">
    @include('partials.network-summary')

    <h2 class="mb-4 mt-8 font-display text-lg font-semibold text-ink-100">My Batches</h2>
    @if ($batches->isEmpty())
        <div class="card"><x-empty icon="academic" title="No batches assigned yet" text="Once the academy team assigns you a batch, its schedule and student list appear here." /></div>
    @else
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($batches as $b)
                <a href="{{ route('teacher.batches.show', $b) }}" class="card block p-5 transition hover:border-gold/40">
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="font-display text-lg font-semibold text-ink-100">{{ $b->name }}</h3>
                        <x-status-badge :status="$b->status" />
                    </div>
                    <div class="mt-4 space-y-2 text-sm text-ink-200">
                        <div class="flex items-center gap-2"><x-icon name="calendar" class="h-4 w-4 text-ink-400" /> {{ $b->scheduleLabel() }}</div>
                        <div class="flex items-center gap-2"><x-icon name="clock" class="h-4 w-4 text-ink-400" /> Starts {{ $b->start_date?->format('d M Y') ?? 'TBA' }}</div>
                        <div class="flex items-center gap-2"><x-icon name="users" class="h-4 w-4 text-ink-400" /> {{ $b->members_count }} students</div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-app-layout>
