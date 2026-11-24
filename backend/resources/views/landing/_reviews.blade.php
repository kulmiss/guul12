{{-- Only real reviews, with the student's permission, added in Nova. Hidden while there are none. --}}
@if ($reviews->isNotEmpty())
    @php($icon = \App\Support\LandingIcons::class)
    <section class="sec" aria-labelledby="reviews-title">
        <div class="wrap">
            <p class="kicker">{{ __('landing.reviews_kicker') }}</p>
            <h2 id="reviews-title">{{ __('landing.reviews_title') }}</h2>
            <ul class="cards cards-3">
                @foreach ($reviews as $review)
                    <li class="card">
                        <figure class="rev">
                            <div class="stars" role="img" aria-label="{{ __('landing.review_stars', ['count' => $review->rating]) }}">
                                @for ($s = 1; $s <= 5; $s++){!! $icon::svg('star', $s <= $review->rating ? 'ic' : 'ic off') !!}@endfor
                            </div>
                            <blockquote>{{ $review->quote }}</blockquote>
                            <figcaption><strong>{{ $review->name }}</strong> · {{ __('landing.review_grade', ['grade' => $review->grade]) }}@if ($review->city !== null) · {{ $review->city }}@endif</figcaption>
                        </figure>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
