{{-- The logo (if one is set in Nova → Settings → Branding) or a letter mark, and the logo text. --}}
@if (isset($href))<a class="brand" href="{{ $href }}">@else<span class="brand">@endif
@if ($brand['logo'] !== null)<img src="{{ $brand['logo']['url'] }}" width="{{ $brand['logo']['width'] }}" height="{{ $brand['logo']['height'] }}" alt="">@else<span class="logo-mark" aria-hidden="true">{{ mb_substr($brand['logo_text'], 0, 1) }}</span>@endif{{ $brand['logo_text'] }}
@if (isset($href))</a>@else</span>@endif
