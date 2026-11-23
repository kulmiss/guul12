<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FeatureSwitches;
use App\Services\Leaderboard;
use App\Services\NicknameModeration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LeaderboardController extends Controller
{
    /**
     * GET /v1/leaderboard — this week's top 50 (nickname, avatar, XP) and the student's own place.
     */
    public function __invoke(Request $request, Leaderboard $board, FeatureSwitches $switches, NicknameModeration $moderation): JsonResponse
    {
        if (! $switches->enabled('leaderboard')) {
            return response()->json(['code' => 'feature_disabled', 'message' => 'The leaderboard is turned off.'], 403);
        }

        /** @var User $me */
        $me = $request->user();
        $now = Carbon::now();
        $standings = $board->standings($now);
        $top = array_slice($standings, 0, Leaderboard::TOP);

        $users = User::query()
            ->whereIn('id', array_column($top, 'user_id'))
            ->get(['id', 'nickname', 'avatar_style', 'avatar_seed'])
            ->keyBy('id');

        $rows = [];
        foreach ($top as $row) {
            /** @var User|null $user */
            $user = $users->get($row['user_id']);
            if ($user === null || $user->nickname === null) {
                continue;
            }
            $rows[] = [
                'rank' => Leaderboard::rankFor($standings, $row['user_id'], $row['xp']),
                'nickname' => $user->nickname,
                'avatar_style' => $user->avatar_style,
                'avatar_seed' => $user->avatar_seed,
                'xp' => $row['xp'],
                'is_me' => $user->id === $me->id,
                // Private reference for "Report nickname" (never the student's ID).
                'ref' => $user->id === $me->id ? null : $moderation->refFor($user),
            ];
        }

        $hasNickname = $me->nickname !== null;
        // Read from the database: the signed-in model may not hold this column.
        $blocked = User::query()->whereKey($me->id)->whereNotNull('leaderboard_blocked_at')->exists();
        $myXp = $board->xpOf($me, $now);

        return response()->json([
            'week_start' => Leaderboard::weekStart($now)->setTimezone(Leaderboard::TIMEZONE)->toDateString(),
            'top' => $rows,
            'me' => [
                'has_nickname' => $hasNickname,
                'xp' => $myXp,
                'rank' => $hasNickname && ! $me->role->isStaff() && ! $blocked
                    ? Leaderboard::rankFor($standings, $me->id, $myXp)
                    : null,
            ],
        ]);
    }
}
