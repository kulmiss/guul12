{{-- Phones only: a "get the app" button that stays at the bottom while scrolling. --}}
<div class="sticky-cta">
    @if ($playUrl)
        <a class="btn" href="{{ $playUrl }}" rel="noopener">{{ __('landing.get_app_free') }}</a>
    @else
        <a class="btn" href="#download">{{ __('landing.get_app_free') }}</a>
    @endif
</div>
