<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\User;
use App\Services\ContentPayload;
use App\Services\DailyChallengeService;
use App\Services\FeatureSwitches;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyChallengeController extends Controller
{
    /**
     * GET /v1/challenge — today's 10 questions and, once played, the first result of the day.
     */
    public function __invoke(Request $request, DailyChallengeService $challenges, ContentPayload $payload, FeatureSwitches $switches): JsonResponse
    {
        if (! $switches->enabled('daily_challenge')) {
            return response()->json(['code' => 'feature_disabled', 'message' => 'The daily challenge is turned off.'], 403);
        }

        /** @var User $user */
        $user = $request->user();
        $date = DailyChallengeService::today();
        $key = DailyChallengeService::keyFor($user);
        $questions = $challenges->questions($date, $key);
        if ($questions->isEmpty()) {
            return response()->json(['code' => 'no_questions', 'message' => 'No questions yet'], 404);
        }

        $first = $challenges->firstTry($user, $date, $key);
        $result = null;
        if ($first !== null) {
            [$percent, $players] = $challenges->percentile($first);
            $result = [
                'correct' => $first->correct,
                'total' => $first->total,
                'better_than_percent' => $percent,
                'players' => $players,
            ];
        }

        return response()->json([
            'date' => $date,
            'questions' => $questions->map(fn (Question $q): array => $payload->question($q))->values()->all(),
            'result' => $result,
        ]);
    }
}
