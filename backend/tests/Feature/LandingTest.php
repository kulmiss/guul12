<?php

namespace Tests\Feature;

use App\Enums\QuestionStatus;
use App\Http\Controllers\LandingController;
use App\Models\Chapter;
use App\Models\Flashcard;
use App\Models\LandingReview;
use App\Models\Mock;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Unit;
use App\Services\LandingStats;
use App\Services\Settings;
use App\Support\LandingIcons;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LandingTest extends TestCase
{
    use RefreshDatabase;

    /** Words that must never appear on pages linked from the app (Play payments policy). */
    private const PAYMENT_WORDS = ['birr', 'price', 'wa.me', 'whatsapp', 't.me', 'telegram', 'mailto:', '/premium'];

    public function test_home_page_shows_live_prices_and_how_to_pay_in_its_premium_part(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Pass the Grade 12 exam')
            ->assertSee('href="#premium"', false)
            ->assertSee('id="premium"', false)
            ->assertSeeText('Coming soon to Google Play')
            ->assertSee('250 Birr')
            ->assertSee('600 Birr')
            ->assertSee('120 days')
            ->assertSee('Contact details are coming soon.')
            ->assertSee('Payment is never made inside the app.')
            ->assertDontSee('wa.me')
            ->assertDontSee('href="/premium"', false);

        // Prices and contacts come from the admin settings.
        $settings = app(Settings::class);
        $settings->set('premium_price_term_birr', 300);
        $settings->set('premium_price_year_birr', 1200);
        $settings->set('contact_whatsapp', '+251 91 234 5678');
        $settings->set('contact_telegram', 'https://t.me/Guul12_Help');
        $settings->set('contact_email', 'pay@example.com');
        $this->get('/')
            ->assertSee('href="https://t.me/Guul12_Help"', false)
            ->assertSee('Telegram: @Guul12_Help')
            ->assertSee('300 Birr')
            ->assertSee('1,200 Birr')
            ->assertSee('href="https://wa.me/251912345678"', false)
            ->assertSee('WhatsApp: +251912345678')
            ->assertSee('href="mailto:pay@example.com"', false)
            ->assertDontSee('Contact details are coming soon.');
    }

    public function test_the_old_premium_address_goes_to_the_home_pages_premium_part(): void
    {
        $this->get('/premium')->assertStatus(301)->assertRedirect('/#premium');
        $this->get('/so/premium')->assertStatus(301)->assertRedirect('/so#premium');
        $this->get('/am/premium')->assertStatus(301)->assertRedirect('/am#premium');
    }

    public function test_pages_linked_from_the_app_never_show_or_link_to_prices(): void
    {
        $settings = app(Settings::class);
        $settings->set('contact_whatsapp', '+251912345678');
        $settings->set('contact_telegram', '@Guul12_Help');
        $settings->set('contact_email', 'pay@example.com');
        $settings->set('exam_date', now()->addDays(30)->format('Y-m-d'));
        $this->contentWithOneChapter();
        LandingReview::query()->create(['name' => 'Hodan', 'grade' => 12, 'city' => 'Jigjiga', 'quote' => 'The mock exams helped me a lot.', 'rating' => 5, 'permission_confirmed' => true, 'is_published' => true]);

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
        $this->get('/')->assertSeeText('Coming soon to Google Play')->assertDontSee('evil.example.com');
        $settings->set('play_store_url', 'https://play.google.com/store/apps/details?id=com.kulmiss.guul12');
        $this->get('/app')->assertSee('href="https://play.google.com/store/apps/details?id=com.kulmiss.guul12"', false);
    }

    public function test_draft_translations_are_marked_and_not_indexed(): void
    {
        $this->get('/so')->assertOk()
            ->assertSee('<html lang="so">', false)
            ->assertSee('Qoraal qabyo ah')
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertSee('href="#premium"', false);
        $this->get('/am')->assertOk()
            ->assertSee('<html lang="am">', false)
            ->assertSee('ረቂቅ ትርጉም')
            ->assertSee('250 ብር');
        $this->get('/')->assertSee('<html lang="en">', false)->assertDontSee('noindex');
        $this->get('/fr')->assertNotFound();
        $this->get('/so/privacy')->assertNotFound();
    }

    public function test_language_links_hreflang_and_canonical(): void
    {
        $this->get('/app')
            ->assertSee('<link rel="alternate" hreflang="so" href="'.url('/so/app').'">', false)
            ->assertSee('<link rel="canonical" href="'.url('/app').'">', false)
            ->assertSee('href="/am/app"', false);
        $this->get('/')->assertSee('<link rel="alternate" hreflang="am" href="'.url('/am').'">', false);
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
        foreach (['/', '/app', '/privacy', '/terms', '/delete-account', '/so', '/so/app', '/am', '/am/app'] as $p) {
            $this->assertStringContainsString('<loc>'.url($p).'</loc>', $xml, $p);
        }
        $this->assertStringNotContainsString('premium', $xml, 'redirects are not listed');
    }

    public function test_no_outside_scripts_fonts_or_styles(): void
    {
        foreach (['/', '/app', '/privacy', '/so', '/am/app'] as $path) {
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

    /** One physics chapter with 3 visible questions, 1 hidden, 2 flashcards and 1 open mock. */
    private function contentWithOneChapter(): Subject
    {
        $physics = Subject::factory()->create(['code' => 'physics', 'name' => 'Physics', 'stream' => 'natural']);
        $chapter = Chapter::factory()->create(['unit_id' => Unit::factory()->create(['subject_id' => $physics->id])->id]);
        Question::factory()->count(3)->create(['chapter_id' => $chapter->id]);
        Question::factory()->status(QuestionStatus::Draft)->create(['chapter_id' => $chapter->id]);
        foreach ([1, 2] as $n) {
            Flashcard::query()->create(['uid' => "fc-{$n}", 'chapter_id' => $chapter->id, 'sort' => $n, 'term' => "T{$n}", 'definition' => 'D', 'status' => QuestionStatus::Published]);
        }
        Mock::query()->create(['uid' => 'm-1', 'subject_id' => $physics->id, 'grade' => 12, 'number' => 1, 'title' => 'Mock 1', 'minutes' => 60]);
        Cache::forget(LandingStats::CACHE_KEY);

        return $physics;
    }

    public function test_numbers_and_subjects_are_counted_from_what_students_can_open(): void
    {
        // Nothing yet: no numbers and no subject list (never a zero or a made-up number).
        $this->get('/')->assertOk()->assertDontSee('Guul 12 in numbers')->assertDontSee('id="subjects"', false);

        $physics = $this->contentWithOneChapter();
        // Not counted: an inactive chapter, a subject without chapters, an unpublished mock.
        Chapter::factory()->create(['unit_id' => Unit::factory()->create(['subject_id' => $physics->id])->id, 'is_active' => false]);
        Subject::factory()->create(['code' => 'history', 'name' => 'History']);
        $hidden = Mock::query()->create(['uid' => 'm-2', 'subject_id' => $physics->id, 'grade' => 12, 'number' => 2, 'title' => 'Mock 2', 'minutes' => 60]);
        $hidden->forceFill(['unpublished_at' => now()])->save();
        Cache::forget(LandingStats::CACHE_KEY);

        $stats = app(LandingStats::class)->get();
        $this->assertSame(['subjects' => 1, 'chapters' => 1, 'questions' => 3, 'flashcards' => 2, 'mocks' => 1], $stats['totals']);
        $this->assertSame([['code' => 'physics', 'name' => 'Physics', 'stream' => 'natural', 'chapters' => 1, 'questions' => 3]], $stats['subjects']);

        $this->get('/')->assertOk()
            ->assertSee('<strong>3</strong><span>practice questions</span>', false)
            ->assertSee('<strong>2</strong><span>flashcards</span>', false)
            ->assertSeeText('Natural · 1 chapters · 3 questions')
            ->assertDontSee('>History<', false);
        $this->get('/so')->assertSee('<span>su’aalo layli ah</span>', false);
    }

    public function test_numbers_are_cached_for_ten_minutes(): void
    {
        $this->contentWithOneChapter();
        $this->assertSame(3, app(LandingStats::class)->get()['totals']['questions']);
        Question::factory()->create(['chapter_id' => Chapter::query()->firstOrFail()->id]);
        $this->assertSame(3, app(LandingStats::class)->get()['totals']['questions'], 'cached');
        $this->travel(11)->minutes();
        $this->assertSame(4, app(LandingStats::class)->get()['totals']['questions']);
    }

    public function test_only_real_reviews_with_permission_are_shown(): void
    {
        $this->get('/')->assertDontSee('What students say');

        $review = ['name' => 'Hodan', 'grade' => 12, 'city' => 'Jigjiga', 'quote' => 'The mock exams helped me a lot.', 'rating' => 4];
        LandingReview::query()->create([...$review, 'name' => 'Hidden', 'permission_confirmed' => true, 'is_published' => false]);
        LandingReview::query()->create([...$review, 'name' => 'NoPermission', 'permission_confirmed' => false, 'is_published' => true]);
        $this->get('/')->assertDontSee('What students say')->assertDontSee('NoPermission')->assertDontSee('Hidden');

        LandingReview::query()->create([...$review, 'quote' => '<script>alert(1)</script> Great app', 'permission_confirmed' => true, 'is_published' => true]);
        $this->get('/')->assertOk()
            ->assertSee('What students say')
            ->assertSee('<strong>Hodan</strong> · Grade 12 · Jigjiga', false)
            ->assertSee('aria-label="4 out of 5 stars"', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; Great app', false)
            ->assertDontSee('<script>alert(1)', false);

        // At most six, in the staff's order.
        foreach (range(1, 8) as $n) {
            LandingReview::query()->create([...$review, 'name' => "Student{$n}", 'sort' => 10 - $n, 'permission_confirmed' => true, 'is_published' => true]);
        }
        $shown = LandingReview::query()->shown()->pluck('name')->all();
        $this->assertSame(['Hodan', 'Student8', 'Student7', 'Student6', 'Student5', 'Student4'], $shown, 'sort 0 (Hodan) first');
    }

    public function test_exam_countdown_uses_the_exam_date_in_ethiopian_time(): void
    {
        $now = CarbonImmutable::parse('2026-11-24 22:30', 'UTC'); // already 25 Nov in Addis Ababa
        $this->assertSame(216, LandingController::daysUntilExam('2027-06-29', $now));
        $this->assertSame(1, LandingController::daysUntilExam('2026-11-26', $now));
        $this->assertNull(LandingController::daysUntilExam('2026-11-25', $now), 'exam day itself');
        $this->assertNull(LandingController::daysUntilExam('2026-01-01', $now), 'past');
        $this->assertNull(LandingController::daysUntilExam('2027-02-30', $now), 'not a real date');
        $this->assertNull(LandingController::daysUntilExam('29/06/2027', $now));
        $this->assertNull(LandingController::daysUntilExam(null, $now));

        $this->get('/')->assertDontSee('left until the national exam')->assertSee('Grade 12 · National exam<', false);
        app(Settings::class)->set('exam_date', now('Africa/Addis_Ababa')->addDays(40)->format('Y-m-d'));
        $this->get('/')->assertSee('<strong>40 days</strong> left until the national exam', false)
            ->assertSee('Grade 12 · National exam '.now('Africa/Addis_Ababa')->addDays(40)->format('Y'), false);
        app(Settings::class)->set('exam_date', now('Africa/Addis_Ababa')->addDay()->format('Y-m-d'));
        $this->get('/')->assertSee('<strong>1 day</strong> left until the national exam. Good luck!', false);
    }

    public function test_every_app_picture_is_on_the_website_as_webp(): void
    {
        foreach (LandingController::SCREENS as $name) {
            $file = public_path("img/landing/{$name}.webp");
            $this->assertFileExists($file);
            $size = getimagesize($file);
            $this->assertNotFalse($size, $name);
            $this->assertSame([540, 1170, 'image/webp'], [$size[0], $size[1], $size['mime']], $name);
        }
        $html = (string) $this->get('/')->getContent();
        foreach (LandingController::SCREENS as $name) {
            $this->assertStringContainsString("src=\"/img/landing/{$name}.webp\" width=\"540\" height=\"1170\"", $html, $name);
        }
        // Every picture in the gallery has a description for screen readers.
        $this->assertSame(count(LandingController::SCREENS), substr_count($html, 'a screen of the Guul 12 app"'));
    }

    public function test_every_subject_has_its_own_icon(): void
    {
        foreach (['mathematics', 'physics', 'chemistry', 'biology', 'english', 'aptitude', 'geography', 'history', 'economics'] as $code) {
            $this->assertContains($code, LandingIcons::names());
        }
        $this->assertSame((string) LandingIcons::svg('book'), (string) LandingIcons::svg('unknown_subject'));
    }

    public function test_drafts_have_every_english_text(): void
    {
        $en = require lang_path('en/landing.php');
        foreach (LandingController::DRAFT_LOCALES as $locale) {
            $draft = require lang_path("{$locale}/landing.php");
            $this->assertSame($this->shape($en), $this->shape($draft), $locale);
        }
    }

    /**
     * Keys and list lengths of a texts file (so a missing or extra line is caught).
     *
     * @param  array<mixed>  $texts
     * @return array<mixed>
     */
    private function shape(array $texts): array
    {
        $out = [];
        foreach ($texts as $key => $value) {
            $out[$key] = is_array($value) ? $this->shape($value) : 'text';
        }
        ksort($out);

        return $out;
    }
}
