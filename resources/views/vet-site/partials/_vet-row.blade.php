{{-- One vet on the team step. $i is the index, or "__i__" inside the <template>. --}}
<div class="vet-row">
    <span class="vet-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(trim(preg_replace('/^(dr\.?|د\.?)\s*/iu', '', $vet['name'] ?? '')), 0, 1)) ?: '+' }}</span>
    <input class="input vet-name" name="vets[{{ $i }}][name]" maxlength="80" dir="auto" value="{{ $vet['name'] ?? '' }}"
           placeholder="{{ translate('messages.clinic_vet_name') }}" aria-label="{{ translate('messages.clinic_vet_name') }}">
    <input class="input vet-role" name="vets[{{ $i }}][role]" maxlength="80" dir="auto" value="{{ $vet['role'] ?? '' }}"
           placeholder="{{ translate('messages.clinic_vet_role') }}" aria-label="{{ translate('messages.clinic_vet_role') }}">
    <input class="input vet-years" type="number" min="0" max="70" inputmode="numeric" name="vets[{{ $i }}][years]" value="{{ $vet['years'] ?? '' }}"
           placeholder="{{ translate('messages.clinic_vet_years') }}" aria-label="{{ translate('messages.clinic_vet_years') }}">
    <button type="button" class="vet-remove" aria-label="{{ translate('messages.vs_remove') }}">@include('vet-site.partials._icon', ['name' => 'x'])</button>
</div>
