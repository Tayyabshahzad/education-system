<x-app-layout title="My Team" subtitle="Members you referred directly (level 1) and the members they brought in (levels 2 and 3).">
    <x-slot:actions>
        <a href="{{ route('team.tree') }}" class="btn btn-secondary btn-sm hidden sm:inline-flex"><x-icon name="tree" class="h-4 w-4" /> Tree view</a>
        <a href="{{ route('team.create') }}" class="btn btn-primary btn-sm"><x-icon name="user-plus" class="h-4 w-4" /> Register member</a>
    </x-slot:actions>

    <div x-data="{ tab: 1 }">
        <div class="mb-4 flex gap-2 overflow-x-auto" role="tablist">
            @foreach ($levels as $level => $members)
                <button type="button" role="tab" @click="tab = {{ $level }}" :aria-selected="tab === {{ $level }}"
                        class="flex shrink-0 items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition"
                        :class="tab === {{ $level }} ? 'border-gold/40 bg-gold/10 text-gold' : 'border-ink-700 bg-ink-900 text-ink-400 hover:text-ink-100'">
                    Level {{ $level }}
                    <span class="rounded-full bg-ink-800 px-2 py-0.5 text-xs text-ink-200">{{ $members->count() }}</span>
                    <span class="money text-xs opacity-70">${{ number_format($commissionLevels[$level] ?? 0, 0) }}</span>
                </button>
            @endforeach
        </div>

        @foreach ($levels as $level => $members)
            <div x-show="tab === {{ $level }}" @if ($level !== 1) x-cloak @endif class="card">
                @if ($members->isEmpty())
                    <x-empty icon="users" title="No level {{ $level }} members yet"
                             :text="$level === 1 ? 'Share your referral link or register a member yourself to start building your team.' : 'Members appear here when your level '.($level - 1).' team refers others.'" />
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr><th>Member</th><th>Sponsor</th><th>Paid</th><th>Status</th><th>Commission</th><th>Joined</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($members as $m)
                                    <tr>
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <x-avatar :user="$m" />
                                                <div>
                                                    <div class="font-medium text-ink-100">{{ $m->name }}</div>
                                                    <div class="text-xs text-ink-400">{{ $m->username }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-ink-400">{{ $m->sponsor?->username }}</td>
                                        <td class="money">${{ number_format((float) $m->paid_amount, 2) }}</td>
                                        <td><x-status-badge :status="$m->status" /></td>
                                        <td>
                                            @if ($m->commission_released_at)
                                                <span class="badge badge-green">Earned</span>
                                            @else
                                                <span class="badge badge-gray" title="Released when they reach ${{ number_format($fullFee, 0) }}">Awaiting ${{ number_format($fullFee, 0) }}</span>
                                            @endif
                                        </td>
                                        <td class="text-ink-400">{{ $m->created_at->format('d M Y') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</x-app-layout>
