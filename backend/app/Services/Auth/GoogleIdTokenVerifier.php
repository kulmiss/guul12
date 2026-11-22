<?php

namespace App\Services\Auth;

interface GoogleIdTokenVerifier
{
    /**
     * Checks a Google Sign-In ID token and returns the verified identity.
     *
     * @throws InvalidGoogleToken when the token is not valid for this app
     */
    public function verify(string $idToken): GoogleIdentity;
}
