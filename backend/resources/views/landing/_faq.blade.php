<section id="faq" class="sec" aria-labelledby="faq-title">
    <div class="wrap narrow">
        <p class="kicker">{{ __('landing.faq_kicker') }}</p>
        <h2 id="faq-title">{{ __('landing.faq_title') }}</h2>
        @foreach (__('landing.faq') as $i => [$question, $answer])
            <details class="qa" @if ($i === 0) open @endif><summary>{{ $question }}</summary><p>{{ $answer }}</p></details>
        @endforeach
    </div>
</section>
