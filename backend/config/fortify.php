<?php

/*
 * Fortify is used only by Nova (staff sign-in, password change, two-step sign-in). Only the
 * rate limiters are set here; everything else keeps Fortify's defaults. Its own public pages
 * are switched off in AppServiceProvider.
 */
return [
    'limiters' => [
        // Defined in AppServiceProvider: per address across all emails, and the two-step code.
        'login' => 'login',
        'two-factor' => 'two-factor',
        'passkeys' => null,
    ],
];
