@php($icons = ['book', 'check', 'timer', 'plan', 'offline', 'chart', 'trophy', 'shield'])
<section id="features" class="sec sec-white" aria-labelledby="features-title">
    <div class="wrap">
        <p class="kicker">{{ __('landing.features_kicker') }}</p>
        <h2 id="features-title">{{ __('landing.features_title') }}</h2>
        <ul class="cards cards-4 features">
            @foreach (__('landing.features') as $i => [$title, $body])
                <li class="card {{ $i % 2 === 1 ? 'alt' : '' }}"><span class="chip-ic">{!! \App\Support\LandingIcons::svg($icons[$i] ?? 'book') !!}</span><h3>{{ $title }}</h3><p>{{ $body }}</p></li>
            @endforeach
        </ul>
    </div>
</section>
