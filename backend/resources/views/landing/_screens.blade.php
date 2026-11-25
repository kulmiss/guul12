{{-- Real app screens with real lessons (app/test/golden/landing_screens_test.dart). --}}
<section id="screens" class="sec screens" aria-labelledby="screens-title">
    <div class="wrap">
        <p class="kicker">{{ __('landing.screens_kicker') }}</p>
        <h2 id="screens-title">{{ __('landing.screens_title') }}</h2>
    </div>
    <ul class="gallery" tabindex="0" aria-label="{{ __('landing.screens_label') }}">
        @foreach ($screens as $name)
            @php($caption = __('landing.screens.'.$name))
            <li class="shot"><figure class="phone"><img src="/img/landing/{{ $name }}.webp" width="540" height="1170" loading="lazy" decoding="async" alt="{{ __('landing.screens_alt', ['caption' => $caption]) }}"></figure><p class="cap">{{ $caption }}</p></li>
        @endforeach
    </ul>
    <p class="swipe" aria-hidden="true">{{ __('landing.screens_swipe') }}</p>
</section>
