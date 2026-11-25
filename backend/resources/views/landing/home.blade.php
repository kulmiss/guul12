@extends('landing.layout')

@php($prefix = $locale === 'en' ? '' : '/'.$locale)

@section('preload')
    <link rel="preload" href="/img/landing/home.webp" as="image" type="image/webp" fetchpriority="high">
@endsection

@section('header')
    @include('landing._nav', ['brandHref' => $prefix === '' ? '/' : $prefix, 'links' => [
        ['#grades', __('landing.nav_grades')],
        ['#features', __('landing.nav_features')],
        ['#subjects', __('landing.nav_subjects')],
        ['#premium', __('landing.nav_premium')],
        ['#faq', __('landing.nav_faq')],
    ]])
@endsection

@section('content')
    @include('landing._hero')
    @include('landing._numbers')
    @include('landing._grades')
    @include('landing._steps')
    @include('landing._features')
    @include('landing._screens')
    @include('landing._subjects')
    @include('landing._offline')
    @include('landing._reviews')
    @include('landing._compare', ['withPlans' => true])
    @include('landing._faq')
    @include('landing._final')
@endsection

@section('footer-links')
    <li><a href="#premium">{{ __('landing.nav_premium') }}</a></li>
@endsection

@section('sticky')
    @include('landing._sticky')
@endsection
