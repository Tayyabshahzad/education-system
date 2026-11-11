<x-app-layout title="My Classes" subtitle="Your live training batches and class schedule.">
    @if (! $eligible)
        <div class="card">
            <x-empty icon="academic" title="Classes unlock with the full package"
                     text="Members who have paid the full ${{ number_format($fullFee, 0) }} are placed into a live class batch with a mentor.">
                <a href="{{ route('payments.index') }}" class="btn btn-primary mt-5">Top up now</a>
            </x-empty>
        </div>
    @elseif ($batches->isEmpty())
        <div class="card">
            <x-empty icon="calendar" title="You'll be assigned to a batch soon" text="Our team places full members into the next available batch. Your schedule will appear here." />
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-2">
            @foreach ($batches as $batch)
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ $batch->name }}</h3>
                        <x-status-badge :status="$batch->status" />
                    </div>
                    <div class="card-body space-y-4 text-sm">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-gold/10 text-gold"><x-icon name="calendar" /></span>
                            <div>
                                <div class="text-ink-100">{{ $batch->scheduleLabel() }}</div>
                                <div class="text-xs text-ink-400">Starts {{ $batch->start_date?->format('d M Y') ?? 'TBA' }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-ink-800 text-ink-400"><x-icon name="teacher" /></span>
                            <div>
                                <div class="text-ink-100">{{ $batch->teacher?->name ?? 'Mentor to be assigned' }}</div>
                                <div class="text-xs text-ink-400">Mentor</div>
                            </div>
                        </div>
                        @if ($batch->description)
                            <p class="whitespace-pre-line text-ink-400">{{ $batch->description }}</p>
                        @endif
                        @if ($batch->meeting_link)
                            <a href="{{ $batch->meeting_link }}" target="_blank" rel="noopener" class="btn btn-primary w-full"><x-icon name="link" class="h-4 w-4" /> Join class</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-app-layout>
