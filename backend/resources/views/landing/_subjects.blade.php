@if ($stats['subjects'] !== [])
    <section id="subjects" class="sec sec-white" aria-labelledby="subjects-title">
        <div class="wrap">
            <p class="kicker">{{ __('landing.subjects_kicker') }}</p>
            <h2 id="subjects-title">{{ __('landing.subjects_title') }}</h2>
            <p class="sub">{{ __('landing.subjects_sub') }}</p>
            <ul class="subj-grid">
                @foreach ($stats['subjects'] as $i => $subject)
                    <li class="subj {{ $i % 2 === 1 ? 'alt' : '' }}">
                        <span class="chip-ic">{!! \App\Support\LandingIcons::svg($subject['code']) !!}</span>
                        <div>
                            <h3>{{ $subject['name'] }}</h3>
                            <p>{{ __('landing.subject_line', [
                                'stream' => __('landing.stream_'.($subject['stream'] ?? 'both')),
                                'chapters' => number_format($subject['chapters']),
                                'questions' => number_format($subject['questions']),
                            ]) }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
