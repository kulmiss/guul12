<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Bug hunt (privacy): a visit to the website must not store the visitor's address or set
 * cookies. The pages have no forms and no sign-in, so they need no session.
 */
class LandingPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_website_visits_store_nothing_and_set_no_cookies(): void
    {
        config(['session.driver' => 'database']);
        foreach (['/', '/app', '/so', '/am/app', '/privacy', '/terms', '/delete-account', '/sitemap.xml', '/health'] as $page) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => '196.188.1.2'])->get($page);
            $this->assertLessThan(400, $response->status(), $page);
            $this->assertSame([], $response->headers->getCookies(), "{$page} sets no cookie");
        }
        $this->get('/premium')->assertRedirect();
        $this->assertSame(0, DB::table('sessions')->count(), 'no visitor address or browser is stored');
    }
}
