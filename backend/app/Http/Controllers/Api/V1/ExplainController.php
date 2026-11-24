<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AiExplanation;
use App\Models\Question;
use App\Models\User;
use App\Services\ExplainService;
use App\Services\FeatureSwitches;
use App\Services\PremiumService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExplainController extends Controller
{
    public function __construct(
        private readonly ExplainService $explain,
        private readonly FeatureSwitches $switches,
        private readonly PremiumService $premium,
    ) {}

    /** POST /v1/questions/{question}/explain — Premium only. */
    public function store(Request $request, Question $question): JsonResponse
    {
        if (! $this->switches->enabled('explain_more') || ! $this->explain->isAvailable()) {
            return response()->json(['code' => 'feature_disabled', 'message' => 'Explain more is turned off.'], 403);
        }
        /** @var User $user */
        $user = $request->user();
        if (! $this->premium->isPremium($user)) {
            return response()->json(['code' => 'premium_required', 'message' => 'Premium'], 403);
        }
        abort_unless(Question::query()->visibleToStudents()->whereKey($question->id)->exists(), 404);

        $question->loadMissing('options');
        $result = $this->explain->explain($user, $question);
        if (is_string($result)) {
            return response()->json(['code' => $result, 'message' => 'Explain more is not available now.'], $result === 'explain_limit' ? 429 : 503);
        }

        return response()->json([
            'explanation' => [
                'id' => $result['explanation']->id,
                'question_id' => $question->id,
                'body' => $result['explanation']->body,
                'cached' => $result['cached'],
            ],
            'remaining_today' => max(0, $this->explain->dailyLimit() - $this->explain->usedToday($user)),
        ]);
    }

    /** POST /v1/explanations/{explanation}/feedback — helpful or not. */
    public function feedback(Request $request, AiExplanation $explanation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate(['helpful' => ['required', 'boolean']]);
        // Only students who were shown this explanation may vote on it.
        $shown = DB::table('ai_requests')
            ->where('user_id', $user->id)->where('ai_explanation_id', $explanation->id)->exists();
        abort_unless($shown, 404);
        $this->explain->feedback($user, $explanation, (bool) $data['helpful']);

        return response()->json(['ok' => true]);
    }
}
