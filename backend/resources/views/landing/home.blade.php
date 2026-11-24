@extends('landing.layout')

@php($prefix = $locale === 'en' ? '' : '/'.$locale)

@section('header')
    @include('landing._brand', ['href' => $prefix === '' ? '/' : $prefix])
    <nav>
        <a href="{{ $prefix }}/premium">{{ __('landing.nav_premium') }}</a>
        <a href="/privacy">{{ __('landing.nav_privacy') }}</a>
    </nav>
@endsection

@section('content')
    <section class="hero">
        <h1>{{ __('landing.hero_title') }}</h1>
        <p>{{ __('landing.hero_body') }}</p>
        @include('landing._download')
    </section>

    <h2>{{ __('landing.features_title') }}</h2>
    <div class="grid">
        @foreach (__('landing.features') as [$title, $body])
            <div class="card">
                <h3>{{ $title }}</h3>
                <p>{{ $body }}</p>
            </div>
        @endforeach
    </div>

    <h2>{{ __('landing.free_title') }}</h2>
    <p>{{ __('landing.free_body') }} <a href="{{ $prefix }}/premium">{{ __('landing.nav_premium') }}</a></p>
@endsection
