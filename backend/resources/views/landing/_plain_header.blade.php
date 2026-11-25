{{-- Header of the legal pages: never a link to the home page or prices. --}}
@include('landing._nav', ['cta' => false, 'links' => [
    ['/app', 'App'],
    ['/privacy', __('landing.nav_privacy')],
    ['/terms', __('landing.nav_terms')],
    ['/delete-account', __('landing.nav_delete')],
]])
