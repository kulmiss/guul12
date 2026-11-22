<?php

namespace App\Services;

use App\Enums\PracticeMode;
use App\Models\Attempt;
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
    ) {}

    /**
     * @param  array{client_uuid: string, mode: string, chapter_id?: int|null, mock_id?: int|null, started_at: string, finished_at: string,
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

        /** @var Collection<int, Question> $questions */
        $questions = Question::query()
            ->visibleToStudents()
            ->whereIn('id', array_column($data['answers'], 'question_id'))
            ->get()
            ->keyBy('id');

        try {
            $attempt = $this->store($user, $data, $questions, $minMs, $maxMs);
        } catch (UniqueConstraintViolationException) {
            // The same attempt arrived twice at the same moment; the first one won.
            $attempt = Attempt::query()->where('user_id', $user->id)->where('client_uuid', $data['client_uuid'])->firstOrFail();

            return ['attempt' => $attempt->load('answers'), 'created' => false];
        }

        $user->forceFill(['last_active_at' => Carbon::now()])->save();

        return ['attempt' => $attempt->load('answers'), 'created' => true];
    }

    /**
     * @param  array{client_uuid: string, mode: string, chapter_id?: int|null, mock_id?: int|null, started_at: string, finished_at: string,
     *               answers: list<array{question_id: int, version: int, response: mixed, time_ms: int}>}  $data
     * @param  Collection<int, Question>  $questions
     */
    private function store(User $user, array $data, $questions, int $minMs, int $maxMs): Attempt
    {
        return DB::transaction(function () use ($user, $data, $questions, $minMs, $maxMs): Attempt {
            $attempt = Attempt::query()->create([
                'user_id' => $user->id,
                'client_uuid' => $data['client_uuid'],
                'mode' => PracticeMode::from($data['mode']),
                'chapter_id' => $data['chapter_id'] ?? null,
                'mock_id' => $data['mock_id'] ?? null,
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
