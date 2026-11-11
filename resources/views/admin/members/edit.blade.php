<x-app-layout :title="'Edit '.$member->username">
    <form method="POST" action="{{ route('admin.members.update', $member) }}" class="max-w-3xl space-y-6">
        @csrf @method('PUT')

        <div class="card">
            <div class="card-header"><h3 class="card-title">Member details</h3></div>
            <div class="card-body">@include('partials.member-fields', ['member' => $member])</div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Placement</h3></div>
            <div class="card-body">
                <x-input-label for="sponsor" value="Sponsor (username or referral code)" />
                <x-text-input id="sponsor" name="sponsor" :value="old('sponsor', $member->sponsor?->username)" placeholder="Empty = top-level member" />
                <p class="field-help">Moving a member moves their whole team with them. Commissions already paid are not changed.</p>
                <x-input-error :messages="$errors->get('sponsor')" />
            </div>
        </div>

        <div class="flex gap-3">
            <button class="btn btn-primary">Save changes</button>
            <a href="{{ route('admin.members.show', $member) }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</x-app-layout>
