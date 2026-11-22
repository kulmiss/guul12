<?php

namespace App\Services\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Verifies Google ID tokens offline against Google's public keys (JWKS), cached for an hour.
 */
class JwksGoogleIdTokenVerifier implements GoogleIdTokenVerifier
{
    public const JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    private const ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    /**
     * @param  list<string>  $allowedAudiences  OAuth client IDs of our app (web client ID used by the Android app)
     */
    public function __construct(private readonly array $allowedAudiences) {}

    public function verify(string $idToken): GoogleIdentity
    {
        if ($this->allowedAudiences === []) {
            throw new InvalidGoogleToken('Google sign-in is not configured.');
        }

        try {
            JWT::$leeway = 60;
            $payload = (array) JWT::decode($idToken, JWK::parseKeySet($this->keys(), 'RS256'));
        } catch (Throwable $e) {
            throw new InvalidGoogleToken('Token could not be verified.', previous: $e);
        }

        $issuer = $payload['iss'] ?? null;
        $audience = $payload['aud'] ?? null;
        $sub = $payload['sub'] ?? null;

        if (! is_string($issuer) || ! in_array($issuer, self::ISSUERS, true)) {
            throw new InvalidGoogleToken('Wrong issuer.');
        }
        if (! is_string($audience) || ! in_array($audience, $this->allowedAudiences, true)) {
            throw new InvalidGoogleToken('Wrong audience.');
        }
        if (! is_string($sub) || $sub === '') {
            throw new InvalidGoogleToken('Missing subject.');
        }

        $email = $payload['email'] ?? null;

        return new GoogleIdentity(
            sub: $sub,
            email: is_string($email) ? $email : null,
            emailVerified: ($payload['email_verified'] ?? false) === true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function keys(): array
    {
        /** @var array<string, mixed> $keys */
        $keys = Cache::remember('google_jwks', 3600, function (): array {
            $response = Http::timeout(10)->get(self::JWKS_URL)->throw();
            /** @var array<string, mixed> $json */
            $json = $response->json();

            return $json;
        });

        return $keys;
    }
}
