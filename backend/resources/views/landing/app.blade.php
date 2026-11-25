@extends('landing.layout')

{{-- Linked from the app and the summary PDFs: no prices, no way to pay and no link to the home
     page or the price page (Google Play payments policy, DECISIONS #3). A test checks every word. --}}
@section('title', __('landing.app_title'))

@section('preload')
    <link rel="preload" href="/img/landing/home.webp" as="image" type="image/webp" fetchpriority="high">
@endsection

@section('header')
    @include('landing._nav', ['links' => [
        ['#grades', __('landing.nav_grades')],
        ['#features', __('landing.nav_features')],
        ['#subjects', __('landing.nav_subjects')],
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
    @include('landing._compare', ['withPlans' => false])
    @include('landing._faq')
    @include('landing._final')
@endsection

@section('sticky')
    @include('landing._sticky')
@endsection
