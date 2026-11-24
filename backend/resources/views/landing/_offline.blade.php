@php($icons = ['offline', 'shield', 'spark'])
<section class="band" aria-labelledby="offline-title">
    <div class="wrap band-grid">
        <div>
            <h2 id="offline-title">{{ __('landing.offline_title') }}</h2>
            <p>{{ __('landing.offline_body') }}</p>
        </div>
        <ul class="band-list">
            @foreach (__('landing.offline_points') as $i => $point)
                <li>{!! \App\Support\LandingIcons::svg($icons[$i] ?? 'check') !!}{{ $point }}</li>
            @endforeach
        </ul>
    </div>
</section>
