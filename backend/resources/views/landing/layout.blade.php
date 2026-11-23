<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('landing.title'))</title>
    <meta name="description" content="@yield('description', __('landing.description'))">
    <meta name="theme-color" content="{{ $brand['theme_color'] }}">
    <link rel="canonical" href="{{ url()->current() }}">
    @foreach ($alternates as $l => $path)
        <link rel="alternate" hreflang="{{ $l }}" href="{{ url($path) }}">
    @endforeach
    <meta property="og:title" content="@yield('title', __('landing.title'))">
    <meta property="og:description" content="@yield('description', __('landing.description'))">
    <meta property="og:type" content="website">
    @if ($isDraft)
        {{-- Draft translations are not final: keep them out of search results until reviewed. --}}
        <meta name="robots" content="noindex">
    @endif
    @php($fontFile = ['nunito' => 'nunito', 'noto_sans' => 'noto-sans', 'inter' => 'inter', 'poppins' => 'poppins'])
    <link rel="preload" href="/fonts/{{ $fontFile[$brand['fonts']['body']] }}-latin-400.woff2" as="font" type="font/woff2" crossorigin>
    <style>
        {{-- Colours, roundness and fonts come from Nova → Settings → Branding (checked for contrast before saving). --}}
        :root { {!! $brand['css'] !!} --card: #FFFFFF; }
        @foreach (array_unique([[$brand['fonts']['body'], 400], [$brand['fonts']['body'], 700], [$brand['fonts']['heading'], 800]], SORT_REGULAR) as [$font, $weight])
        @font-face { font-family: '{{ \App\Services\Branding\Branding::FONTS[$font][2] }}'; font-style: normal; font-weight: {{ $weight }}; font-display: swap; src: url('/fonts/{{ $fontFile[$font] }}-latin-{{ $weight }}.woff2') format('woff2'); }
        @endforeach
        * { box-sizing: border-box; }
        body { margin: 0; font-family: var(--font-body), system-ui, -apple-system, "Segoe UI", Roboto, "Noto Sans", "Noto Sans Ethiopic", sans-serif; background: var(--cream); color: var(--ink); line-height: 1.55; }
        a { color: var(--teal); }
        header, main, footer { max-width: 920px; margin: 0 auto; padding: 0 20px; }
        header { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; padding-top: 16px; padding-bottom: 16px; }
        h1, h2, h3, .brand { font-family: var(--font-heading), var(--font-body), system-ui, sans-serif; }
        .brand { display: inline-flex; align-items: center; gap: 8px; font-weight: 800; font-size: 1.25rem; color: var(--teal); text-decoration: none; }
        .brand img { height: 32px; width: auto; }
        nav a { margin-left: 16px; display: inline-block; padding: 8px 0; }
        .draft { background: #FDE7C8; color: #5A3200; padding: 10px 20px; text-align: center; }
        .hero { background: var(--teal); color: var(--on-teal); border-radius: calc(var(--radius-card) + 2px); padding: 32px 24px; margin: 8px 0 24px; }
        .hero h1 { margin: 0 0 12px; font-size: 1.9rem; line-height: 1.25; }
        .button { display: inline-block; background: #fff; color: var(--teal); font-weight: 700; padding: 12px 20px; border-radius: var(--radius-button); text-decoration: none; margin-top: 12px; }
        .soon { display: inline-block; margin-top: 12px; padding: 12px 0; font-weight: 700; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; }
        .card { background: var(--card); border-radius: var(--radius-card); padding: 18px; }
        .card h2, .card h3 { margin: 0 0 6px; font-size: 1.05rem; }
        .amount { font-size: 1.6rem; font-weight: 800; color: var(--teal); }
        ol li { margin-bottom: 8px; }
        .note { color: #4A5A5F; }
        footer { padding-top: 24px; padding-bottom: 32px; font-size: .9rem; color: #4A5A5F; }
        .langs a { margin-right: 12px; display: inline-block; padding: 8px 0; }
        h2 { margin-top: 32px; }
    </style>
</head>
<body>
@if ($isDraft)
    <p class="draft" role="note">{{ __('landing.draft_notice') }}</p>
@endif
<header>
    @yield('header')
</header>
<main>
    @yield('content')
</main>
<footer>
    @if ($alternates !== [])
        <p class="langs">
            @foreach ($alternates as $l => $path)
                <a href="{{ $path }}" hreflang="{{ $l }}" lang="{{ $l }}">{{ __('landing.lang_name', [], $l) }}</a>
            @endforeach
        </p>
    @endif
    @yield('footer-links')
    <p>{{ __('landing.footer', ['year' => $year]) }}</p>
</footer>
</body>
</html>
