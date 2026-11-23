<?php

// Nova code is analysed only when the licensed laravel/nova package is installed
// (see docs/nova-setup.md). Without it, its classes do not exist and cannot be checked.
if (is_dir(__DIR__.'/vendor/laravel/nova')) {
    return [];
}

return [
    'parameters' => [
        'excludePaths' => [
            'analyse' => [
                __DIR__.'/app/Nova',
                __DIR__.'/app/Providers/NovaServiceProvider.php',
                __DIR__.'/tests/Nova',
            ],
        ],
    ],
];
