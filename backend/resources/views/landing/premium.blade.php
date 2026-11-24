@extends('landing.layout')

{{-- For social media: the only page with prices and how to pay. Never linked from the app. --}}
@php($prefix = $locale === 'en' ? '' : '/'.$locale)
@php($icon = \App\Support\LandingIcons::class)

@section('title', __('landing.premium_title').' – Guul 12')

@section('header')
    @include('landing._brand', ['href' => $prefix === '' ? '/' : $prefix])
    <nav class="menu" aria-label="{{ __('landing.nav_label') }}">
        <a href="{{ $prefix === '' ? '/' : $prefix }}">{{ __('landing.nav_home') }}</a>
        <a href="#plans">{{ __('landing.plans_title') }}</a>
        <a href="#how">{{ __('landing.how_title') }}</a>
    </nav>
    <a class="btn btn-sm" href="#contact">{{ __('landing.contact_title') }}</a>
@endsection

@section('content')
    <section class="hero" aria-labelledby="premium-title">
        <div class="wrap page-hero">
            <p class="eyebrow">{{ __('landing.premium_title') }}</p>
            <h1 id="premium-title">{{ __('landing.premium_page_title') }}</h1>
            <p class="lead">{{ __('landing.premium_intro') }}</p>
            <ul class="cards cards-4">
                @foreach (__('landing.premium_benefits') as $i => [$title, $body])
                    <li class="card"><span class="chip-ic">{!! $icon::svg(['timer', 'offline', 'spark', 'shield'][$i] ?? 'check') !!}</span><h2 class="h3">{{ $title }}</h2><p>{{ $body }}</p></li>
                @endforeach
            </ul>
        </div>
        <svg class="wave" viewBox="0 0 1440 80" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path d="M0 40c240 40 480 40 720 10s480-40 720 0v30H0z"/></svg>
    </section>

    <section id="plans" class="sec" aria-labelledby="plans-title">
        <div class="wrap narrow">
            <h2 id="plans-title">{{ __('landing.plans_title') }}</h2>
            <div class="plans">
                <div class="card plan">
                    <h3>{{ __('landing.plan_term') }}</h3>
                    <p class="amount">{{ __('landing.birr', ['amount' => number_format($termPrice)]) }}</p>
                    <p>{{ __('landing.plan_term_days', ['days' => $termDays]) }} · {{ __('landing.plan_includes') }}</p>
                </div>
                <div class="card plan plan-best">
                    <span class="badge">{{ __('landing.plan_best') }}</span>
                    <h3>{{ __('landing.plan_year') }}</h3>
                    <p class="amount">{{ __('landing.birr', ['amount' => number_format($yearPrice)]) }}</p>
                    <p>{{ __('landing.plan_year_days', ['days' => $yearDays]) }} · {{ __('landing.plan_includes') }}</p>
                </div>
            </div>
        </div>
    </section>

    <section id="how" class="sec sec-white" aria-labelledby="how-title">
        <div class="wrap">
            <h2 id="how-title">{{ __('landing.how_title') }}</h2>
            <ol class="cards cards-4">
                @foreach (__('landing.how_steps') as $i => $step)
                    <li class="card"><span class="step-n" aria-hidden="true">{{ $i + 1 }}</span><p>{{ $step }}</p></li>
                @endforeach
            </ol>
        </div>
    </section>

    <section id="contact" class="sec" aria-labelledby="contact-title">
        <div class="wrap narrow">
            <div class="card">
                <h2 id="contact-title">{{ __('landing.contact_title') }}</h2>
                @if ($whatsapp === null && $telegram === null && $email === null)
                    <p>{{ __('landing.contact_soon') }}</p>
                @else
                    <p>{{ __('landing.contact_intro') }}</p>
                    <div class="contact-list">
                        @if ($whatsapp !== null)
                            <a class="btn" href="https://wa.me/{{ $whatsapp }}" rel="noopener">{!! $icon::svg('chat') !!}{{ __('landing.whatsapp', ['number' => '+'.$whatsapp]) }}</a>
                        @endif
                        @if ($telegram !== null)
                            <a class="btn btn-dark" href="https://t.me/{{ $telegram }}" rel="noopener">{!! $icon::svg('send') !!}{{ __('landing.telegram', ['name' => $telegram]) }}</a>
                        @endif
                        @if ($email !== null)
                            <a class="btn btn-dark" href="mailto:{{ $email }}">{!! $icon::svg('mail') !!}{{ __('landing.email', ['email' => $email]) }}</a>
                        @endif
                    </div>
                @endif
                <p class="note">{{ __('landing.payment_note') }}</p>
            </div>
        </div>
    </section>
@endsection

@section('footer-links')
    <li><a href="{{ $prefix === '' ? '/' : $prefix }}">{{ __('landing.nav_home') }}</a></li>
@endsection
