<x-app-layout title="Profile">
    <div class="grid max-w-5xl gap-6 xl:grid-cols-2">
        <div class="card card-body">
            @include('profile.partials.update-profile-information-form')
        </div>
        <div class="card card-body">
            @include('profile.partials.update-password-form')
        </div>
    </div>
</x-app-layout>
