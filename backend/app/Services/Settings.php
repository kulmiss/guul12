<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-editable settings with safe defaults. Values live in app_settings and are edited in Nova.
 */
class Settings
{
    private const CACHE_KEY = 'app_settings.all';

    /**
     * Every known setting and its default. Unknown keys are refused, so a typo cannot hide a setting.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            // Landing page only. NEVER sent to the app (Google Play payments policy).
            'premium_price_term_birr' => 250,
            'premium_price_year_birr' => 600,
            'premium_term_days' => 120,
            'premium_year_days' => 365,

            // Exam date (Gregorian, Y-m-d). null = not announced yet.
            'exam_date' => null,

            // Free limits.
            'free_fixed_mocks' => 2,
            'premium_explain_more_per_day' => 20,

            // AI spending.
            'ai_monthly_cap_usd' => 20,

            // App updates.
            'min_supported_build' => 1,
            'recommended_build' => 1,

            // Maintenance message shown in the app; null = none.
            'maintenance_message' => null,

            // Score scale per exam paper (EAES has not confirmed the Aptitude scale).
            'paper_max_score' => 100,

            // Anti-cheat: answers faster than this are flagged.
            'min_answer_ms' => 800,
            'max_answer_ms' => 600000,
        ];
    }

    /**
     * Keys the app may receive in /api/v1/config.
     *
     * @return list<string>
     */
    public static function publicKeys(): array
    {
        return [
            'exam_date',
            'free_fixed_mocks',
            'premium_explain_more_per_day',
            'min_supported_build',
            'recommended_build',
            'maintenance_message',
            'paper_max_score',
        ];
    }

    public function get(string $key): mixed
    {
        $defaults = self::defaults();

        if (! array_key_exists($key, $defaults)) {
            throw new \InvalidArgumentException("Unknown setting [{$key}].");
        }

        $stored = $this->stored();

        return array_key_exists($key, $stored) ? $stored[$key] : $defaults[$key];
    }

    public function int(string $key): int
    {
        $value = $this->get($key);

        return is_numeric($value) ? (int) $value : (int) self::defaults()[$key];
    }

    public function set(string $key, mixed $value): void
    {
        if (! array_key_exists($key, self::defaults())) {
            throw new \InvalidArgumentException("Unknown setting [{$key}].");
        }

        AppSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    public function publicValues(): array
    {
        $out = [];

        foreach (self::publicKeys() as $key) {
            $out[$key] = $this->get($key);
        }

        return $out;
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        /** @var array<string, mixed> $values */
        $values = Cache::remember(self::CACHE_KEY, 300, function (): array {
            return AppSetting::query()->pluck('value', 'key')->all();
        });

        return $values;
    }
}
