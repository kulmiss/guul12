@if ($playUrl)
    <a class="button" href="{{ $playUrl }}" rel="noopener">{{ __('landing.download') }}</a>
@else
    <span class="soon">{{ __('landing.coming_soon') }}</span>
@endif
