{{-- The grades the app covers (8 to 12), one card each. --}}
<section id="grades" class="sec sec-white" aria-labelledby="grades-title">
    <div class="wrap">
        <p class="kicker">{{ __('landing.grades_kicker') }}</p>
        <h2 id="grades-title">{{ __('landing.grades_title') }}</h2>
        <p class="sub">{{ __('landing.grades_sub') }}</p>
        <ul class="cards cards-5 rail">
            @foreach (__('landing.grades') as $i => [$title, $body])
                <li class="card grade-card"><span class="grade-n" aria-hidden="true">{{ 8 + $i }}</span><h3>{{ $title }}</h3><p>{{ $body }}</p></li>
            @endforeach
        </ul>
    </div>
</section>
