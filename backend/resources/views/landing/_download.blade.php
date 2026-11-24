{{-- The Google Play button, or "coming soon" while the store link is not set in Nova. --}}
@if ($playUrl)
    <a class="btn btn-store" href="{{ $playUrl }}" rel="noopener">{!! \App\Support\LandingIcons::svg('play') !!}<span><small>{{ __('landing.download_small') }}</small> Google Play</span></a>
@else
    <span class="btn btn-store soon">{!! \App\Support\LandingIcons::svg('play') !!}<span><small>{{ __('landing.coming_soon_small') }}</small> Google Play</span></span>
@endif
