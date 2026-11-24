@extends('landing.layout')

{{-- Linked from the app and the summary PDFs: no prices and no link to the price page. --}}
@section('title', __('landing.app_title'))

@section('header')
    @include('landing._brand')
@endsection

@section('content')
    <section class="hero">
        <h1>{{ __('landing.app_title') }}</h1>
        <p>{{ __('landing.app_body') }}</p>
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
@endsection

@section('footer-links')
    <p><a href="/privacy">{{ __('landing.nav_privacy') }}</a></p>
@endsection
