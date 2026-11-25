{{-- Page header: logo, menu and "Get the app". On phones the links move into a small menu button
     (a <details> dropdown: works without JavaScript). $links: list of [href, label]. --}}
@include('landing._brand', isset($brandHref) ? ['href' => $brandHref] : [])
<nav class="menu" aria-label="{{ __('landing.nav_label') }}">
    @foreach ($links as [$href, $label])
        <a href="{{ $href }}">{{ $label }}</a>
    @endforeach
</nav>
@if ($cta ?? true)
    <a class="btn btn-sm" href="{{ $playUrl ?? '#download' }}" @if ($playUrl) rel="noopener" @endif>{{ __('landing.get_app') }}</a>
@endif
<details class="mmenu">
    <summary aria-label="{{ __('landing.nav_menu') }}"><svg class="ic" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg></summary>
    <nav class="mmenu-panel" aria-label="{{ __('landing.nav_label') }}">
        @foreach ($links as [$href, $label])
            <a href="{{ $href }}">{{ $label }}</a>
        @endforeach
    </nav>
</details>
