<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\V1\ConfigController;
use App\Models\LandingReview;
use App\Services\Branding\Branding;
use App\Services\LandingStats;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

/**
 * Public website. "/" is for social media and shows prices and how to pay (its Premium part;
 * the old "/premium" address redirects there).
 * "/app" (linked from the app and the summary PDFs) and the legal pages never show prices and
 * never link to them (Google Play payments policy, DECISIONS #3).
 */
class LandingController extends Controller
{
    /** Languages besides English. Their texts are drafts for native review. */
    public const DRAFT_LOCALES = ['so', 'am'];

    /** App pictures (public/img/landing/<name>.webp, from app/tool/landing_images.sh), in page order. */
    public const SCREENS = ['home', 'chapter', 'question', 'exam', 'result', 'progress', 'plan', 'leaderboard', 'chapter_dark'];

    /** Exam dates are counted in Ethiopian time. */
    private const TIMEZONE = 'Africa/Addis_Ababa';

    public function __construct(
        private readonly Settings $settings,
        private readonly Branding $branding,
        private readonly LandingStats $stats,
    ) {}

    public function home(?string $locale = null): View
    {
        $this->useLocale($locale);

        return view('landing.home', $this->shared($locale, 'home') + $this->marketing() + [
            'termPrice' => $this->settings->int('premium_price_term_birr'),
            'yearPrice' => $this->settings->int('premium_price_year_birr'),
            'termDays' => $this->settings->int('premium_term_days'),
            'yearDays' => $this->settings->int('premium_year_days'),
            'whatsapp' => self::whatsappDigits($this->settings->get('contact_whatsapp')),
            'telegram' => ConfigController::telegramName($this->settings->get('contact_telegram')),
            'email' => self::cleanEmail($this->settings->get('contact_email')),
        ]);
    }

    public function app(?string $locale = null): View
    {
        $this->useLocale($locale);

        return view('landing.app', $this->shared($locale, 'app') + $this->marketing());
    }

    /** The Premium part now lives on the home page; old links keep working. */
    public function premium(?string $locale = null): RedirectResponse
    {
        return redirect(($locale === null ? '/' : '/'.$locale).'#premium', 301);
    }

    public function legal(string $page): View
    {
        App::setLocale('en');

        $data = $this->shared(null, $page) + ['email' => self::cleanEmail($this->settings->get('contact_email'))];

        return match ($page) {
            'privacy' => view('landing.privacy', $data),
            'terms' => view('landing.terms', $data),
            'delete' => view('landing.delete', $data),
            default => abort(404),
        };
    }

    public function sitemap(): Response
    {
        $paths = ['/', '/app', '/privacy', '/terms', '/delete-account'];
        foreach (self::DRAFT_LOCALES as $l) {
            array_push($paths, "/{$l}", "/{$l}/app");
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($paths as $p) {
            $xml .= '<url><loc>'.e(url($p)).'</loc></url>';
        }
        $xml .= '</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** Digits only, 9–15 of them (international format without +), else null. */
    public static function whatsappDigits(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return strlen($digits) >= 9 && strlen($digits) <= 15 ? $digits : null;
    }

    public static function cleanEmail(mixed $value): ?string
    {
        return is_string($value) && filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false ? trim($value) : null;
    }

    /**
     * Days from today until the exam date in the settings; null when not set, not a date, or past.
     */
    public static function daysUntilExam(mixed $examDate, CarbonImmutable $now): ?int
    {
        if (! is_string($examDate) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $examDate) !== 1) {
            return null;
        }
        $exam = CarbonImmutable::createFromFormat('!Y-m-d', $examDate, self::TIMEZONE);
        if ($exam === null || $exam->format('Y-m-d') !== $examDate) {
            return null;
        }
        $days = (int) $now->setTimezone(self::TIMEZONE)->startOfDay()->diffInDays($exam, false);

        return $days > 0 ? $days : null;
    }

    /**
     * What the home and /app pages show besides the shared parts.
     *
     * @return array<string, mixed>
     */
    private function marketing(): array
    {
        $examDate = $this->settings->get('exam_date');
        $days = self::daysUntilExam($examDate, CarbonImmutable::now());

        return [
            'stats' => $this->stats->get(),
            'reviews' => LandingReview::query()->shown()->get(),
            'examDays' => $days,
            'examYear' => $days === null ? null : substr((string) $examDate, 0, 4),
            'screens' => self::SCREENS,
        ];
    }

    private function useLocale(?string $locale): void
    {
        App::setLocale($locale ?? 'en');
    }

    /**
     * @return array<string, mixed>
     */
    private function shared(?string $locale, string $page): array
    {
        $playUrl = $this->settings->get('play_store_url');

        return [
            'locale' => $locale ?? 'en',
            'page' => $page,
            'isDraft' => $locale !== null,
            'playUrl' => is_string($playUrl) && str_starts_with($playUrl, 'https://play.google.com/') ? $playUrl : null,
            'year' => now()->year,
            // Same page in every language, for the language links and hreflang.
            'alternates' => self::alternates($page),
            'brand' => $this->branding->forLanding(),
        ];
    }

    /**
     * @return array<string, string> locale => path
     */
    private static function alternates(string $page): array
    {
        if (! in_array($page, ['home', 'app'], true)) {
            return [];
        }
        $suffix = $page === 'home' ? '' : '/'.$page;
        $out = ['en' => $suffix === '' ? '/' : $suffix];
        foreach (self::DRAFT_LOCALES as $l) {
            $out[$l] = '/'.$l.$suffix;
        }

        return $out;
    }
}
