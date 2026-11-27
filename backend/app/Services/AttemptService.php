<?php

namespace App\Services;

use App\Enums\PracticeMode;
use App\Models\Attempt;
use App\Models\Battle;
use App\Models\Mock;
use App\Models\Question;
use App\Models\User;
use App\Services\Scoring\AnswerScorer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stores a finished practice set and scores it on the server.
 * Uploading the same attempt twice (offline retry) returns the first result.
 */
class AttemptService
{
    /** A fixed mock may end this many minutes after its time limit (slow phone, upload) and still count. */
    public const MOCK_GRACE_MINUTES = 5;

    public function __construct(
        private readonly AnswerScorer $scorer,
        private readonly Settings $settings,
        private readonly DailyChallengeService $challenges,
    ) {}

    /**
     * @param  array{client_uuid: string, mode: string, chapter_id?: int|null, mock_id?: int|null, challenge_date?: string|null, battle_id?: int|null, started_at: string, finished_at: string,
     *               answers: list<array{question_id: int, version: int, response: mixed, time_ms: int}>}  $data
     * @return array{attempt: Attempt, created: bool}
     */
    public function submit(User $user, array $data): array
    {
        $existing = Attempt::query()
            ->where('user_id', $user->id)
            ->where('client_uuid', $data['client_uuid'])
            ->first();

        if ($existing !== null) {
            return ['attempt' => $existing->load('answers'), 'created' => false];
        }

        $minMs = $this->settings->int('min_answer_ms');
        $maxMs = $this->settings->int('max_answer_ms');

        $ids = array_column($data['answers'], 'question_id');
        $challengeDate = $data['challenge_date'] ?? null;
        $challengeKey = null;
        if (PracticeMode::from($data['mode']) === PracticeMode::DailyChallenge && $challengeDate !== null) {
            // Only that day's challenge questions count (anything else is ignored, never trusted).
            $challengeKey = DailyChallengeService::keyFor($user);
            $ids = array_values(array_intersect($ids, $this->challenges->questionIds($challengeDate, $challengeKey)));
        }

        $battleId = PracticeMode::from($data['mode']) === PracticeMode::Battle ? ($data['battle_id'] ?? null) : null;
        if ($battleId !== null) {
            // Only the battle's own questions count.
            $battle = Battle::query()->find($battleId);
            $ids = array_values(array_intersect($ids, $battle === null ? [] : $battle->question_ids));
        }

        $mock = null;
        if (PracticeMode::from($data['mode']) === PracticeMode::ExamFixed && isset($data['mock_id'])) {
            // Only the mock's own questions count, and a question left out counts as wrong.
            $mock = Mock::query()->find($data['mock_id']);
            $ids = array_values(array_intersect($ids, $mock === null ? [] : $this->mockQuestionIds($mock)));
        }

        /** @var Collection<int, Question> $questions */
        $questions = Question::query()
            ->visibleToStudents()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        try {
            $attempt = $this->store($user, $data, $questions, $minMs, $maxMs, $challengeKey === null ? null : [$challengeDate, $challengeKey], $battleId, $mock);
        } catch (UniqueConstraintViolationException) {
            // The same attempt arrived twice at the same moment; the first one won.
            $attempt = Attempt::query()->where('user_id', $user->id)->where('client_uuid', $data['client_uuid'])->firstOrFail();

            return ['attempt' => $attempt->load('answers'), 'created' => false];
        }

        $user->forceFill(['last_active_at' => Carbon::now()])->save();

        return ['attempt' => $attempt->load('answers'), 'created' => true];
    }

    /**
     * @param  array{client_uuid: string, mode: string, chapter_id?: int|null, mock_id?: int|null, challenge_date?: string|null, battle_id?: int|null, started_at: string, finished_at: string,
     *               answers: list<array{question_id: int, version: int, response: mixed, time_ms: int}>}  $data
     * @param  Collection<int, Question>  $questions
     * @param  array{0: string, 1: string}|null  $challenge  date and key of a daily challenge
     * @param  int|null  $battleId  for battles
     * @param  Mock|null  $mock  for fixed mocks
     */
    private function store(User $user, array $data, $questions, int $minMs, int $maxMs, ?array $challenge, ?int $battleId, ?Mock $mock): Attempt
    {
        return DB::transaction(function () use ($user, $data, $questions, $minMs, $maxMs, $challenge, $battleId, $mock): Attempt {
            $startedAt = Carbon::parse($data['started_at'])->utc();
            $finishedAt = Carbon::parse($data['finished_at'])->utc();
            $attempt = Attempt::query()->create([
                'user_id' => $user->id,
                'client_uuid' => $data['client_uuid'],
                'mode' => PracticeMode::from($data['mode']),
                'chapter_id' => $data['chapter_id'] ?? null,
                'mock_id' => $data['mock_id'] ?? null,
                'challenge_date' => $challenge[0] ?? null,
                'challenge_key' => $challenge[1] ?? null,
                'battle_id' => $battleId,
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
                'total' => 0,
                'correct' => 0,
            ]);

            $total = 0;
            $correct = 0;
            $answered = 0;
            $tooFast = 0;
            $seen = [];

            foreach ($data['answers'] as $answer) {
                $question = $questions->get($answer['question_id']);

                // Unknown, hidden or repeated questions are ignored, never trusted.
                if ($question === null || isset($seen[$question->id])) {
                    continue;
                }
                $seen[$question->id] = true;

                $timeMs = min(max(0, $answer['time_ms']), $maxMs);
                $isTooFast = $timeMs < $minMs;
                $isCorrect = $this->scorer->isCorrect($question->type, $question->answer_key, $answer['response']);

                $attempt->answers()->create([
                    'question_id' => $question->id,
                    'question_version' => $answer['version'],
                    'response' => $answer['response'],
                    'is_correct' => $isCorrect,
                    'time_ms' => $timeMs,
                    'too_fast' => $isTooFast,
                ]);

                // Stats only: a plain query so the question's version and sync log are not touched.
                Question::query()->whereKey($question->id)->toBase()->update([
                    'times_answered' => DB::raw('times_answered + 1'),
                    'times_correct' => DB::raw($isCorrect ? 'times_correct + 1' : 'times_correct'),
                ]);

                $total++;
                $correct += $isCorrect ? 1 : 0;
                $answered += $answer['response'] === null || $answer['response'] === '' || $answer['response'] === [] ? 0 : 1;
                $tooFast += $isTooFast ? 1 : 0;
            }

            $overTime = false;
            if ($mock !== null) {
                $total = max($total, Question::query()->visibleToStudents()->whereIn('id', $this->mockQuestionIds($mock))->count());
                $overTime = $startedAt->diffInSeconds($finishedAt) > ($mock->minutes + self::MOCK_GRACE_MINUTES) * 60;
            }

            $attempt->forceFill([
                'total' => $total, 'correct' => $correct, 'answered' => $answered, 'too_fast' => $tooFast, 'over_time' => $overTime,
            ])->save();

            return $attempt;
        });
    }

    /**
     * @return list<int>
     */
    private function mockQuestionIds(Mock $mock): array
    {
        return array_values(array_map(
            fn (mixed $id): int => (int) $id,
            DB::table('mock_questions')->where('mock_id', $mock->id)->pluck('question_id')->all(),
        ));
    }
}
