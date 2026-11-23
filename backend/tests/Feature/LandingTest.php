<?php

namespace Tests\Feature;

use App\Http\Controllers\LandingController;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingTest extends TestCase
{
    use RefreshDatabase;

    /** Words that must never appear on pages linked from the app (Play payments policy). */
    private const PAYMENT_WORDS = ['birr', 'price', 'wa.me', 'whatsapp', 'mailto:', '/premium'];

    public function test_home_and_premium_pages_show_live_prices_and_how_to_pay(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Get ready for the Grade 12 national exam')
            ->assertSee('href="/premium"', false)
            ->assertSee('Coming soon to Google Play');

        $this->get('/premium')->assertOk()
            ->assertSee('250 Birr')
            ->assertSee('600 Birr')
            ->assertSee('120 days')
            ->assertSee('Contact details are coming soon.')
            ->assertSee('Payment is never made inside the app.')
            ->assertDontSee('wa.me');

        // Prices and contacts come from the admin settings.
        $settings = app(Settings::class);
        $settings->set('premium_price_term_birr', 300);
        $settings->set('premium_price_year_birr', 1200);
        $settings->set('contact_whatsapp', '+251 91 234 5678');
        $settings->set('contact_email', 'pay@example.com');
        $this->get('/premium')
            ->assertSee('300 Birr')
            ->assertSee('1,200 Birr')
            ->assertSee('href="https://wa.me/251912345678"', false)
            ->assertSee('WhatsApp: +251912345678')
            ->assertSee('href="mailto:pay@example.com"', false)
            ->assertDontSee('coming soon');
    }

    public function test_pages_linked_from_the_app_never_show_or_link_to_prices(): void
    {
        $settings = app(Settings::class);
        $settings->set('contact_whatsapp', '+251912345678');
        $settings->set('contact_email', 'pay@example.com');

        foreach (['/app', '/so/app', '/am/app'] as $path) {
            $html = strtolower((string) $this->get($path)->assertOk()->getContent());
            foreach (self::PAYMENT_WORDS as $word) {
                $this->assertStringNotContainsString($word, $html, "{$path} contains {$word}");
            }
            // Not even a link back to the home page (it links to prices).
            $this->assertStringNotContainsString('href="/"', $html, $path);
        }
        foreach (['/privacy', '/terms', '/delete-account'] as $path) {
            $html = strtolower((string) $this->get($path)->assertOk()->getContent());
            foreach (['birr', 'price', 'wa.me', 'whatsapp', '/premium', 'href="/"'] as $word) {
                $this->assertStringNotContainsString($word, $html, "{$path} contains {$word}");
            }
        }
    }

    public function test_play_store_link_only_for_a_real_play_url(): void
    {
        $settings = app(Settings::class);
        $settings->set('play_store_url', 'https://evil.example.com/app');
        $this->get('/')->assertSee('Coming soon to Google Play')->assertDontSee('evil.example.com');
        $settings->set('play_store_url', 'https://play.google.com/store/apps/details?id=com.kulmiss.guul12');
        $this->get('/app')->assertSee('href="https://play.google.com/store/apps/details?id=com.kulmiss.guul12"', false);
    }

    public function test_draft_translations_are_marked_and_not_indexed(): void
    {
        $this->get('/so')->assertOk()
            ->assertSee('<html lang="so">', false)
            ->assertSee('Qoraal qabyo ah')
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertSee('href="/so/premium"', false);
        $this->get('/am/premium')->assertOk()
            ->assertSee('<html lang="am">', false)
            ->assertSee('ረቂቅ ትርጉም')
            ->assertSee('250 ብር');
        $this->get('/')->assertSee('<html lang="en">', false)->assertDontSee('noindex');
        $this->get('/fr')->assertNotFound();
        $this->get('/so/privacy')->assertNotFound();
    }

    public function test_language_links_hreflang_and_canonical(): void
    {
        $this->get('/premium')
            ->assertSee('<link rel="alternate" hreflang="so" href="'.url('/so/premium').'">', false)
            ->assertSee('<link rel="canonical" href="'.url('/premium').'">', false)
            ->assertSee('href="/am/premium"', false);
    }

    public function test_legal_pages_explain_data_and_deletion(): void
    {
        $this->get('/privacy')->assertOk()
            ->assertSee('No contacts, no phone location (GPS), no photos')
            ->assertSee('the region and city you type')
            ->assertSee('Anthropic')
            ->assertSee('Our contact email is coming soon.');
        $this->get('/delete-account')->assertOk()->assertSee('Delete my account');
        app(Settings::class)->set('contact_email', 'help@example.com');
        $this->get('/delete-account')->assertSee('mailto:help@example.com', false);
    }

    public function test_sitemap_lists_every_page(): void
    {
        $xml = (string) $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        foreach (['/', '/app', '/premium', '/privacy', '/terms', '/delete-account', '/so/app', '/am/premium'] as $p) {
            $this->assertStringContainsString('<loc>'.url($p).'</loc>', $xml, $p);
        }
    }

    public function test_no_outside_scripts_fonts_or_styles(): void
    {
        foreach (['/', '/app', '/premium', '/privacy'] as $path) {
            $html = (string) $this->get($path)->getContent();
            $this->assertStringNotContainsString('<script', $html, $path);
            $this->assertDoesNotMatchRegularExpression('/<link[^>]+stylesheet/i', $html, $path);
            $this->assertStringNotContainsString('fonts.googleapis', $html, $path);
        }
    }

    public function test_contact_values_are_cleaned(): void
    {
        $this->assertSame('251912345678', LandingController::whatsappDigits('+251 91-234-5678'));
        $this->assertNull(LandingController::whatsappDigits('12345'));
        $this->assertNull(LandingController::whatsappDigits(null));
        $this->assertSame('a@b.et', LandingController::cleanEmail(' a@b.et '));
        $this->assertNull(LandingController::cleanEmail('not an email'));
        $this->assertNull(LandingController::cleanEmail(42));
    }
}
