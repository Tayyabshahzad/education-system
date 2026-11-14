<x-app-layout title="Profile">
    <div class="grid max-w-5xl gap-6 xl:grid-cols-2">
        {{-- Profile picture --}}
        <div class="card card-body xl:col-span-2">
            <div class="flex flex-wrap items-center gap-5">
                <x-avatar :user="$user" size="h-20 w-20" />
                <div class="min-w-0 flex-1">
                    <h2 class="card-title">Profile picture</h2>
                    <p class="mt-1 text-sm text-ink-400">JPG, PNG or WEBP. It is cropped to a square and resized automatically.</p>
                    @error('avatar')<p class="mt-1.5 text-xs text-bear-400">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('profile.avatar') }}" enctype="multipart/form-data" x-data>
                        @csrf
                        <label class="btn btn-primary btn-sm cursor-pointer">
                            <x-icon name="upload" class="h-4 w-4" /> {{ $user->avatar_path ? 'Change photo' : 'Upload photo' }}
                            <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="$el.form.submit()">
                        </label>
                    </form>
                    @if ($user->avatar_path)
                        <form method="POST" action="{{ route('profile.avatar.destroy') }}">
                            @csrf @method('DELETE')
                            <button class="btn btn-secondary btn-sm">Remove</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="card card-body">
            @include('profile.partials.update-profile-information-form')

            <dl class="mt-6 grid gap-3 border-t border-ink-700 pt-5 text-sm sm:grid-cols-2">
                @foreach (['Username' => $user->username, 'Mobile' => $user->phone, 'CNIC' => $user->cnic, 'Referral code' => $user->referral_code] as $label => $value)
                    @if ($value)
                        <div><dt class="text-xs text-ink-400">{{ $label }}</dt><dd class="font-mono text-ink-100">{{ $value }}</dd></div>
                    @endif
                @endforeach
            </dl>
            <p class="mt-3 text-xs text-ink-400">To change your username, mobile number or CNIC, please contact support.</p>
        </div>

        <div class="card card-body">
            @include('profile.partials.update-password-form')
        </div>

        @if ($user->isMember())
            <div class="card xl:col-span-2">
                <div class="card-header">
                    <h2 class="card-title">Payout details</h2>
                    <span class="text-xs text-ink-400">Used for your withdrawal requests</span>
                </div>
                <form method="POST" action="{{ route('profile.payout') }}" class="card-body grid gap-4 sm:grid-cols-2">
                    @csrf @method('PUT')
                    @include('partials.payout-fields', ['source' => $user])
                    <div class="sm:col-span-2"><button class="btn btn-primary">Save payout details</button></div>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
