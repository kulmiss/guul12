<?php

namespace App\Services;

use App\Enums\PracticeMode;
use App\Models\Attempt;
use App\Models\Battle;
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

        /** @var Collection<int, Question> $questions */
        $questions = Question::query()
            ->visibleToStudents()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        try {
            $attempt = $this->store($user, $data, $questions, $minMs, $maxMs, $challengeKey === null ? null : [$challengeDate, $challengeKey], $battleId);
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
     */
    private function store(User $user, array $data, $questions, int $minMs, int $maxMs, ?array $challenge, ?int $battleId): Attempt
    {
        return DB::transaction(function () use ($user, $data, $questions, $minMs, $maxMs, $challenge, $battleId): Attempt {
            $attempt = Attempt::query()->create([
                'user_id' => $user->id,
                'client_uuid' => $data['client_uuid'],
                'mode' => PracticeMode::from($data['mode']),
                'chapter_id' => $data['chapter_id'] ?? null,
                'mock_id' => $data['mock_id'] ?? null,
                'challenge_date' => $challenge[0] ?? null,
                'challenge_key' => $challenge[1] ?? null,
                'battle_id' => $battleId,
                'started_at' => Carbon::parse($data['started_at'])->utc(),
                'finished_at' => Carbon::parse($data['finished_at'])->utc(),
                'total' => 0,
                'correct' => 0,
            ]);

            $total = 0;
            $correct = 0;
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
                $tooFast += $isTooFast ? 1 : 0;
            }

            $attempt->forceFill(['total' => $total, 'correct' => $correct, 'too_fast' => $tooFast])->save();

            return $attempt;
        });
    }
}
