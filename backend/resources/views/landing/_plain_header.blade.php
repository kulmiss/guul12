{{-- Header of the legal pages and /app: never a link to the home page or prices. --}}
@include('landing._brand')
<nav class="menu" aria-label="{{ __('landing.nav_label') }}">
    <a href="/app">App</a>
    <a href="/privacy">{{ __('landing.nav_privacy') }}</a>
    <a href="/terms">{{ __('landing.nav_terms') }}</a>
    <a href="/delete-account">{{ __('landing.nav_delete') }}</a>
</nav>
