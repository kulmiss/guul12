<?php

namespace App\Services\Staff;

use App\Enums\QuestionStatus;
use App\Models\AuditLog;
use App\Models\Chapter;
use App\Models\ContentChange;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use App\Services\Quality\RescoreService;
use Illuminate\Support\Facades\DB;

/**
 * Changes staff make to questions in Nova. The quality gate stays in charge: Publish only from Beta,
 * Beta only after the second solve (Checked) or back from Published, and anything can go back to
 * Draft. Every change is logged with the old and the new value.
 */
final class QuestionChanges
{
    /** More questions than this at once run in the background (a queued job). */
    public const BULK = 100;

    /** move => [target, allowed starting statuses] */
    private const MOVES = [
        'publish' => [QuestionStatus::Published, [QuestionStatus::Beta]],
        'beta' => [QuestionStatus::Beta, [QuestionStatus::Checked, QuestionStatus::Published]],
        'draft' => [QuestionStatus::Draft, [QuestionStatus::Checked, QuestionStatus::Beta, QuestionStatus::Published, QuestionStatus::Reported, QuestionStatus::Fixed, QuestionStatus::Retired]],
    ];

    /** Operations apply() knows, with the words staff see. */
    public const OPERATIONS = [
        'publish' => 'Publish',
        'beta' => 'Move to Beta',
        'draft' => 'Back to Draft',
        'fixed' => 'Mark fixed + re-score',
        'difficulty' => 'Change difficulty',
        'chapter' => 'Move to chapter',
    ];

    public function __construct(private readonly RescoreService $rescore) {}

    public function setStatus(Question $question, string $move, User $actor): bool
    {
        if (! isset(self::MOVES[$move])) {
            return false;
        }
        [$target, $from] = self::MOVES[$move];
        $old = $question->status;
        if (! in_array($old, $from, true)) {
            return false;
        }
        $question->status = $target;
        $question->save();
        AuditLog::change($actor, 'question.status', $question, ['status' => $old->value], ['status' => $target->value]);

        return true;
    }

    /**
     * The key was corrected (in Nova or the content files): the question becomes Fixed and every
     * stored answer is scored again with the new key, quietly. Returns how many answers changed, or
     * null when students never saw the question (nothing to fix).
     */
    public function markFixed(Question $question, User $actor): ?int
    {
        $old = $question->status;
        if (! $old->isVisibleToStudents()) {
            return null;
        }

        return DB::transaction(function () use ($question, $old, $actor): int {
            if ($old !== QuestionStatus::Fixed) {
                $question->status = QuestionStatus::Fixed;
                $question->save();
                AuditLog::change($actor, 'question.status', $question, ['status' => $old->value], ['status' => QuestionStatus::Fixed->value]);
            }

            return $this->rescore->rescore($question, $actor);
        });
    }

    public function setDifficulty(Question $question, int $difficulty, User $actor): bool
    {
        $old = $question->difficulty;
        if ($difficulty < 1 || $difficulty > 5 || $difficulty === $old) {
            return false;
        }
        $question->difficulty = $difficulty;
        $question->save();
        AuditLog::change($actor, 'question.difficulty', $question, ['difficulty' => $old], ['difficulty' => $difficulty]);

        return true;
    }

    public function moveToChapter(Question $question, Chapter $chapter, User $actor): bool
    {
        $old = $question->chapter_id;
        if ($old === $chapter->id) {
            return false;
        }

        DB::transaction(function () use ($question, $chapter, $old, $actor): void {
            $question->chapter_id = $chapter->id;
            $question->save();
            // Phones that have the old chapter must drop the question too.
            ContentChange::record('question', $question->id, $old, ContentChange::ACTION_UPSERT);
            AuditLog::change($actor, 'question.chapter', $question, ['chapter_id' => $old], ['chapter_id' => $chapter->id]);
        });

        return true;
    }

    /** A Draft copy with the same text, options and key, and no answers, reports or uid. */
    public function duplicate(Question $question, User $actor): Question
    {
        return DB::transaction(function () use ($question, $actor): Question {
            $copy = $question->replicate(['uid', 'content_hash', 'published_at', 'times_answered', 'times_correct', 'reports_count', 'version', 'status']);
            $copy->status = QuestionStatus::Draft;
            $copy->version = 1;
            $copy->save();
            foreach ($question->options()->get() as $option) {
                /** @var QuestionOption $option */
                $option->replicate()->fill(['question_id' => $copy->id])->save();
            }
            AuditLog::write($actor, 'question.duplicated', $copy, ['from' => $question->id]);

            return $copy->refresh();
        });
    }

    /**
     * One operation on many questions (the Nova actions, directly or from the background job).
     *
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $params  difficulty: 1–5, chapter_id: a chapter
     * @return array{changed: int, skipped: int}
     */
    public function apply(string $operation, array $ids, array $params, User $actor): array
    {
        $chapter = $operation === 'chapter' ? Chapter::query()->find((int) ($params['chapter_id'] ?? 0)) : null;
        $changed = 0;
        $found = 0;
        Question::query()->whereIn('id', $ids)->orderBy('id')->chunkById(200, function ($questions) use ($operation, $params, $chapter, $actor, &$changed, &$found): void {
            foreach ($questions as $question) {
                /** @var Question $question */
                $found++;
                $done = match ($operation) {
                    'publish', 'beta', 'draft' => $this->setStatus($question, $operation, $actor),
                    'fixed' => $this->markFixed($question, $actor) !== null,
                    'difficulty' => $this->setDifficulty($question, (int) ($params['difficulty'] ?? 0), $actor),
                    'chapter' => $chapter !== null && $this->moveToChapter($question, $chapter, $actor),
                    default => false,
                };
                $changed += $done ? 1 : 0;
            }
        });

        return ['changed' => $changed, 'skipped' => count(array_unique($ids)) - $changed];
    }
}
