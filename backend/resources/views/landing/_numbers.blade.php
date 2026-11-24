{{-- Real numbers, counted from what students can open (LandingStats). Zero numbers are left out. --}}
@php($shown = array_filter($stats['totals'], fn (int $n): bool => $n > 0))
@if ($shown !== [])
    <section class="numbers" aria-label="{{ __('landing.numbers_label') }}">
        <div class="wrap"><ul class="num-grid">
            @foreach ($shown as $key => $n)
                <li class="num"><strong>{{ number_format($n) }}</strong><span>{{ __('landing.numbers.'.$key) }}</span></li>
            @endforeach
        </ul></div>
    </section>
@endif
