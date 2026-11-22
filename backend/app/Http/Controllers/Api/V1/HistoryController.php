<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The student's own answer history (right/wrong per question), so a new phone gets back
 * its mistakes, reviews and chapter strength after signing in.
 */
class HistoryController extends Controller
{
    public const PAGE = 1000;

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate(['after' => ['nullable', 'integer', 'min:0']]);

        $rows = DB::table('attempt_answers')
            ->join('attempts', 'attempts.id', '=', 'attempt_answers.attempt_id')
            ->join('questions', 'questions.id', '=', 'attempt_answers.question_id')
            ->where('attempts.user_id', $user->id)
            ->where('attempt_answers.id', '>', (int) ($data['after'] ?? 0))
            ->orderBy('attempt_answers.id')
            ->limit(self::PAGE + 1)
            ->get(['attempt_answers.id', 'attempt_answers.question_id', 'questions.chapter_id', 'attempt_answers.is_correct', 'attempts.finished_at']);

        $hasMore = $rows->count() > self::PAGE;
        $rows = $rows->take(self::PAGE);

        return response()->json([
            'answers' => $rows->map(fn (object $r): array => [
                'id' => (int) $r->id,
                'question_id' => (int) $r->question_id,
                'chapter_id' => (int) $r->chapter_id,
                'correct' => (bool) $r->is_correct,
                'answered_at' => Carbon::parse((string) $r->finished_at)->toIso8601ZuluString(),
            ])->values()->all(),
            'next_after' => $rows->isEmpty() ? (int) ($data['after'] ?? 0) : (int) $rows->last()->id,
            'has_more' => $hasMore,
        ]);
    }
}
