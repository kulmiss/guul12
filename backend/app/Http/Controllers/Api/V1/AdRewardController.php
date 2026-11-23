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
use Illuminate\Support\Facades\DB;

class AdRewardController extends Controller
{
    public const RANDOM_EXAM = 'random_exam';

    /**
     * POST /v1/rewards — the app watched a rewarded video; unlock one more random exam today.
     * The phone's word is trusted (no server-side ad verification yet), so the daily cap is what
     * limits it; the reward itself is small (one extra practice exam).
     */
    public function __invoke(Request $request, Settings $settings, FeatureSwitches $switches, PremiumService $premium): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $request->validate(['kind' => ['required', 'in:'.self::RANDOM_EXAM]]);

        if (! $switches->enabled('ads') || ! $switches->enabled('rewarded_ads') || $premium->isPremium($user)) {
            return response()->json(['code' => 'feature_disabled', 'message' => 'Rewarded videos are not available.'], 403);
        }

        $since = Carbon::now(RandomExamController::TIMEZONE)->startOfDay()->utc();
        $max = $settings->int('rewarded_random_exams_per_day');
        $today = DB::table('ad_rewards')->where('user_id', $user->id)->where('kind', self::RANDOM_EXAM)->where('created_at', '>=', $since)->count();
        if ($today >= $max) {
            return response()->json(['code' => 'reward_limit', 'message' => 'No more rewards today.'], 429);
        }

        DB::table('ad_rewards')->insert(['user_id' => $user->id, 'kind' => self::RANDOM_EXAM, 'created_at' => Carbon::now()]);

        return response()->json(['kind' => self::RANDOM_EXAM, 'remaining_today' => $max - $today - 1], 201);
    }
}
