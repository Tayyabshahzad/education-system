<x-app-layout title="Add Member" subtitle="Create a member under any sponsor. Leave the sponsor empty to add a top-level member.">
    <form method="POST" action="{{ route('admin.members.store') }}" class="grid gap-6 xl:grid-cols-3"
          x-data="{ amount: {{ (float) old('joining_amount', $fullFee) }} }">
        @csrf

        <div class="card xl:col-span-2">
            <div class="card-header"><h3 class="card-title">Member details</h3></div>
            <div class="card-body space-y-5">
                <div>
                    <x-input-label for="sponsor" value="Sponsor (username or referral code)" />
                    <x-text-input id="sponsor" name="sponsor" :value="old('sponsor', $sponsor?->username)" placeholder="Leave empty for a top-level member" />
                    <x-input-error :messages="$errors->get('sponsor')" />
                </div>
                @include('partials.member-fields')
            </div>
        </div>

        <div class="space-y-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Joining amount</h3></div>
                <div class="card-body space-y-4">
                    @include('partials.amount-picker', ['name' => 'joining_amount'])
                </div>
            </div>
            <div class="card">
                <div class="card-body space-y-4">
                    @can('payments.review')
                        <label class="flex items-start gap-3 text-sm">
                            <input type="checkbox" name="mark_paid" value="1" @checked(old('mark_paid')) class="mt-0.5 rounded border-ink-600 bg-ink-800 text-gold focus:ring-gold/40 focus:ring-offset-ink-950">
                            <span>
                                <span class="font-medium text-white">Payment already received</span>
                                <span class="block text-xs text-ink-400">Records the amount as approved, activates the account and releases upline commission if it reaches the full fee.</span>
                            </span>
                        </label>
                    @endcan
                    <button class="btn btn-primary w-full">Create member</button>
                </div>
            </div>
        </div>
    </form>
</x-app-layout>
