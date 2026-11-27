<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Tests\TestCase;

/**
 * Bug hunt: browser safety headers on every page and API answer.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_website_sends_safety_headers_and_a_strict_content_policy(): void
    {
        foreach (['/', '/app', '/so', '/am/app', '/privacy', '/terms', '/delete-account'] as $page) {
            $response = $this->get($page)->assertOk();
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
            $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
            $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
            $csp = (string) $response->headers->get('Content-Security-Policy');
            foreach (["default-src 'self'", "script-src 'self'", "object-src 'none'", "frame-ancestors 'none'", "base-uri 'self'", "form-action 'self'"] as $rule) {
                $this->assertStringContainsString($rule, $csp, "{$page}: {$rule}");
            }
            $this->assertStringNotContainsString('unsafe-eval', $csp);
            $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp, 'no inline scripts on the website');
        }
    }

    public function test_the_api_sends_safety_headers_too(): void
    {
        $response = $this->getJson('/api/v1/config')->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_https_answers_ask_the_browser_to_stay_on_https(): void
    {
        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
        $this->get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_behind_a_trusted_proxy_https_is_seen(): void
    {
        $forwarded = ['HTTP_X_FORWARDED_PROTO' => 'https', 'HTTP_X_FORWARDED_FOR' => '196.188.1.2'];
        // Not trusted (the default): a visitor cannot pretend to be on HTTPS.
        $this->withServerVariables($forwarded)->get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');

        config(['guul.trusted_proxies' => '*']);
        try {
            AppServiceProvider::trustConfiguredProxies();
            $this->withServerVariables($forwarded)->get('http://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
        } finally {
            TrustProxies::flushState();
        }
    }
}
