<x-app-layout :title="$batch->exists ? 'Edit '.$batch->name : 'New Batch'">
    @php
        $selectedDays = old('days', $batch->days ? array_map('trim', explode(',', $batch->days)) : []);
    @endphp
    <form method="POST" action="{{ $batch->exists ? route('admin.batches.update', $batch) : route('admin.batches.store') }}" class="max-w-3xl space-y-6">
        @csrf
        @if ($batch->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-header"><h3 class="card-title">Batch</h3></div>
            <div class="card-body grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-input-label for="name" value="Batch name" />
                    <x-text-input id="name" name="name" :value="old('name', $batch->name)" required placeholder="e.g. Forex Foundations — Batch 01" />
                </div>
                <div>
                    <x-input-label for="teacher_id" value="Teacher" />
                    <select id="teacher_id" name="teacher_id" class="field-select">
                        <option value="">— Not assigned —</option>
                        @foreach ($teachers as $t)
                            <option value="{{ $t->id }}" @selected((int) old('teacher_id', $batch->teacher_id) === $t->id)>{{ $t->name }}</option>
                        @endforeach
                    </select>
                    @if ($teachers->isEmpty())
                        <p class="field-help">No teachers yet. @can('teachers.manage')<a href="{{ route('admin.users.create', ['role' => 'teacher']) }}" class="link">Add a teacher</a>@endcan</p>
                    @endif
                </div>
                <div>
                    <x-input-label for="status" value="Status" />
                    <select id="status" name="status" class="field-select">
                        @foreach (['upcoming', 'running', 'completed'] as $s)
                            <option value="{{ $s }}" @selected(old('status', $batch->status) === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Schedule</h3></div>
            <div class="card-body grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-3">
                    <x-input-label value="Class days" />
                    <div class="flex flex-wrap gap-2">
                        @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="days[]" value="{{ $d }}" class="peer sr-only" @checked(in_array($d, $selectedDays, true))>
                                <span class="block rounded-lg border border-ink-700 bg-ink-800 px-3.5 py-2 text-sm text-ink-400 transition peer-checked:border-gold peer-checked:bg-gold/10 peer-checked:text-gold peer-focus-visible:ring-2 peer-focus-visible:ring-gold/50">{{ $d }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <x-input-label for="start_date" value="Start date" />
                    <x-text-input id="start_date" type="date" name="start_date" :value="old('start_date', $batch->start_date?->format('Y-m-d'))" />
                </div>
                <div>
                    <x-input-label for="class_time" value="Class time" />
                    <x-text-input id="class_time" type="time" name="class_time" :value="old('class_time', $batch->class_time ? substr($batch->class_time, 0, 5) : '')" />
                </div>
                <div>
                    <x-input-label for="duration_minutes" value="Duration (minutes)" />
                    <x-text-input id="duration_minutes" type="number" min="15" max="600" name="duration_minutes" :value="old('duration_minutes', $batch->duration_minutes ?? 60)" />
                </div>
                <div class="sm:col-span-3">
                    <x-input-label for="meeting_link" value="Class link (Zoom / Google Meet)" />
                    <x-text-input id="meeting_link" type="url" name="meeting_link" :value="old('meeting_link', $batch->meeting_link)" placeholder="https://" />
                </div>
                <div class="sm:col-span-3">
                    <x-input-label for="description" value="Notes for members" />
                    <textarea id="description" name="description" rows="3" class="field-textarea">{{ old('description', $batch->description) }}</textarea>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button class="btn btn-primary">{{ $batch->exists ? 'Save batch' : 'Create batch' }}</button>
            <a href="{{ $batch->exists ? route('admin.batches.show', $batch) : route('admin.batches.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>

    @if ($batch->exists)
        <form method="POST" action="{{ route('admin.batches.destroy', $batch) }}" class="mt-8 max-w-3xl" onsubmit="return confirm('Delete this batch? Members will be unassigned.')">
            @csrf @method('DELETE')
            <button class="btn btn-danger btn-sm">Delete batch</button>
        </form>
    @endif
</x-app-layout>
