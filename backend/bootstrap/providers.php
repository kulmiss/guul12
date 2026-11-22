<?php

use App\Providers\AppServiceProvider;
use App\Providers\NovaServiceProvider;
use Laravel\Nova\Nova;

return array_values(array_filter([
    AppServiceProvider::class,
    // Nova admin is switched on automatically once the licensed laravel/nova package is installed.
    // Until then the app, the tests and CI run without it. See docs/nova-setup.md.
    class_exists(Nova::class) ? NovaServiceProvider::class : null,
]));
