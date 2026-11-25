{{-- Prices and how to pay: only on the home page (never on /app or the legal pages, which the app links to). --}}
@php($icon = \App\Support\LandingIcons::class)
<div class="plans-block">
    <h3 class="sub-title">{{ __('landing.plans_title') }}</h3>
    <div class="plans">
        <div class="card plan">
            <h4>{{ __('landing.plan_term') }}</h4>
            <p class="amount">{{ __('landing.birr', ['amount' => number_format($termPrice)]) }}</p>
            <p>{{ __('landing.plan_term_days', ['days' => $termDays]) }} · {{ __('landing.plan_includes') }}</p>
        </div>
        <div class="card plan plan-best">
            <span class="badge">{{ __('landing.plan_best') }}</span>
            <h4>{{ __('landing.plan_year') }}</h4>
            <p class="amount">{{ __('landing.birr', ['amount' => number_format($yearPrice)]) }}</p>
            <p>{{ __('landing.plan_year_days', ['days' => $yearDays]) }} · {{ __('landing.plan_includes') }}</p>
        </div>
    </div>

    <h3 class="sub-title">{{ __('landing.how_title') }}</h3>
    <ol class="cards cards-4">
        @foreach (__('landing.how_steps') as $i => $step)
            <li class="card"><span class="step-n" aria-hidden="true">{{ $i + 1 }}</span><p>{{ $step }}</p></li>
        @endforeach
    </ol>

    <div class="card contact" id="contact">
        <h3>{{ __('landing.contact_title') }}</h3>
        @if ($whatsapp === null && $telegram === null && $email === null)
            <p>{{ __('landing.contact_soon') }}</p>
        @else
            <p>{{ __('landing.contact_intro') }}</p>
            <div class="contact-list">
                @if ($whatsapp !== null)
                    <a class="btn" href="https://wa.me/{{ $whatsapp }}" rel="noopener">{!! $icon::svg('chat') !!}{{ __('landing.whatsapp', ['number' => '+'.$whatsapp]) }}</a>
                @endif
                @if ($telegram !== null)
                    <a class="btn btn-dark" href="https://t.me/{{ $telegram }}" rel="noopener">{!! $icon::svg('send') !!}{{ __('landing.telegram', ['name' => $telegram]) }}</a>
                @endif
                @if ($email !== null)
                    <a class="btn btn-dark" href="mailto:{{ $email }}">{!! $icon::svg('mail') !!}{{ __('landing.email', ['email' => $email]) }}</a>
                @endif
            </div>
        @endif
        <p class="note">{{ __('landing.payment_note') }}</p>
    </div>
</div>
