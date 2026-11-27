<?php

namespace Tests\Feature;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Bug hunt: staff sign-in limits. Fortify already allowed 5 tries per email and address; now one
 * address also gets at most 20 tries a minute across all emails, and the two-step code is limited.
 */
class StaffLoginLimitTest extends TestCase
{
    public function test_the_login_limiter_exists_and_counts_per_email_and_per_address(): void
    {
        $limiter = RateLimiter::limiter('login');
        $this->assertNotNull($limiter, 'Nova uses this limiter for its sign-in form (config/fortify.php)');
        $this->assertSame('login', config('fortify.limiters.login'));
        $this->assertSame('two-factor', config('fortify.limiters.two-factor'));
        $this->assertNotNull(RateLimiter::limiter('two-factor'));

        $request = Request::create('/nova/login', 'POST', ['email' => 'Boss@Example.com '], server: ['REMOTE_ADDR' => '196.188.1.2']);
        /** @var list<Limit> $limits */
        $limits = (array) $limiter($request);
        $this->assertSame([5, 20], array_map(fn (Limit $l): int => $l->maxAttempts, $limits));
        $this->assertSame([60, 60], array_map(fn (Limit $l): int => $l->decaySeconds, $limits));
        $this->assertSame('boss@example.com|196.188.1.2', $limits[0]->key, 'the same email in any spelling');
        $this->assertSame('196.188.1.2', $limits[1]->key, 'many emails from one address');
    }

    public function test_no_public_sign_up_password_or_passkey_pages(): void
    {
        // Fortify (used by Nova for staff sign-in and 2FA) must not add its own pages: students
        // never sign up with a password, and staff sign in only through Nova.
        $names = collect(Route::getRoutes()->getRoutes())->map(fn ($r): string => (string) $r->getName());
        foreach (['register', 'register.store', 'login.store', 'password.request', 'password.email', 'password.update', 'two-factor.login', 'passkey.login'] as $name) {
            $this->assertFalse($names->contains($name), "route {$name} must not exist");
        }
        foreach (['/register', '/forgot-password', '/passkeys/login/options'] as $uri) {
            $this->get($uri)->assertNotFound();
        }
    }
}
