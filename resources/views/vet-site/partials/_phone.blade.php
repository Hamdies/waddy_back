{{-- A phone showing the Pets app's "Vets near you" list.
     $live: the first card is filled from the application form by apply.js (data-pv hooks);
     otherwise it is a static sample. --}}
@php($live = $live ?? false)
<div class="phone" @if($live) id="pvPhone" @endif>
    <div class="phone-screen">
        <p class="phone-title">{{ translate('messages.vs_phone_title') }}</p>
        <p class="phone-sub">@include('vet-site.partials._icon', ['name' => 'navigation']) {{ translate('messages.vs_phone_sub') }}</p>
        <div class="chips">
            <span class="chip is-on">@include('vet-site.partials._icon', ['name' => 'cat']) {{ translate('messages.clinic_species_cat') }}</span>
            <span class="chip">@include('vet-site.partials._icon', ['name' => 'dog']) {{ translate('messages.clinic_species_dog') }}</span>
            <span class="chip">@include('vet-site.partials._icon', ['name' => 'siren']) 24h</span>
        </div>

        <article class="ccard">
            <div class="ccard-cover" @if($live) data-pv="cover" @endif>
                @include('vet-site.partials._mark', ['class' => 'ccard-mark'])
            </div>
            <div class="ccard-body">
                <div class="ccard-logo" @if($live) data-pv="logo" @endif>W</div>
                <div class="ccard-head">
                    <h3 class="ccard-name" @if($live) data-pv="name" @endif>{{ translate('messages.vs_sample_clinic') }}</h3>
                    <span class="ccard-open" @if($live) data-pv="open" hidden @endif>{{ translate('messages.vs_open_now') }}</span>
                </div>
                <div class="ccard-addr">
                    @include('vet-site.partials._icon', ['name' => 'map-pin'])
                    <span @if($live) data-pv="addr" @endif>{{ translate('messages.vs_sample_addr') }}</span>
                </div>
                <div class="ccard-tags" @if($live) data-pv="tags" @endif>
                    @unless($live)
                        <span class="ccard-tag">@include('vet-site.partials._icon', ['name' => 'cat']) {{ translate('messages.clinic_species_cat') }}</span>
                        <span class="ccard-tag">@include('vet-site.partials._icon', ['name' => 'dog']) {{ translate('messages.clinic_species_dog') }}</span>
                        <span class="ccard-tag">@include('vet-site.partials._icon', ['name' => 'syringe']) {{ translate('messages.clinic_service_vaccination') }}</span>
                    @endunless
                </div>
                <div class="ccard-vets" @if($live) data-pv="vets" hidden @endif>
                    <span class="ccard-avatars"><span>M</span><span>K</span></span>
                    <span>{{ translate('messages.vs_sample_vets') }}</span>
                </div>
                <div class="ccard-actions" @if($live) data-pv="actions" @endif>
                    <span class="ccard-call">@include('vet-site.partials._icon', ['name' => 'phone']) {{ translate('messages.vs_call') }}</span>
                    <span class="ccard-wa" @if($live) data-pv="wa" @endif>@include('vet-site.partials._icon', ['name' => 'message-circle']) WhatsApp</span>
                </div>
            </div>
        </article>

        <article class="ccard ccard--ghost" aria-hidden="true">
            <div class="ccard-cover"></div>
            <div class="ccard-body" style="padding-top: 6px;">
                <div class="skel w60"></div>
                <div class="skel w40"></div>
            </div>
        </article>
    </div>
</div>
