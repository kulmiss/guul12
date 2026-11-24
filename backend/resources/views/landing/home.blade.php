@extends('landing.layout')

@php($prefix = $locale === 'en' ? '' : '/'.$locale)

@section('preload')
    <link rel="preload" href="/img/landing/home.webp" as="image" type="image/webp" fetchpriority="high">
@endsection

@section('header')
    @include('landing._brand', ['href' => $prefix === '' ? '/' : $prefix])
    <nav class="menu" aria-label="{{ __('landing.nav_label') }}">
        <a href="#features">{{ __('landing.nav_features') }}</a>
        <a href="#subjects">{{ __('landing.nav_subjects') }}</a>
        <a href="{{ $prefix }}/premium">{{ __('landing.nav_premium') }}</a>
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
    @include('landing._compare', ['premiumLink' => $prefix.'/premium'])
    @include('landing._faq')
    @include('landing._final')
@endsection

@section('footer-links')
    <li><a href="{{ $prefix }}/premium">{{ __('landing.nav_premium') }}</a></li>
@endsection

@section('sticky')
    @include('landing._sticky')
@endsection
