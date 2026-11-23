<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Passage;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Services\ContentPayload;
use App\Services\PremiumService;
use App\Services\RandomExamBuilder;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A random mock from the bank. Free students get a set number per day (DECISIONS #18);
 * the day follows Ethiopian time.
 */
class RandomExamController extends Controller
{
    public const TIMEZONE = 'Africa/Addis_Ababa';

    public function __invoke(
        Request $request,
        RandomExamBuilder $builder,
        ContentPayload $payload,
        PremiumService $premium,
        Settings $settings,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'grade' => ['nullable', 'integer', 'between:9,12'],
        ]);

        if (! $premium->isPremium($user)) {
            $since = Carbon::now(self::TIMEZONE)->startOfDay()->utc();
            $today = DB::table('random_exam_draws')->where('user_id', $user->id)->where('created_at', '>=', $since)->count();
            $unlocked = DB::table('ad_rewards')->where('user_id', $user->id)->where('kind', AdRewardController::RANDOM_EXAM)->where('created_at', '>=', $since)->count();
            if ($today >= $settings->int('free_random_exams_per_day') + $unlocked) {
                return response()->json(['code' => 'daily_limit', 'message' => 'Premium'], 429);
            }
        }

        $subject = Subject::query()->whereKey((int) $data['subject_id'])->firstOrFail();
        $grade = (int) ($data['grade'] ?? $user->grade ?? 12);
        $questions = $builder->build($subject, $grade, $grade >= 11 ? $user->stream : null, random_int(1, PHP_INT_MAX));
        if ($questions->isEmpty()) {
            return response()->json(['code' => 'no_questions', 'message' => 'No questions yet'], 404);
        }

        DB::table('random_exam_draws')->insert([
            'user_id' => $user->id,
            'subject_id' => $subject->id,
            'question_count' => $questions->count(),
            'created_at' => Carbon::now(),
        ]);

        /** @var Collection<int, Passage> $passages */
        $passages = $questions->pluck('passage')->filter()->unique('id')->values();

        return response()->json([
            'exam' => [
                'subject_id' => $subject->id,
                'grade' => $grade,
                'minutes' => (int) ceil($questions->count() * RandomExamBuilder::SECONDS_PER_QUESTION / 60),
                'question_count' => $questions->count(),
            ],
            'passages' => $passages->map(fn (Passage $p): array => ['id' => $p->id, 'title' => $p->title, 'body' => $p->body])->all(),
            'questions' => $questions->map(fn (Question $q): array => $payload->question($q))->values()->all(),
        ], 201);
    }
}
