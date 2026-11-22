<?php

namespace App\Services\Quality;

use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\AuditLog;
use App\Models\Question;
use App\Models\User;
use App\Services\Scoring\AnswerScorer;
use Illuminate\Support\Facades\DB;

/**
 * After a wrong answer key is fixed, every stored answer to that question is checked again
 * with the new key. Scores change quietly: no message, no notification.
 */
final class RescoreService
{
    public function __construct(private readonly AnswerScorer $scorer) {}

    /**
     * @return int how many answers changed from right to wrong or back
     */
    public function rescore(Question $question, ?User $by = null): int
    {
        return DB::transaction(function () use ($question, $by): int {
            $changed = 0;
            $touchedAttempts = [];

            AttemptAnswer::query()
                ->where('question_id', $question->id)
                ->orderBy('id')
                ->chunkById(500, function ($answers) use ($question, &$changed, &$touchedAttempts): void {
                    foreach ($answers as $answer) {
                        /** @var AttemptAnswer $answer */
                        $now = $this->scorer->isCorrect($question->type, $question->answer_key, $answer->response);
                        if ($now !== $answer->is_correct) {
                            $answer->is_correct = $now;
                            $answer->save();
                            $changed++;
                            $touchedAttempts[$answer->attempt_id] = true;
                        }
                    }
                });

            foreach (array_keys($touchedAttempts) as $attemptId) {
                $correct = AttemptAnswer::query()->where('attempt_id', $attemptId)->where('is_correct', true)->count();
                Attempt::query()->whereKey($attemptId)->update(['correct' => $correct]);
            }

            $question->newQuery()->whereKey($question->id)->update([
                'times_correct' => AttemptAnswer::query()
                    ->where('question_id', $question->id)
                    ->where('is_correct', true)
                    ->count(),
            ]);

            AuditLog::write($by, 'question.rescored', $question, [
                'version' => $question->version,
                'answers_changed' => $changed,
                'attempts_changed' => count($touchedAttempts),
            ]);

            return $changed;
        });
    }
}
