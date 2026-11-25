{{-- Real app screens with real lessons (app/test/golden/landing_screens_test.dart): one row inside
     the page width that scrolls sideways (swipe, trackpad, shift + wheel, arrow keys), scrollbar hidden. --}}
<section id="screens" class="sec" aria-labelledby="screens-title">
    <div class="wrap">
        <p class="kicker">{{ __('landing.screens_kicker') }}</p>
        <h2 id="screens-title">{{ __('landing.screens_title') }}</h2>
        <ul class="gallery" tabindex="0" aria-label="{{ __('landing.screens_label') }}">
            @foreach ($screens as $name)
                @php($caption = __('landing.screens.'.$name))
                <li class="shot"><figure class="frame"><img src="/img/landing/{{ $name }}.webp" width="540" height="1170" loading="lazy" decoding="async" alt="{{ __('landing.screens_alt', ['caption' => $caption]) }}"></figure><p class="cap">{{ $caption }}</p></li>
            @endforeach
        </ul>
        <p class="swipe" aria-hidden="true"><span>{{ __('landing.screens_swipe') }}</span></p>
    </div>
</section>
