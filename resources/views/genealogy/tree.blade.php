<x-app-layout title="Genealogy Tree">
    @php
        $isAdminView = $routeName === 'admin.genealogy';
        $fullFee = \App\Models\Setting::money('full_fee');
    @endphp

    <x-slot:actions>
        @if ($root && $canGoUp)
            <a href="{{ route($routeName, $root->sponsor_id) }}" class="btn btn-secondary btn-sm"><x-icon name="arrow-up" class="h-4 w-4" /> Up one level</a>
        @endif
        @if ($home && $root && ! $root->is($home))
            <a href="{{ route($routeName) }}" class="btn btn-secondary btn-sm">Back to me</a>
        @endif
    </x-slot:actions>

    @if ($isAdminView)
        <form method="GET" class="mb-5 flex flex-wrap items-end gap-3">
            <div>
                <x-input-label for="jump" value="Show tree from" />
                <select id="jump" class="field-select min-w-56" onchange="if (this.value) window.location = this.value">
                    <option value="">Choose a top-level user…</option>
                    @foreach ($topLevel as $t)
                        <option value="{{ route('admin.genealogy', $t) }}" @selected($root?->is($t))>{{ $t->username }} — {{ $t->name }}{{ $t->isMember() ? '' : ' ('.\App\Models\User::roleLabel($t->primaryRole()).')' }}</option>
                    @endforeach
                </select>
            </div>
            <p class="pb-2.5 text-xs text-ink-400">Click any member in the tree to view the tree from them.</p>
        </form>
    @endif

    <div class="mb-4 flex flex-wrap items-center gap-4 text-xs text-ink-400">
        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-gold"></span> Full package (${{ number_format($fullFee, 0) }})</span>
        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-bull"></span> Active, partially paid</span>
        <span>Only active users are shown. Pending or blocked users appear once an admin activates them.</span>
    </div>

    <div class="card grid-bg overflow-x-auto p-6 sm:p-8">
        @if (! $tree)
            <x-empty icon="tree" title="No members yet" />
        @else
            <div class="tree mx-auto w-max min-w-full">
                <ul>
                    @include('genealogy._node', ['node' => $tree, 'isRoot' => true])
                </ul>
            </div>
        @endif
    </div>
</x-app-layout>
