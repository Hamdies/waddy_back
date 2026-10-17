@extends('layouts.landing.app')
@section('title', translate('messages.vet_apply_thanks_title'))

@section('content')
<section style="padding: 80px 16px; background: #f6f7f9;">
    <div style="max-width: 560px; margin: 0 auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 40px 28px; text-align: center;">
        <div style="width: 72px; height: 72px; border-radius: 50%; background: #e7f6f0; color: #0e9f6e; display: grid; place-items: center; margin: 0 auto 18px; font-size: 34px;">✓</div>
        <h1 style="font-size: 26px; font-weight: 700; margin-bottom: 12px;">{{ translate('messages.vet_apply_thanks_title') }}</h1>
        <p style="color: #6b7280; line-height: 1.7; margin-bottom: 28px;">{{ translate('messages.vet_apply_thanks_body') }}</p>
        <a href="{{ route('home') }}" class="cmn--btn" style="margin: 4px;">{{ translate('messages.vet_apply_back_home') }}</a>
        <a href="{{ route('vet-clinic.create') }}" style="display: inline-block; margin: 4px; padding: 10px 18px; color: #0e9f6e; font-weight: 600;">{{ translate('messages.vet_apply_another') }}</a>
    </div>
</section>
@endsection
