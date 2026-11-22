<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PracticeMode;
use App\Http\Controllers\Controller;
use App\Models\AttemptAnswer;
use App\Models\User;
use App\Services\AttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttemptController extends Controller
{
    public const MAX_ANSWERS = 200;

    public function store(Request $request, AttemptService $attempts): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'client_uuid' => ['required', 'uuid'],
            'mode' => ['required', Rule::enum(PracticeMode::class)],
            'chapter_id' => ['nullable', 'integer', 'exists:chapters,id'],
            'started_at' => ['required', 'date'],
            'finished_at' => ['required', 'date', 'after_or_equal:started_at', 'before_or_equal:'.now()->addMinutes(10)->toIso8601String()],
            'answers' => ['required', 'array', 'min:1', 'max:'.self::MAX_ANSWERS],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.version' => ['required', 'integer', 'min:1'],
            'answers.*.response' => ['present'],
            'answers.*.time_ms' => ['required', 'integer', 'min:0'],
        ]);

        /** @var array{client_uuid: string, mode: string, chapter_id?: int|null, started_at: string, finished_at: string,
         *        answers: list<array{question_id: int, version: int, response: mixed, time_ms: int}>} $data */
        $result = $attempts->submit($user, $data);
        $attempt = $result['attempt'];

        return response()->json([
            'attempt' => [
                'client_uuid' => $attempt->client_uuid,
                'total' => $attempt->total,
                'correct' => $attempt->correct,
                'answers' => $attempt->answers->map(fn (AttemptAnswer $a): array => [
                    'question_id' => $a->question_id,
                    'is_correct' => $a->is_correct,
                ])->values()->all(),
            ],
        ], $result['created'] ? 201 : 200);
    }
}
