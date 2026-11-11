{{--
    Bank / wallet payout inputs. $source = the user whose saved details pre-fill the form.
    Expects a 2-column grid parent.
--}}
@php $methods = \App\Models\Setting::withdrawalMethods(); @endphp
<div>
    <x-input-label for="method" value="Payout method" />
    <select id="method" name="method" class="field-select" required>
        @foreach ($methods as $m)
            <option value="{{ $m }}" @selected(old('method', $source->payout_method) === $m)>{{ $m }}</option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('method')" />
</div>
<div>
    <x-input-label for="bank_name" value="Bank / wallet name" />
    <x-text-input id="bank_name" name="bank_name" :value="old('bank_name', $source->bank_name)" placeholder="e.g. Meezan Bank, Easypaisa" />
    <x-input-error :messages="$errors->get('bank_name')" />
</div>
<div>
    <x-input-label for="account_title" value="Account title" />
    <x-text-input id="account_title" name="account_title" :value="old('account_title', $source->account_title)" required placeholder="Name on the account" />
    <x-input-error :messages="$errors->get('account_title')" />
</div>
<div>
    <x-input-label for="account_number" value="Account number / IBAN / wallet address" />
    <x-text-input id="account_number" name="account_number" :value="old('account_number', $source->account_number)" required class="font-mono" />
    <x-input-error :messages="$errors->get('account_number')" />
</div>
