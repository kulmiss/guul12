<?php

namespace App\Services;

use App\Models\FeatureSwitch;
use Illuminate\Support\Facades\Cache;

/**
 * ON/OFF switch for every feature, editable in Nova. Missing rows fall back to the default here.
 */
class FeatureSwitches
{
    private const CACHE_KEY = 'feature_switches.all';

    /**
     * @return array<string, bool>
     */
    public static function defaults(): array
    {
        return [
            'battles' => true,
            'leaderboard' => true,
            'ads' => true,
            'rewarded_ads' => true,
            'referral' => true,
            'explain_more' => true,
            'daily_challenge' => true,
            'videos' => true,
            'certificates' => true,
            'sharing' => true,
            'google_sign_in' => true,
            'in_app_review' => true,
            'read_aloud' => true,
            // WhatsApp/Telegram buttons on the app's Premium page (owner decision #40).
            'premium_contact' => true,
        ];
    }

    public function enabled(string $key): bool
    {
        $all = $this->all();

        if (! array_key_exists($key, $all)) {
            throw new \InvalidArgumentException("Unknown feature switch [{$key}].");
        }

        return $all[$key];
    }

    /**
     * @return array<string, bool>
     */
    public function all(): array
    {
        /** @var array<string, bool> $stored */
        $stored = Cache::remember(self::CACHE_KEY, 300, function (): array {
            return FeatureSwitch::query()->pluck('enabled', 'key')
                ->map(fn ($v): bool => (bool) $v)
                ->all();
        });

        $out = [];

        foreach (self::defaults() as $key => $default) {
            $out[$key] = $stored[$key] ?? $default;
        }

        return $out;
    }

    public function set(string $key, bool $enabled): void
    {
        if (! array_key_exists($key, self::defaults())) {
            throw new \InvalidArgumentException("Unknown feature switch [{$key}].");
        }

        FeatureSwitch::query()->updateOrCreate(['key' => $key], ['enabled' => $enabled]);
        Cache::forget(self::CACHE_KEY);
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
