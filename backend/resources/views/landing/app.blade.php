@extends('landing.layout')

{{-- Linked from the app and the summary PDFs: no prices, no way to pay and no link to the home
     page or the price page (Google Play payments policy, DECISIONS #3). A test checks every word. --}}
@section('title', __('landing.app_title'))

@section('preload')
    <link rel="preload" href="/img/landing/home.webp" as="image" type="image/webp" fetchpriority="high">
@endsection

@section('header')
    @include('landing._brand')
    <nav class="menu" aria-label="{{ __('landing.nav_label') }}">
        <a href="#features">{{ __('landing.nav_features') }}</a>
        <a href="#subjects">{{ __('landing.nav_subjects') }}</a>
        <a href="#faq">{{ __('landing.nav_faq') }}</a>
    </nav>
    <a class="btn btn-sm" href="{{ $playUrl ?? '#download' }}" @if ($playUrl) rel="noopener" @endif>{{ __('landing.get_app') }}</a>
@endsection

@section('content')
    @include('landing._hero')
    @include('landing._numbers')
    @include('landing._steps')
    @include('landing._features')
    @include('landing._screens')
    @include('landing._subjects')
    @include('landing._offline')
    @include('landing._reviews')
    @include('landing._compare', ['withPlans' => false])
    @include('landing._faq')
    @include('landing._final')
@endsection

@section('sticky')
    @include('landing._sticky')
@endsection
