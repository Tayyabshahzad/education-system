<x-app-layout title="Add User" subtitle="Choose the role first. Members are always placed under a sponsor; staff and teachers have no sponsor or referral link.">
    <form method="POST" action="{{ route('admin.users.store') }}" x-data="{ role: @js($role), amount: {{ (float) old('joining_amount', $fullFee) }} }" class="space-y-6">
        @csrf

        {{-- Role picker --}}
        <div class="card">
            <div class="card-header"><h3 class="card-title">Role</h3></div>
            <div class="card-body">
                @php
                    $roleHelp = [
                        'member' => 'Joins the referral tree, pays fees, earns commission.',
                        'staff' => 'Works in the admin panel with the access you tick below.',
                        'teacher' => 'Sees their own batches, schedules and students.',
                        'super-admin' => 'Full access to everything, including settings.',
                    ];
                @endphp
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($roles as $key => $label)
                        <label class="cursor-pointer">
                            <input type="radio" name="role" value="{{ $key }}" x-model="role" class="peer sr-only">
                            <span class="block h-full rounded-xl border border-ink-700 bg-ink-800/60 p-4 transition peer-checked:border-gold peer-checked:bg-gold/5 peer-focus-visible:ring-2 peer-focus-visible:ring-gold/50">
                                <span class="block font-medium text-white">{{ $label }}</span>
                                <span class="mt-1 block text-xs text-ink-400">{{ $roleHelp[$key] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('role')" />
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="card xl:col-span-2">
                <div class="card-header"><h3 class="card-title">Account details</h3></div>
                <div class="card-body space-y-5">
                    {{-- Sponsor: members only --}}
                    <div x-show="role === 'member'">
                        <x-input-label for="sponsor" :value="$sponsorRequired ? 'Sponsor (username or referral code)' : 'Sponsor (optional for the first member)'" />
                        <x-text-input id="sponsor" name="sponsor" :value="old('sponsor', $sponsor?->username)" placeholder="e.g. member1 or GVEAB12CD" x-bind:required="role === 'member' && {{ $sponsorRequired ? 'true' : 'false' }}" />
                        <p class="field-help">The new member is placed directly under this member. You can choose anyone in the tree.</p>
                        <x-input-error :messages="$errors->get('sponsor')" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="name" value="Full name" />
                            <x-text-input id="name" name="name" :value="old('name')" required autocomplete="off" />
                            <x-input-error :messages="$errors->get('name')" />
                        </div>
                        <div>
                            <x-input-label for="username" value="Username" />
                            <x-text-input id="username" name="username" :value="old('username')" required autocomplete="off" />
                            <x-input-error :messages="$errors->get('username')" />
                        </div>
                        <div>
                            <x-input-label for="email" value="Email (login)" />
                            <x-text-input id="email" type="email" name="email" :value="old('email')" required autocomplete="off" />
                            <x-input-error :messages="$errors->get('email')" />
                        </div>
                        <div>
                            <x-input-label for="phone" value="Phone / WhatsApp" />
                            <x-text-input id="phone" type="tel" name="phone" :value="old('phone')" x-bind:required="role === 'member'" />
                            <x-input-error :messages="$errors->get('phone')" />
                        </div>
                        <div x-show="role === 'member'">
                            <x-input-label for="country" value="Country" />
                            <x-text-input id="country" name="country" :value="old('country')" x-bind:required="role === 'member'" />
                            <x-input-error :messages="$errors->get('country')" />
                        </div>
                        <div x-show="role === 'member'" class="hidden sm:block"></div>
                        <div>
                            <x-input-label for="password" value="Password" />
                            <x-text-input id="password" type="password" name="password" required autocomplete="new-password" />
                            <x-input-error :messages="$errors->get('password')" />
                        </div>
                        <div>
                            <x-input-label for="password_confirmation" value="Confirm password" />
                            <x-text-input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                {{-- Member: amount + payment --}}
                <template x-if="role === 'member'">
                    <div class="space-y-6">
                        <div class="card">
                            <div class="card-header"><h3 class="card-title">Joining amount</h3></div>
                            <div class="card-body space-y-4">
                                @include('partials.amount-picker', ['name' => 'joining_amount'])
                            </div>
                        </div>
                        @can('payments.review')
                            <div class="card">
                                <div class="card-body">
                                    <label class="flex items-start gap-3 text-sm">
                                        <input type="checkbox" name="mark_paid" value="1" @checked(old('mark_paid')) class="mt-0.5 rounded border-ink-600 bg-ink-800 text-gold focus:ring-gold/40 focus:ring-offset-ink-950">
                                        <span>
                                            <span class="font-medium text-white">Payment already received</span>
                                            <span class="block text-xs text-ink-400">Records the amount as approved and activates the account. If unticked, the member logs in and uploads their payment screenshot.</span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        @endcan
                    </div>
                </template>

                {{-- Staff: permissions --}}
                <template x-if="role === 'staff'">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Access</h3></div>
                        <div class="card-body space-y-4">
                            @foreach (\App\Support\Permissions::GROUPS as $group => $perms)
                                <fieldset>
                                    <legend class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-ink-400">{{ $group }}</legend>
                                    @foreach ($perms as $key => $label)
                                        <label class="flex items-center gap-2.5 py-1 text-sm text-ink-200">
                                            <input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key, $granted, true))
                                                   class="rounded border-ink-600 bg-ink-800 text-gold focus:ring-gold/40 focus:ring-offset-ink-950">
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </fieldset>
                            @endforeach
                        </div>
                    </div>
                </template>

                <template x-if="role === 'teacher' || role === 'super-admin'">
                    <div class="card card-body text-sm text-ink-400">
                        <span x-show="role === 'teacher'">After creating the teacher, assign them to a batch from <span class="text-white">Academy → Batches</span>.</span>
                        <span x-show="role === 'super-admin'" class="text-bear-400">Super admins can change fees, staff access and every record. Only create one for people you fully trust.</span>
                    </div>
                </template>

                <button class="btn btn-primary w-full">Create <span x-text="{ member: 'member', staff: 'staff account', teacher: 'teacher', 'super-admin': 'super admin' }[role]">user</span></button>
            </div>
        </div>
    </form>
</x-app-layout>
