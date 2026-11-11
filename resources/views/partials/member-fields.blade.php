{{-- Shared member detail inputs. Pass $member to prefill (edit forms). --}}
@php $member ??= null; @endphp
<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <x-input-label for="name" value="Full name" />
        <x-text-input id="name" name="name" :value="old('name', $member?->name)" required autocomplete="off" />
        <x-input-error :messages="$errors->get('name')" />
    </div>
    <div>
        <x-input-label for="username" value="Username" />
        <x-text-input id="username" name="username" :value="old('username', $member?->username)" required autocomplete="off" />
        <x-input-error :messages="$errors->get('username')" />
    </div>
    <div>
        <x-input-label for="email" value="Email address" />
        <x-text-input id="email" type="email" name="email" :value="old('email', $member?->email)" required autocomplete="off" />
        <x-input-error :messages="$errors->get('email')" />
    </div>
    <div>
        <x-input-label for="phone" value="Phone / WhatsApp" />
        <x-text-input id="phone" type="tel" name="phone" :value="old('phone', $member?->phone)" required placeholder="+92 300 0000000" />
        <x-input-error :messages="$errors->get('phone')" />
    </div>
    <div>
        <x-input-label for="cnic" value="CNIC number" />
        <x-text-input id="cnic" name="cnic" :value="old('cnic', $member?->cnic)" required inputmode="numeric" maxlength="15" placeholder="35202-1234567-1" class="font-mono" />
        <x-input-error :messages="$errors->get('cnic')" />
    </div>
    <div>
        <x-input-label for="country" value="Country" />
        <x-text-input id="country" name="country" :value="old('country', $member?->country)" required />
        <x-input-error :messages="$errors->get('country')" />
    </div>
    <div>
        <x-input-label for="password" :value="$member ? 'New password (leave blank to keep)' : 'Password'" />
        <x-text-input id="password" type="password" name="password" :required="! $member" autocomplete="new-password" />
        <x-input-error :messages="$errors->get('password')" />
    </div>
    <div>
        <x-input-label for="password_confirmation" value="Confirm password" />
        <x-text-input id="password_confirmation" type="password" name="password_confirmation" :required="! $member" autocomplete="new-password" />
    </div>
</div>
