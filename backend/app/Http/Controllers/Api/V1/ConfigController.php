<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
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
    public function __invoke(Request $request, Settings $settings, FeatureSwitches $switches, PremiumService $premium): JsonResponse
    {
        $user = $request->user('sanctum');
        $isPremium = $user instanceof User && $premium->isPremium($user);

        return response()->json([
            'server_time' => Carbon::now()->toIso8601String(),
            'settings' => $settings->publicValues(),
            'features' => $switches->all(),
            'premium' => $isPremium,
            // Ads are never initialised for Premium students.
            'show_ads' => ! $isPremium && $switches->enabled('ads'),
        ]);
    }
}
