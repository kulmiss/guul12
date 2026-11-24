{{-- What is free and what Premium adds. Never a price here; the button (home page only) goes to /premium. --}}
@php($free = 6)
@php($icon = \App\Support\LandingIcons::class)
<section id="premium" class="sec sec-white" aria-labelledby="compare-title">
    <div class="wrap">
        <p class="kicker">{{ __('landing.compare_kicker') }}</p>
        <h2 id="compare-title">{{ __('landing.compare_title') }}</h2>
        <div class="table-card">
            <table class="cmp">
                <thead>
                    <tr><th scope="col">{{ __('landing.compare_what') }}</th><th scope="col">{{ __('landing.compare_free') }}</th><th scope="col" class="pcol">{{ __('landing.compare_premium') }}</th></tr>
                </thead>
                <tbody>
                    @foreach (__('landing.compare_rows') as $i => $row)
                        <tr>
                            <th scope="row">{{ $row }}</th>
                            <td>@if ($i < $free)<span role="img" aria-label="{{ __('landing.compare_yes') }}">{!! $icon::svg('tick', 'mark') !!}</span>@else<span role="img" aria-label="{{ __('landing.compare_no') }}">{!! $icon::svg('dash', 'mark') !!}</span>@endif</td>
                            <td class="pcol"><span role="img" aria-label="{{ __('landing.compare_yes') }}">{!! $icon::svg('tick', 'mark') !!}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($premiumLink ?? false)
            <p class="center"><a class="btn btn-dark" href="{{ $premiumLink }}">{{ __('landing.compare_button') }}</a></p>
        @endif
    </div>
</section>
