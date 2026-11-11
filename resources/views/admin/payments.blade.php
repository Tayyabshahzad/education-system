<x-app-layout title="Payments" subtitle="Verify uploaded payment proofs. Approving adds the amount to the member's paid total and releases upline commission when they reach the full fee.">
    @include('partials.status-tabs', ['route' => 'admin.payments.index'])

    <div class="card">
        @if ($payments->isEmpty())
            <x-empty icon="check" title="Nothing here" text="No {{ $status === 'all' ? '' : $status }} payments." />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Submitted</th><th>Member</th><th>Amount</th><th>Method</th><th>Proof / ref</th><th>Status</th><th class="text-right">Action</th></tr></thead>
                    <tbody>
                        @foreach ($payments as $p)
                            <tr x-data="{ rejecting: false }">
                                <td class="text-ink-400">{{ $p->created_at->format('d M Y, H:i') }}</td>
                                <td>
                                    <a href="{{ route('admin.members.show', $p->user_id) }}" class="font-medium text-ink-100 hover:text-gold">{{ $p->user->name }}</a>
                                    <div class="text-xs text-ink-400">{{ $p->user->username }} · <span class="font-mono">{{ $p->user->cnic ?: 'no CNIC' }}</span> · paid ${{ number_format((float) $p->user->paid_amount, 2) }}</div>
                                </td>
                                <td class="money text-ink-100">${{ number_format((float) $p->amount, 2) }}</td>
                                <td>
                                    {{ $p->methodLabel() }}
                                    @if ($p->payer && $p->paid_by !== $p->user_id)<div class="text-xs text-ink-400">by {{ $p->payer->username }}</div>@endif
                                </td>
                                <td>
                                    @if ($p->proof_path)
                                        <a href="{{ route('payments.proof', $p) }}" target="_blank" title="Open full size" class="group block w-14">
                                            <img src="{{ route('payments.proof', $p) }}" alt="Payment proof" loading="lazy" class="h-14 w-14 rounded-lg object-cover object-top ring-1 ring-ink-700 transition group-hover:ring-gold">
                                        </a>
                                    @endif
                                    @if ($p->reference)<div class="font-mono text-xs text-ink-400">{{ $p->reference }}</div>@endif
                                </td>
                                <td>
                                    <x-status-badge :status="$p->status" />
                                    @if ($p->reviewer)<div class="mt-1 text-xs text-ink-400">{{ $p->reviewer->name }}</div>@endif
                                    @if ($p->note && $p->status !== 'pending')<div class="max-w-[14rem] truncate text-xs text-ink-400" title="{{ $p->note }}">{{ $p->note }}</div>@endif
                                </td>
                                <td class="text-right">
                                    @if ($p->status === 'pending')
                                        @can('payments.review')
                                            <div x-show="!rejecting" class="flex justify-end gap-2">
                                                <form method="POST" action="{{ route('admin.payments.approve', $p) }}">
                                                    @csrf
                                                    <button class="btn btn-success btn-sm">Approve</button>
                                                </form>
                                                <button type="button" class="btn btn-danger btn-sm" @click="rejecting = true">Reject</button>
                                            </div>
                                            <form x-show="rejecting" x-cloak method="POST" action="{{ route('admin.payments.reject', $p) }}" class="flex justify-end gap-2">
                                                @csrf
                                                <x-text-input name="note" placeholder="Reason" class="!w-44 !py-1.5 text-xs" required />
                                                <button class="btn btn-danger btn-sm">Confirm</button>
                                                <button type="button" class="btn btn-secondary btn-sm" @click="rejecting = false">Cancel</button>
                                            </form>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $payments->links() }}
        @endif
    </div>
</x-app-layout>
