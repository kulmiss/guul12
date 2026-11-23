@extends('landing.layout')

@php($prefix = $locale === 'en' ? '' : '/'.$locale)

@section('title', __('landing.premium_title').' – Guul 12')

@section('header')
    @include('landing._brand', ['href' => $prefix === '' ? '/' : $prefix])
    <nav>
        <a href="{{ $prefix === '' ? '/' : $prefix }}">{{ __('landing.nav_home') }}</a>
        <a href="/privacy">{{ __('landing.nav_privacy') }}</a>
    </nav>
@endsection

@section('content')
    <h1>{{ __('landing.premium_title') }}</h1>
    <p>{{ __('landing.premium_intro') }}</p>

    <div class="grid">
        <div class="card">
            <h2>{{ __('landing.plan_term') }}</h2>
            <p class="amount">{{ __('landing.birr', ['amount' => number_format($termPrice)]) }}</p>
            <p class="note">{{ __('landing.plan_term_days', ['days' => $termDays]) }}</p>
        </div>
        <div class="card">
            <h2>{{ __('landing.plan_year') }}</h2>
            <p class="amount">{{ __('landing.birr', ['amount' => number_format($yearPrice)]) }}</p>
            <p class="note">{{ __('landing.plan_year_days', ['days' => $yearDays]) }}</p>
        </div>
    </div>

    <h2>{{ __('landing.how_title') }}</h2>
    <ol>
        @foreach (__('landing.how_steps') as $step)
            <li>{{ $step }}</li>
        @endforeach
    </ol>

    <h2>{{ __('landing.contact_title') }}</h2>
    @if ($whatsapp === null && $email === null)
        <p>{{ __('landing.contact_soon') }}</p>
    @else
        <ul>
            @if ($whatsapp !== null)
                <li><a href="https://wa.me/{{ $whatsapp }}" rel="noopener">{{ __('landing.whatsapp', ['number' => '+'.$whatsapp]) }}</a></li>
            @endif
            @if ($email !== null)
                <li><a href="mailto:{{ $email }}">{{ __('landing.email', ['email' => $email]) }}</a></li>
            @endif
        </ul>
    @endif
    <p class="note">{{ __('landing.payment_note') }}</p>
@endsection
