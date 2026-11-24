<?php

namespace Tests\Support;

use App\Services\Auth\GoogleIdentity;
use App\Services\Auth\GoogleIdTokenVerifier;
use App\Services\Auth\InvalidGoogleToken;

/**
 * Accepts tokens shaped "good:<sub>:<email>" and rejects everything else.
 */
class FakeGoogleVerifier implements GoogleIdTokenVerifier
{
    public function verify(string $idToken): GoogleIdentity
    {
        $parts = explode(':', $idToken);

        if (count($parts) !== 3 || $parts[0] !== 'good') {
            throw new InvalidGoogleToken('bad token');
        }

        return new GoogleIdentity($parts[1], $parts[2] === '' ? null : $parts[2], true);
    }
}
