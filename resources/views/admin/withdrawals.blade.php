<x-app-layout title="Withdrawals" subtitle="Pay the member through their chosen method, then approve. Rejecting returns the amount to their wallet.">
    @include('partials.status-tabs', ['route' => 'admin.withdrawals.index'])

    <div class="card">
        @if ($withdrawals->isEmpty())
            <x-empty icon="check" title="Nothing here" text="No {{ $status === 'all' ? '' : $status }} withdrawal requests." />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Requested</th><th>Member</th><th>Amount</th><th>Pay to</th><th>Status</th><th class="text-right">Action</th></tr></thead>
                    <tbody>
                        @foreach ($withdrawals as $w)
                            <tr x-data="{ mode: null }">
                                <td class="text-ink-400">{{ $w->created_at->format('d M Y, H:i') }}</td>
                                <td>
                                    <a href="{{ route('admin.members.show', $w->user_id) }}" class="font-medium text-white hover:text-gold">{{ $w->user->name }}</a>
                                    <div class="text-xs text-ink-400">{{ $w->user->username }} · {{ $w->user->phone }}</div>
                                </td>
                                <td class="money text-white">${{ number_format((float) $w->amount, 2) }}</td>
                                <td class="!whitespace-normal">
                                    <div class="font-medium">{{ $w->method }}</div>
                                    <div class="max-w-xs whitespace-pre-line text-xs text-ink-400">{{ $w->account_details }}</div>
                                </td>
                                <td>
                                    <x-status-badge :status="$w->status" />
                                    @if ($w->reviewer)<div class="mt-1 text-xs text-ink-400">{{ $w->reviewer->name }}</div>@endif
                                    @if ($w->note)<div class="max-w-[14rem] truncate text-xs text-ink-400" title="{{ $w->note }}">{{ $w->note }}</div>@endif
                                </td>
                                <td class="text-right">
                                    @if ($w->status === 'pending')
                                        @can('withdrawals.review')
                                            <div x-show="!mode" class="flex justify-end gap-2">
                                                <button type="button" class="btn btn-success btn-sm" @click="mode = 'approve'">Mark paid</button>
                                                <button type="button" class="btn btn-danger btn-sm" @click="mode = 'reject'">Reject</button>
                                            </div>
                                            <form x-show="mode === 'approve'" x-cloak method="POST" action="{{ route('admin.withdrawals.approve', $w) }}" class="flex justify-end gap-2">
                                                @csrf
                                                <x-text-input name="note" placeholder="Transaction ref (optional)" class="!w-48 !py-1.5 text-xs" />
                                                <button class="btn btn-success btn-sm">Confirm</button>
                                                <button type="button" class="btn btn-secondary btn-sm" @click="mode = null">Cancel</button>
                                            </form>
                                            <form x-show="mode === 'reject'" x-cloak method="POST" action="{{ route('admin.withdrawals.reject', $w) }}" class="flex justify-end gap-2">
                                                @csrf
                                                <x-text-input name="note" placeholder="Reason" class="!w-44 !py-1.5 text-xs" required />
                                                <button class="btn btn-danger btn-sm">Confirm</button>
                                                <button type="button" class="btn btn-secondary btn-sm" @click="mode = null">Cancel</button>
                                            </form>
                                        @endcan
                                    @endif
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
