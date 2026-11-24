{{-- Big top part: title, store button, trust ticks, exam countdown and two app pictures. --}}
@php($icon = \App\Support\LandingIcons::class)
<section class="hero" aria-labelledby="hero-title">
    <div class="wrap hero-grid">
        <div>
            <p class="eyebrow">{{ $examYear !== null ? __('landing.hero_eyebrow_year', ['year' => $examYear]) : __('landing.hero_eyebrow') }}</p>
            <h1 id="hero-title">{{ __('landing.hero_title_a') }} <span class="hl">{{ __('landing.hero_title_b') }}</span></h1>
            <p class="lead">{{ __('landing.hero_body') }}</p>
            <div class="cta-row" id="download">
                @include('landing._download')
                <a class="btn btn-ghost" href="#how">{{ __('landing.hero_more') }}</a>
            </div>
            <ul class="chips">
                @foreach (__('landing.hero_chips') as $chip)
                    <li>{!! $icon::svg('tick') !!}{{ $chip }}</li>
                @endforeach
            </ul>
            @if ($examDays !== null)
                <p class="countdown">{!! trans_choice('landing.countdown', $examDays, ['count' => number_format($examDays)]) !!}</p>
            @endif
        </div>
        <div class="hero-art" aria-hidden="true">
            <figure class="phone p-back"><img src="/img/landing/progress.webp" width="540" height="1170" alt="" decoding="async"></figure>
            <figure class="phone p-front"><img src="/img/landing/home.webp" width="540" height="1170" alt="" fetchpriority="high"></figure>
            <span class="sticker s1">{{ __('landing.sticker_streak') }}</span>
            <span class="sticker s2">{{ __('landing.sticker_goal') }}</span>
        </div>
    </div>
    <svg class="wave" viewBox="0 0 1440 80" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path d="M0 40c240 40 480 40 720 10s480-40 720 0v30H0z"/></svg>
</section>
