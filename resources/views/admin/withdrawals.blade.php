<x-app-layout title="Withdrawals" subtitle="Open a request to see the member's full details and bank account, pay them, then approve. Rejecting returns the amount to their wallet.">
    @include('partials.status-tabs', ['route' => 'admin.withdrawals.index'])

    <div class="card">
        @if ($withdrawals->isEmpty())
            <x-empty icon="check" title="Nothing here" text="No {{ $status === 'all' ? '' : $status }} withdrawal requests." />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Requested</th><th>Member</th><th>Amount</th><th>Pay to</th><th>Status</th><th class="text-right"></th></tr></thead>
                    <tbody>
                        @foreach ($withdrawals as $w)
                            <tr>
                                <td class="text-ink-400">{{ $w->created_at->format('d M Y, H:i') }}</td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <x-avatar :user="$w->user" />
                                        <div>
                                            <div class="font-medium text-ink-100">{{ $w->user->name }}</div>
                                            <div class="text-xs text-ink-400">{{ $w->user->username }} · {{ $w->user->phone }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="money font-semibold text-ink-100">${{ number_format((float) $w->amount, 2) }}</td>
                                <td>
                                    <div class="font-medium">{{ $w->method }}{{ $w->bank_name ? ' · '.$w->bank_name : '' }}</div>
                                    <div class="text-xs text-ink-400">{{ $w->account_title ?: '—' }} · <span class="font-mono">{{ $w->account_number ?: $w->account_details }}</span></div>
                                </td>
                                <td>
                                    <x-status-badge :status="$w->status" />
                                    @if ($w->reviewer)<div class="mt-1 text-xs text-ink-400">{{ $w->reviewer->name }}</div>@endif
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('admin.withdrawals.show', $w) }}" class="btn btn-sm {{ $w->status === 'pending' ? 'btn-primary' : 'btn-secondary' }}">
                                        {{ $w->status === 'pending' ? 'Review' : 'View' }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $withdrawals->links() }}
        @endif
    </div>
</x-app-layout>
