<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LandingController;
use App\Models\User;
use App\Services\Branding\Branding;
use App\Services\ExplainService;
use App\Services\FeatureSwitches;
use App\Services\PremiumService;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * App configuration. Contains NO prices and NO payment information (Google Play payments policy).
 */
class ConfigController extends Controller
{
    public function __invoke(Request $request, Settings $settings, FeatureSwitches $switches, PremiumService $premium, ExplainService $explain, Branding $branding): JsonResponse
    {
        $user = $request->user('sanctum');
        $isPremium = $user instanceof User && $premium->isPremium($user);

        return response()->json([
            'server_time' => Carbon::now()->toIso8601String(),
            'settings' => $settings->publicValues(),
            // "Explain more" also needs an AI key on the server.
            'features' => ['explain_more' => $switches->enabled('explain_more') && $explain->isAvailable()] + $switches->all(),
            'premium' => $isPremium,
            // Ads are never initialised for Premium students.
            'show_ads' => ! $isPremium && $switches->enabled('ads'),
            // Colours, fonts, roundness and logo; the app keeps the last one and falls back to Sunrise.
            'branding' => $branding->forApp(),
            // WhatsApp/Telegram for the app's Premium page (owner decision #40). Never a price.
            'contact' => $switches->enabled('premium_contact')
                ? [
                    'whatsapp' => LandingController::whatsappDigits($settings->get('contact_whatsapp')),
                    'telegram' => self::telegramName($settings->get('contact_telegram')),
                ]
                : ['whatsapp' => null, 'telegram' => null],
        ]);
    }

    /** "@name", "name" or "https://t.me/name" → "name" (5–32 letters, digits or _); else null. */
    public static function telegramName(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $name = preg_replace('#^(https?://)?(t\.me|telegram\.me)/#i', '', trim($value)) ?? '';
        $name = ltrim($name, '@');

        return preg_match('/^[A-Za-z0-9_]{5,32}$/', $name) === 1 ? $name : null;
    }
}
