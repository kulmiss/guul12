<section id="how" class="sec" aria-labelledby="how-title">
    <div class="wrap">
        <p class="kicker">{{ __('landing.steps_kicker') }}</p>
        <h2 id="how-title">{{ __('landing.steps_title') }}</h2>
        <ol class="cards cards-3">
            @foreach (__('landing.steps') as $i => [$title, $body])
                <li class="card"><span class="step-n" aria-hidden="true">{{ $i + 1 }}</span><h3>{{ $title }}</h3><p>{{ $body }}</p></li>
            @endforeach
        </ol>
    </div>
</section>
