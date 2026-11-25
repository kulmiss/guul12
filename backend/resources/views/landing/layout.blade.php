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
    {{-- All three font files the first screen uses, so text does not jump when a font arrives late. --}}
    @foreach (array_unique([$fontFile[$brand['fonts']['body']].'-latin-400', $fontFile[$brand['fonts']['body']].'-latin-700', $fontFile[$brand['fonts']['heading']].'-latin-800']) as $preload)
        <link rel="preload" href="/fonts/{{ $preload }}.woff2" as="font" type="font/woff2" crossorigin>
    @endforeach
    @yield('preload')
    <style>
        {{-- Colours, roundness and fonts come from Nova → Settings → Branding. Every text colour is
             checked for contrast against its background (Branding::cssVariables and its tests). --}}
        :root { {!! $brand['css'] !!} --card: #FFFFFF; }
        @foreach (array_unique([[$brand['fonts']['body'], 400], [$brand['fonts']['body'], 700], [$brand['fonts']['heading'], 800]], SORT_REGULAR) as [$font, $weight])
        @font-face { font-family: '{{ \App\Services\Branding\Branding::FONTS[$font][2] }}'; font-style: normal; font-weight: {{ $weight }}; font-display: swap; src: url('/fonts/{{ $fontFile[$font] }}-latin-{{ $weight }}.woff2') format('woff2'); }
        @endforeach
        *{box-sizing:border-box}
        html{scroll-behavior:smooth;scroll-padding-top:80px}
        body{margin:0;font-family:var(--font-body),system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans","Noto Sans Ethiopic",sans-serif;background:var(--cream);color:var(--ink);line-height:1.55;-webkit-font-smoothing:antialiased}
        img{max-width:100%;height:auto;display:block}
        a{color:var(--teal)}
        h1,h2,h3,.brand{font-family:var(--font-heading),var(--font-body),system-ui,sans-serif}
        h1{font-size:3.2rem;line-height:1.1;margin:0 0 18px;letter-spacing:-.02em}
        h2{font-size:2.1rem;line-height:1.18;margin:0 0 14px;letter-spacing:-.01em}
        h3{margin:0 0 6px;font-size:1.1rem}
        p{margin:0 0 12px}
        .wrap{max-width:1160px;margin:0 auto;padding:0 20px}
        .narrow{max-width:800px}
        .ic{width:24px;height:24px;flex:none}
        .skip{position:absolute;left:-9999px;top:8px;background:#fff;color:#000;padding:10px 14px;border-radius:8px;z-index:20}
        .skip:focus{left:12px}
        :focus-visible{outline:3px solid var(--accent);outline-offset:3px}
        .draft{background:#FDE7C8;color:#5A3200;padding:10px 20px;text-align:center;margin:0}

        /* Header */
        .top{position:sticky;top:0;z-index:10;background:var(--deep);color:var(--on-deep);box-shadow:0 1px 0 rgba(255,255,255,.08),0 8px 24px rgba(0,0,0,.12)}
        .nav{display:flex;align-items:center;gap:18px;min-height:68px;flex-wrap:wrap}
        .brand{display:inline-flex;align-items:center;gap:10px;font-weight:800;font-size:1.3rem;color:var(--on-deep);text-decoration:none}
        .brand img{height:32px;width:auto}
        .logo-mark{display:grid;place-items:center;width:36px;height:36px;border-radius:10px;background:var(--accent);color:var(--on-accent);font-weight:800}
        .menu{margin-left:auto;display:flex;gap:4px;flex-wrap:wrap}
        .menu a{color:var(--on-deep);text-decoration:none;font-weight:700;padding:10px 14px;border-radius:99px;transition:background-color .2s}
        .menu a:hover{background:var(--deep-light);text-decoration:underline}

        /* Buttons */
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;font-weight:800;text-decoration:none;padding:14px 22px;border-radius:var(--radius-button);line-height:1.2;background:var(--accent);color:var(--on-accent);border:2px solid var(--accent);min-height:48px}
        .btn{box-shadow:0 8px 20px rgba(0,0,0,.18);transition:transform .2s,box-shadow .2s}
        .btn:hover{transform:translateY(-2px);box-shadow:0 12px 26px rgba(0,0,0,.24)}
        .btn:active{transform:translateY(0)}
        .btn-sm{padding:9px 16px;min-height:44px}
        .btn-ghost{background:transparent;color:var(--on-deep);border-color:var(--on-deep)}
        .btn-dark{background:var(--teal);color:var(--on-teal);border-color:var(--teal)}
        .btn-store span{display:flex;flex-direction:column;align-items:flex-start;font-size:1.15rem}
        .btn-store small{font-size:.75rem;font-weight:700}
        .soon{cursor:default}
        .soon:hover{transform:none;box-shadow:0 8px 20px rgba(0,0,0,.18)}

        /* Hero */
        .hero{position:relative;overflow:hidden;background:linear-gradient(135deg,var(--deep),var(--deep-light));color:var(--on-deep)}
        .hero::before{content:"";position:absolute;width:760px;height:760px;right:-200px;top:-300px;border-radius:50%;background:radial-gradient(closest-side,var(--accent),transparent);opacity:.22;pointer-events:none}
        .hero-grid{position:relative;display:grid;grid-template-columns:1.1fr .9fr;gap:40px;align-items:center;padding-top:56px;padding-bottom:96px}
        .eyebrow{display:inline-flex;align-items:center;gap:8px;font-weight:800;font-size:.92rem;padding:6px 14px;border-radius:99px;margin:0 0 18px;background:var(--deep-light);border:1px solid var(--on-deep)}
        .eyebrow::before{content:"";width:8px;height:8px;border-radius:50%;background:var(--accent)}
        .hl{color:var(--highlight)}
        .lead{font-size:1.18rem;margin:0 0 26px;max-width:580px}
        .cta-row{display:flex;flex-wrap:wrap;gap:12px;align-items:center}
        .chips{list-style:none;padding:0;margin:22px 0 0;display:flex;flex-wrap:wrap;gap:8px}
        .chips li{display:inline-flex;align-items:center;gap:6px;font-weight:700;font-size:.92rem;padding:6px 12px;border-radius:99px;background:var(--deep-light);border:1px solid var(--on-deep)}
        .chips .ic{width:16px;height:16px}
        .countdown{margin:18px 0 0}
        .countdown strong{color:var(--highlight)}
        .hero::after{content:"";position:absolute;inset:0;background-image:radial-gradient(rgba(255,255,255,.09) 1px,transparent 1px);background-size:22px 22px;mask-image:linear-gradient(90deg,transparent,#000 60%);pointer-events:none}
        .hero-grid{z-index:1}
        .hero-art{position:relative;height:540px}
        .p-front img{animation:float 6s ease-in-out infinite}
        .p-back img{animation:float 6s ease-in-out -3s infinite}
        @keyframes float{50%{transform:translateY(-10px)}}
        .phone{margin:0;border-radius:34px;padding:8px;background:#10181B;box-shadow:0 30px 60px rgba(0,0,0,.35)}
        .phone img{border-radius:27px;width:100%}
        .hero-art .phone{position:absolute;width:250px}
        .p-front{right:150px;top:10px;z-index:2;transform:rotate(-4deg)}
        .p-back{right:0;top:70px;transform:rotate(6deg)}
        .sticker{position:absolute;z-index:3;font-weight:800;font-size:.92rem;padding:8px 12px;border-radius:12px;background:#fff;color:#1B2B30;box-shadow:0 10px 24px rgba(0,0,0,.2)}
        .s1{left:-4px;top:120px}
        .s2{right:30px;bottom:30px}
        .wave{position:absolute;left:0;bottom:-1px;width:100%;height:60px;fill:var(--cream)}

        /* Numbers */
        .numbers{position:relative;z-index:2;margin-top:-34px}
        .num-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;list-style:none;padding:0;margin:0}
        .num{text-align:center;padding:22px 10px;border-radius:var(--radius-card);background:var(--card);box-shadow:0 12px 30px rgba(0,0,0,.08);border-top:4px solid var(--accent)}
        .num strong{display:block;font-size:2.4rem;font-weight:800;line-height:1.05;color:var(--teal);font-family:var(--font-heading),var(--font-body),sans-serif}
        .num span{font-weight:700}

        /* Sections */
        .sec{padding:84px 0}
        /* Parts below the first screen are laid out only when they come near (faster first paint on slow phones). */
        main>section:not(.hero):not(.numbers){content-visibility:auto;contain-intrinsic-size:auto 900px}
        .sec-white{background:var(--card)}
        .kicker{font-weight:800;text-transform:uppercase;letter-spacing:.12em;font-size:.82rem;margin:0 0 8px;color:var(--orange)}
        .sec>.wrap>.kicker,.sec>.wrap>h2,.sec>.wrap>.sub{text-align:center;margin-left:auto;margin-right:auto;max-width:760px}
        .sec>.wrap>h2::after{content:"";display:block;width:56px;height:4px;border-radius:4px;background:var(--accent);margin:14px auto 0}
        .sub{margin:-4px 0 26px;font-size:1.08rem}
        .cards{display:grid;gap:18px;margin-top:30px;padding:0;list-style:none}
        .cards-3{grid-template-columns:repeat(3,1fr)}
        .cards-4{grid-template-columns:repeat(4,1fr)}
        .cards-5{grid-template-columns:repeat(5,1fr)}
        .card.grade-card{text-align:center;border-top:4px solid var(--teal)}
        .card.grade-card:last-child{border-top-color:var(--accent)}
        .grade-n{display:grid;place-items:center;width:64px;height:64px;margin:0 auto 14px;border-radius:50%;font-size:1.7rem;font-weight:800;background:var(--teal);color:var(--on-teal);box-shadow:0 0 0 6px var(--tint);font-family:var(--font-heading),var(--font-body),sans-serif}
        .card{background:var(--card);color:var(--ink);border-radius:var(--radius-card);padding:24px;box-shadow:0 10px 30px rgba(0,0,0,.06);border:1px solid rgba(0,0,0,.04);transition:transform .25s,box-shadow .25s}
        .card:hover,.subj:hover{transform:translateY(-4px);box-shadow:0 18px 40px rgba(0,0,0,.1)}
        .sec-white .card{background:var(--cream);box-shadow:none}
        .card p{margin:0}
        .step-n{display:grid;place-items:center;width:48px;height:48px;border-radius:50%;font-weight:800;font-size:1.25rem;margin-bottom:14px;background:var(--teal);color:var(--on-teal);box-shadow:0 0 0 6px var(--tint)}
        .chip-ic{display:grid;place-items:center;width:48px;height:48px;border-radius:14px;margin-bottom:14px;background:var(--tint);color:var(--teal)}
        .alt .chip-ic{background:var(--accent-tint);color:var(--ink)}

        /* App pictures */
        .screens{background:linear-gradient(180deg,var(--deep),var(--deep-light));color:var(--on-deep)}
        .screens .kicker{color:var(--highlight)}
        .gallery{display:flex;flex-wrap:wrap;justify-content:center;gap:34px 26px;padding:34px 20px 10px;list-style:none;margin:0 auto;max-width:1240px}
        .gallery:focus-visible{outline-offset:-3px}
        .shot{flex:0 0 200px;margin:0;text-align:center}
        .shot .phone{transition:transform .3s}
        .shot:hover .phone{transform:translateY(-6px) rotate(-1.5deg)}
        .swipe{display:none}
        .shot .cap{font-weight:800;margin:12px 0 0}

        /* Subjects */
        .subj-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;list-style:none;padding:0;margin:0}
        .subj{display:flex;gap:14px;align-items:center;padding:16px 18px;border-radius:var(--radius-card);background:var(--card);box-shadow:0 6px 20px rgba(0,0,0,.05);border:1px solid rgba(0,0,0,.04);transition:transform .25s,box-shadow .25s}
        .subj .chip-ic{margin:0;flex:none}
        .subj h3{margin:0}
        .subj p{margin:0;font-size:.95rem}

        /* Offline band */
        .band{padding:70px 0;background:var(--accent);color:var(--on-accent)}
        .band-grid{display:grid;grid-template-columns:1.3fr .7fr;gap:40px;align-items:center}
        .band p{font-size:1.1rem;margin:0}
        .band-list{list-style:none;padding:0;margin:0;display:grid;gap:12px}
        .band-list li{display:flex;gap:12px;align-items:center;font-weight:800;padding:14px 18px;border-radius:16px;background:#fff;color:#1B2B30}

        /* Free and Premium table */
        .table-card{margin-top:28px;border-radius:var(--radius-card);overflow:hidden;background:var(--card);box-shadow:0 10px 30px rgba(0,0,0,.08)}
        .cmp{width:100%;border-collapse:collapse}
        .cmp th,.cmp td{padding:14px 18px;text-align:left}
        .cmp thead th{background:var(--teal);color:var(--on-teal)}
        .cmp thead th.pcol{background:var(--accent);color:var(--on-accent)}
        .cmp td,.cmp thead th+th{text-align:center;width:120px}
        .cmp tbody th{font-weight:600}
        .cmp tbody tr:nth-child(odd){background:var(--cream)}
        .cmp tbody td.pcol{background:var(--accent-tint)}
        .mark{width:22px;height:22px;display:inline-block;vertical-align:middle}

        /* Reviews */
        .rev{margin:0;position:relative}
        .stars{display:flex;gap:2px;margin-bottom:10px;color:var(--orange)}
        .stars .ic{width:18px;height:18px;fill:currentColor}
        .stars .off{opacity:.25}
        .rev blockquote{margin:0 0 12px;font-size:1.05rem}

        /* FAQ */
        .qa{border-radius:16px;margin-bottom:12px;padding:0 20px;background:var(--card)}
        .qa summary{cursor:pointer;font-weight:800;font-size:1.08rem;padding:18px 0;list-style:none;display:flex;justify-content:space-between;gap:12px}
        .qa summary::-webkit-details-marker{display:none}
        .qa summary::after{content:"+";font-size:1.4rem;line-height:1}
        .qa[open] summary::after{content:"–"}
        .qa p{margin:0;padding-bottom:18px}

        /* Final call and footer */
        .final{padding:20px 0 90px}
        .final-box{text-align:center;padding:56px 24px;border-radius:28px;background:linear-gradient(135deg,var(--deep),var(--deep-light));color:var(--on-deep);box-shadow:inset 0 -6px 0 var(--accent)}
        .final-box p{font-size:1.15rem;margin:0 0 24px}
        .foot{background:var(--deep);color:var(--on-deep);padding:50px 0 30px}
        .foot a{color:var(--on-deep)}
        .foot-grid{display:grid;grid-template-columns:1.4fr 1fr 1fr;gap:30px;align-items:start}
        .foot h2{font-size:1rem;margin:0 0 10px}
        .foot ul{list-style:none;padding:0;margin:0;display:grid;gap:4px}
        .foot li a{display:inline-block;padding:6px 0;font-weight:700}
        .legal-line{margin-top:30px;padding-top:20px;border-top:1px solid var(--on-deep);font-size:.9rem}
        .sticky-cta{display:none}

        /* Premium part of the home page */
        .sub-title{font-size:1.5rem;margin:48px 0 0;text-align:center}
        .plan h4{margin:0;font-size:1.1rem}
        .contact{margin-top:30px}
        .contact h3{font-size:1.5rem;margin-bottom:8px}
        .card .note{margin-top:18px}
        .plans{display:grid;grid-template-columns:repeat(2,1fr);gap:20px;margin-top:30px}
        .plan{position:relative;text-align:center;padding:30px 24px}
        .plan .amount{font-size:2.6rem;font-weight:800;color:var(--teal);margin:6px 0;font-family:var(--font-heading),var(--font-body),sans-serif}
        .badge{position:absolute;top:-12px;left:50%;transform:translateX(-50%);background:var(--accent);color:var(--on-accent);font-weight:800;font-size:.85rem;padding:4px 12px;border-radius:99px}
        .plan-best{outline:3px solid var(--accent);box-shadow:0 20px 44px rgba(0,0,0,.14)}
        .contact-list{display:flex;flex-wrap:wrap;gap:12px;margin-top:18px}
        .note{font-weight:700;margin:18px 0 0}

        /* Legal pages */
        .doc{background:var(--card);border-radius:var(--radius-card);padding:32px;margin:40px auto 60px;box-shadow:0 10px 30px rgba(0,0,0,.06)}
        .doc h1{font-size:2.2rem}
        .doc h2{font-size:1.4rem;margin-top:28px}

        @media (max-width:900px){
            h1{font-size:2.3rem}
            h2{font-size:1.7rem}
            .top{position:static}
            .menu{order:3;margin-left:0;width:100%;justify-content:center;padding-bottom:8px}
            .menu a{padding:8px 12px}
            .nav .btn-sm{order:2;margin-left:auto}
            .hero-grid,.band-grid{grid-template-columns:1fr}
            .hero-grid{padding-top:28px;padding-bottom:80px}
            .hero-art{height:420px;max-width:380px;margin:0 auto;width:100%}
            .hero-art .phone{width:190px}
            .p-front{right:auto;left:20px}
            .p-back{right:10px}
            .num-grid{grid-template-columns:repeat(2,1fr)}
            .num:last-child:nth-child(odd){grid-column:span 2}
            .cards-3,.cards-4{grid-template-columns:1fr 1fr}
            .cards-5{grid-template-columns:repeat(3,1fr)}
            .subj-grid{grid-template-columns:1fr}
            .foot-grid{grid-template-columns:1fr}
            .sec{padding:60px 0}
            .gallery{flex-wrap:nowrap;justify-content:flex-start;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none;padding:30px 24px 16px;mask-image:linear-gradient(90deg,transparent,#000 24px,#000 calc(100% - 24px),transparent)}
            .gallery::-webkit-scrollbar{display:none}
            .shot{flex-basis:190px;scroll-snap-align:center}
            .swipe{display:block;text-align:center;font-weight:700;margin:0;padding-bottom:6px}
            .cmp th,.cmp td{padding:12px 10px}
            .cmp td,.cmp thead th+th{width:72px}
            .plans{grid-template-columns:1fr}
            .sticky-cta{display:block;position:sticky;bottom:0;z-index:9;padding:10px 16px;background:var(--cream);box-shadow:0 -6px 20px rgba(0,0,0,.08)}
            .sticky-cta .btn{width:100%}
            .doc{padding:22px}
        }
        @media (max-width:560px){
            .cards-3,.cards-4,.cards-5{grid-template-columns:1fr}
            .cards-5 .card{display:grid;grid-template-columns:auto 1fr;column-gap:14px;text-align:left;padding:18px}
            .cards-5 .grade-n{grid-row:span 2;margin:0;width:52px;height:52px;font-size:1.4rem}
            /* Phones: icon or number on the left, text on the right, so the lists stay short. */
            .cards-4 .card{display:grid;grid-template-columns:auto 1fr;column-gap:14px;align-items:start;padding:18px}
            .cards-4 .card>.chip-ic,.cards-4 .card>.step-n{grid-row:span 2;margin:0}
            .cards-4 .card>h3{align-self:end}
        }
        @media (prefers-reduced-motion:reduce){
            html{scroll-behavior:auto}
            *,*::before,*::after{animation:none!important;transition:none!important}
            .card:hover,.subj:hover,.btn:hover,.shot:hover .phone{transform:none}
        }
    </style>
</head>
<body>
<a class="skip" href="#main">{{ __('landing.skip') }}</a>
@if ($isDraft)
    <p class="draft" role="note">{{ __('landing.draft_notice') }}</p>
@endif
<header class="top">
    <div class="wrap nav">
        @yield('header')
    </div>
</header>
<main id="main">
    @yield('content')
</main>
<footer class="foot">
    <div class="wrap">
        <div class="foot-grid">
            <div>
                @include('landing._brand')
                <p>{{ __('landing.footer_tagline') }}</p>
            </div>
            <nav aria-label="{{ __('landing.footer_links') }}">
                <h2>{{ __('landing.footer_links') }}</h2>
                <ul>
                    @yield('footer-links')
                    <li><a href="/privacy">{{ __('landing.nav_privacy') }}</a></li>
                    <li><a href="/terms">{{ __('landing.nav_terms') }}</a></li>
                    <li><a href="/delete-account">{{ __('landing.nav_delete') }}</a></li>
                </ul>
            </nav>
            @if ($alternates !== [])
                <nav aria-label="{{ __('landing.footer_langs') }}">
                    <h2>{{ __('landing.footer_langs') }}</h2>
                    <ul>
                        @foreach ($alternates as $l => $path)
                            <li><a href="{{ $path }}" hreflang="{{ $l }}" lang="{{ $l }}">{{ __('landing.lang_name', [], $l) }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>
        <p class="legal-line">{{ __('landing.footer', ['year' => $year]) }}</p>
    </div>
</footer>
@yield('sticky')
</body>
</html>
