<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PracticeMode;
use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Battle;
use App\Models\User;
use App\Services\AttemptService;
use App\Services\DailyChallengeService;
use App\Services\ExamResultService;
use App\Services\FeatureSwitches;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttemptController extends Controller
{
    public const MAX_ANSWERS = 200;

    public function __construct(
        private readonly DailyChallengeService $challenges,
        private readonly ReferralService $referrals,
        private readonly FeatureSwitches $switches,
    ) {}

    public function store(Request $request, AttemptService $attempts, ExamResultService $results): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'client_uuid' => ['required', 'uuid'],
            'mode' => ['required', Rule::enum(PracticeMode::class)],
            'chapter_id' => ['nullable', 'integer', 'exists:chapters,id'],
            'mock_id' => ['nullable', 'integer', 'exists:mocks,id', 'required_if:mode,'.PracticeMode::ExamFixed->value, 'prohibited_unless:mode,'.PracticeMode::ExamFixed->value],
            'challenge_date' => ['nullable', 'date_format:Y-m-d', 'required_if:mode,'.PracticeMode::DailyChallenge->value, 'prohibited_unless:mode,'.PracticeMode::DailyChallenge->value],
            'battle_id' => ['nullable', 'integer', 'required_if:mode,'.PracticeMode::Battle->value, 'prohibited_unless:mode,'.PracticeMode::Battle->value],
            'started_at' => ['required', 'date'],
            'finished_at' => ['required', 'date', 'after_or_equal:started_at', 'before_or_equal:'.now()->addMinutes(10)->toIso8601String()],
            'answers' => ['required', 'array', 'min:1', 'max:'.self::MAX_ANSWERS],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.version' => ['required', 'integer', 'min:1'],
            'answers.*.response' => ['present'],
            'answers.*.time_ms' => ['required', 'integer', 'min:0'],
        ]);

        if (isset($data['challenge_date']) && ! DailyChallengeService::acceptsDate((string) $data['challenge_date'])) {
            return response()->json([
                'message' => 'This daily challenge is over.',
                'errors' => ['challenge_date' => ['This daily challenge is over.']],
            ], 422);
        }

        if (isset($data['battle_id'])) {
            $battle = Battle::query()->find((int) $data['battle_id']);
            if ($battle === null || ! $battle->hasPlayer($user)) {
                return response()->json([
                    'message' => 'This battle is not yours.',
                    'errors' => ['battle_id' => ['This battle is not yours.']],
                ], 422);
            }
        }

        /** @var array{client_uuid: string, mode: string, chapter_id?: int|null, mock_id?: int|null, challenge_date?: string|null, battle_id?: int|null, started_at: string, finished_at: string,
         *        answers: list<array{question_id: int, version: int, response: mixed, time_ms: int}>} $data */
        $result = $attempts->submit($user, $data);
        if ($result['created'] && $this->switches->enabled('referral')) {
            // A friend who was invited counts once they really studied.
            $this->referrals->check($user);
        }

        return response()->json(['attempt' => $this->present($result['attempt'], $results)], $result['created'] ? 201 : 200);
    }

    /**
     * An uploaded attempt again, e.g. to refresh "better than X%" after more students took the mock.
     */
    public function show(Request $request, string $clientUuid, ExamResultService $results): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $attempt = Attempt::query()->where('user_id', $user->id)->where('client_uuid', $clientUuid)->with('answers')->first();
        abort_if($attempt === null, 404);

        return response()->json(['attempt' => $this->present($attempt, $results)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Attempt $attempt, ExamResultService $results): array
    {
        return [
            'client_uuid' => $attempt->client_uuid,
            'mode' => $attempt->mode->value,
            'mock_id' => $attempt->mock_id,
            'total' => $attempt->total,
            'correct' => $attempt->correct,
            'answers' => $attempt->answers->map(fn (AttemptAnswer $a): array => [
                'question_id' => $a->question_id,
                'is_correct' => $a->is_correct,
            ])->values()->all(),
            // Exams only: right answers per chapter and the comparison with other students.
            'exam' => $attempt->mode->isExam() ? $results->details($attempt) : null,
            'challenge' => $attempt->mode === PracticeMode::DailyChallenge ? $this->challenge($attempt) : null,
        ];
    }

    /**
     * @return array{date: string|null, better_than_percent: int|null, players: int}
     */
    private function challenge(Attempt $attempt): array
    {
        [$percent, $players] = $this->challenges->percentile($attempt);

        return [
            'date' => $attempt->challenge_date?->toDateString(),
            'better_than_percent' => $percent,
            'players' => $players,
        ];
    }
}
