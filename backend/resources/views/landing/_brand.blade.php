{{-- The logo (if one is set in Nova → Settings → Branding) and the logo text. --}}
@if (isset($href))<a class="brand" href="{{ $href }}">@else<span class="brand">@endif
@if ($brand['logo'] !== null)<img src="{{ $brand['logo']['url'] }}" width="{{ $brand['logo']['width'] }}" height="{{ $brand['logo']['height'] }}" alt="">@endif{{ $brand['logo_text'] }}
@if (isset($href))</a>@else</span>@endif
