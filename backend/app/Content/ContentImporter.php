<?php

namespace App\Content;

use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Models\Chapter;
use App\Models\Flashcard;
use App\Models\Passage;
use App\Models\Question;
use App\Models\QuestionReview;
use App\Models\SolvedExercise;
use App\Models\Subject;
use App\Models\Summary;
use App\Models\Unit;
use App\Services\Quality\RescoreService;
use App\Services\Scoring\AnswerScorer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Writes validated content into the database. Safe to run again: rows are matched by
 * their uid (questions, exercises, flashcards, passages) or by number (units, chapters).
 *
 * Quality gate on import:
 *  - a question becomes visible (Beta) only when an independent second solve of its exact
 *    current text gives the same answer as the key;
 *  - otherwise it stays Draft (hidden) and is listed as a problem;
 *  - a visible question whose key alone was fixed is re-scored quietly.
 */
final class ContentImporter
{
    public function __construct(
        private readonly AnswerScorer $scorer,
        private readonly RescoreService $rescorer,
    ) {}

    /**
     * @param  list<CurriculumDef>  $curricula
     * @param  array<string, list<ReviewDef>>  $reviews
     */
    public function import(array $curricula, array $reviews): ImportReport
    {
        $report = new ImportReport;

        DB::transaction(function () use ($curricula, $reviews, $report): void {
            foreach ($curricula as $curriculum) {
                foreach ($curriculum->subjects as $subjectDef) {
                    $this->importSubject($curriculum->grade, $subjectDef, $reviews, $report);
                }
            }
        });

        // Re-scoring runs after the content is saved, each question in its own transaction.
        foreach ($report->toRescore as $questionId) {
            $question = Question::query()->find($questionId);
            if ($question !== null) {
                $report->answersRescored += $this->rescorer->rescore($question);
            }
        }

        return $report;
    }

    /**
     * @param  array<string, list<ReviewDef>>  $reviews
     */
    private function importSubject(int $grade, SubjectDef $def, array $reviews, ImportReport $report): void
    {
        $subject = Subject::query()->where('code', $def->code)->firstOrFail();
        $keptUnits = [];

        foreach ($def->units as $unitDef) {
            $unit = Unit::query()->firstOrNew([
                'subject_id' => $subject->id,
                'grade' => $grade,
                'stream' => $unitDef->stream,
                'number' => $unitDef->number,
            ]);
            $unit->fill(['title' => $unitDef->title, 'sort' => $unitDef->number, 'is_active' => true]);
            $report->count($unit->exists ? ($unit->isDirty() ? 'units_updated' : 'units_unchanged') : 'units_created');
            if ($unit->isDirty() || ! $unit->exists) {
                $unit->save();
            }
            $keptUnits[] = $unit->id;

            $keptChapters = [];
            foreach ($unitDef->chapters as $chapterDef) {
                $chapter = Chapter::query()->firstOrNew(['unit_id' => $unit->id, 'number' => $chapterDef->number]);
                $chapter->fill([
                    'title' => $chapterDef->title,
                    'textbook_url' => $def->textbookUrl,
                    'sort' => $chapterDef->number,
                    'is_active' => true,
                ]);
                $report->count($chapter->exists ? ($chapter->isDirty() ? 'chapters_updated' : 'chapters_unchanged') : 'chapters_created');
                if ($chapter->isDirty() || ! $chapter->exists) {
                    $chapter->save();
                }
                $keptChapters[] = $chapter->id;
                $this->importChapter($chapter, $chapterDef, $reviews, $report);
            }

            // Chapters no longer in the curriculum disappear from the app (kept for old attempts).
            $hidden = Chapter::query()->where('unit_id', $unit->id)->whereNotIn('id', $keptChapters)->where('is_active', true)->get();
            foreach ($hidden as $c) {
                $c->is_active = false;
                $c->save();
                $report->count('chapters_hidden');
            }
        }

        $hiddenUnits = Unit::query()->where('subject_id', $subject->id)->where('grade', $grade)
            ->whereNotIn('id', $keptUnits)->where('is_active', true)->get();
        foreach ($hiddenUnits as $u) {
            $u->is_active = false;
            $u->save();
            $report->count('units_hidden');
        }
    }

    /**
     * @param  array<string, list<ReviewDef>>  $reviews
     */
    private function importChapter(Chapter $chapter, ChapterDef $def, array $reviews, ImportReport $report): void
    {
        $this->publishSummaryPdf($chapter, $def, $report);

        if ($def->file === null) {
            return; // planned chapter: catalog entry only, nothing to touch yet
        }

        // Summary
        $summary = Summary::query()->where('chapter_id', $chapter->id)->first();
        if ($def->summary === null) {
            $summary?->delete();
        } else {
            $summary ??= new Summary(['chapter_id' => $chapter->id]);
            $summary->fill([
                'key_points' => $def->summary->keyPoints,
                'formulas' => $def->summary->formulas,
                'body' => $def->summary->body,
                'status' => QuestionStatus::Published,
                'source' => 'original',
            ]);
            if (! $summary->exists || $summary->isDirty()) {
                $summary->save();
                $report->count('summaries_saved');
            }
        }

        // Solved exercises and flashcards: plain upsert by uid; ones removed from the file are deleted.
        $keep = [];
        foreach ($def->exercises as $i => $e) {
            $row = SolvedExercise::query()->firstOrNew(['uid' => $e->uid]);
            $row->fill([
                'chapter_id' => $chapter->id, 'sort' => $i + 1, 'problem' => $e->problem,
                'steps' => $e->steps, 'final_answer' => $e->answer,
                'status' => QuestionStatus::Published, 'source' => 'original',
            ]);
            if (! $row->exists || $row->isDirty()) {
                $row->save();
                $report->count('exercises_saved');
            }
            $keep[] = $row->id;
        }
        SolvedExercise::query()->where('chapter_id', $chapter->id)->whereNotNull('uid')->whereNotIn('id', $keep)->get()->each->delete();

        $keep = [];
        foreach ($def->flashcards as $i => $f) {
            $row = Flashcard::query()->firstOrNew(['uid' => $f->uid]);
            $row->fill([
                'chapter_id' => $chapter->id, 'sort' => $i + 1, 'term' => $f->term,
                'definition' => $f->definition, 'status' => QuestionStatus::Published,
            ]);
            if (! $row->exists || $row->isDirty()) {
                $row->save();
                $report->count('flashcards_saved');
            }
            $keep[] = $row->id;
        }
        Flashcard::query()->where('chapter_id', $chapter->id)->whereNotNull('uid')->whereNotIn('id', $keep)->get()->each->delete();

        // Passages
        $passageIds = [];
        foreach ($def->passages as $p) {
            $row = Passage::query()->firstOrNew(['uid' => $p->uid]);
            $row->fill(['title' => $p->title, 'body' => $p->body, 'source' => 'original']);
            if (! $row->exists || $row->isDirty()) {
                $row->save();
                $report->count('passages_saved');
            }
            $passageIds[$p->uid] = $row->id;
        }

        // Questions
        $seen = [];
        foreach ($def->questions as $q) {
            $passage = $def->passage($q->passageUid);
            $question = $this->importQuestion($chapter, $q, $passage, $q->passageUid !== null ? $passageIds[$q->passageUid] : null, $reviews[$q->uid] ?? [], $report);
            $seen[] = $question->id;
        }

        // Questions removed from the file are retired, never deleted (attempts point to them).
        $retire = Question::query()
            ->where('chapter_id', $chapter->id)
            ->whereNotNull('uid')
            ->whereNotIn('id', $seen)
            ->where('status', '!=', QuestionStatus::Retired->value)
            ->get();
        foreach ($retire as $old) {
            $old->status = QuestionStatus::Retired;
            $old->save();
            $report->count('questions_retired');
        }
    }

    /**
     * Copies the chapter's summary PDF to the public disk under a name that contains its hash,
     * so a changed PDF gets a new URL (no stale copies in phone or browser caches).
     */
    private function publishSummaryPdf(Chapter $chapter, ChapterDef $def, ImportReport $report): void
    {
        $disk = Storage::disk('public');
        $target = null;
        if ($def->summaryPdf !== null) {
            $hash = hash_file('sha256', $def->summaryPdf);
            $target = 'summaries/'.$def->code.'-'.substr((string) $hash, 0, 12).'.pdf';
        }
        if ($chapter->summary_pdf_path === $target && ($target === null || $disk->exists($target))) {
            return;
        }

        if ($target !== null) {
            $stream = fopen((string) $def->summaryPdf, 'rb');
            if ($stream === false || ! $disk->put($target, $stream)) {
                throw new \RuntimeException("Could not publish {$def->summaryPdf}");
            }
            fclose($stream);
        }
        $old = $chapter->summary_pdf_path;
        $chapter->summary_pdf_path = $target;
        $chapter->save();
        if ($old !== null && $old !== $target) {
            $disk->delete($old);
        }
        $report->count($target === null ? 'summary_pdfs_removed' : 'summary_pdfs_published');
    }

    /**
     * @param  list<ReviewDef>  $reviews
     */
    private function importQuestion(Chapter $chapter, QuestionDef $def, ?PassageDef $passage, ?int $passageId, array $reviews, ImportReport $report): Question
    {
        $hash = $def->contentHash($passage);
        $reviewHash = $def->reviewHash($passage);

        /** @var Question|null $question */
        $question = Question::query()->where('uid', $def->uid)->first();
        $isNew = $question === null;
        $oldStatus = $question?->status;
        $wasVisible = $oldStatus?->isVisibleToStudents() ?? false;
        $changed = $isNew || $question->content_hash !== $hash || $question->chapter_id !== $chapter->id;
        $keyOnlyFix = false;

        if ($question === null) {
            $question = new Question(['uid' => $def->uid]);
        }

        if ($changed) {
            if (! $isNew) {
                $sameText = $question->type === $def->type
                    && $question->stem === $def->stem
                    && $question->media_path === $def->media
                    && $question->passage_id === $passageId
                    && $this->optionsOf($question) === $this->defOptions($def);
                $keyOnlyFix = $sameText && $question->answer_key != $def->answerKey;
            }
            $optionsChanged = $isNew || $this->optionsOf($question) !== $this->defOptions($def);

            $question->fill([
                'chapter_id' => $chapter->id,
                'passage_id' => $passageId,
                'type' => $def->type,
                'stem' => $def->stem,
                'media_path' => $def->media,
                'difficulty' => $def->difficulty,
                'answer_key' => $def->answerKey,
                'explanation' => $def->explanation,
                'source' => $def->source,
                'tags' => $def->tags === [] ? null : $def->tags,
            ]);
            $question->uid = $def->uid;
            $question->content_hash = $hash;
            if (! $isNew && $optionsChanged && ! $question->isDirty(['type', 'stem', 'media_path', 'answer_key', 'explanation', 'passage_id'])) {
                // Only the options changed: the saving hook does not see that, so bump here.
                $question->version = $question->version + 1;
            }
            if ($isNew) {
                $question->status = QuestionStatus::Draft;
                $question->version = 1;
            }
            $question->save();

            if ($optionsChanged) {
                $this->replaceOptions($question, $def);
            }
            $report->count($isNew ? 'questions_created' : 'questions_updated');
        } else {
            $report->count('questions_unchanged');
        }

        // Record every second solve made for this exact text, and decide visibility.
        $verdict = $this->judge($question, $def, $reviewHash, $reviews);
        $status = match (true) {
            $verdict !== true => QuestionStatus::Draft,
            $changed && in_array($oldStatus, [QuestionStatus::Published, QuestionStatus::Reported, QuestionStatus::Fixed], true) => QuestionStatus::Fixed,
            $changed => QuestionStatus::Beta,
            in_array($question->status, [QuestionStatus::Draft, QuestionStatus::Checked, QuestionStatus::Retired], true) => QuestionStatus::Beta,
            default => $question->status,
        };
        if ($verdict === null) {
            $report->problem("{$def->uid}: no second solve for the current text (stays hidden)");
        } elseif ($verdict === false) {
            $report->problem("{$def->uid}: second solve disagrees with the key (stays hidden)");
        }
        if ($status !== $question->status) {
            $question->status = $status;
            $question->save();
        }
        $report->count('status_'.$status->value);

        if ($keyOnlyFix && $wasVisible && $status->isVisibleToStudents()) {
            $report->toRescore[] = $question->id;
        }

        return $question;
    }

    /**
     * true = an independent solve of this exact text matches the key; false = a solve disagrees;
     * null = no solve for this text yet.
     *
     * @param  list<ReviewDef>  $reviews
     */
    private function judge(Question $question, QuestionDef $def, string $reviewHash, array $reviews): ?bool
    {
        $verdict = null;
        foreach ($reviews as $review) {
            if (! hash_equals($reviewHash, $review->hash)) {
                continue; // made for an older text
            }
            $response = self::reviewResponse($def->type, $review->response);
            $matches = $this->scorer->isCorrect($def->type, $def->answerKey, $response);

            QuestionReview::query()->updateOrCreate(
                ['question_id' => $question->id, 'question_version' => $question->version, 'reviewer_name' => $review->reviewer],
                ['reviewer_id' => null, 'response' => $response, 'matches_key' => $matches, 'note' => $review->note],
            );

            if (! $matches) {
                return false; // any disagreement blocks the question
            }
            $verdict = true;
        }

        return $verdict;
    }

    /**
     * Reviewer answers use the same friendly format as the content files.
     */
    public static function reviewResponse(QuestionType $type, mixed $answer): mixed
    {
        if ($type === QuestionType::TrueFalse && is_bool($answer)) {
            return $answer ? 'TRUE' : 'FALSE';
        }
        if ($type === QuestionType::Numeric && is_array($answer) && array_key_exists('value', $answer)) {
            return $answer['value'];
        }

        return $answer;
    }

    /**
     * @return list<array{0: string, 1: string|null, 2: string}>
     */
    private function optionsOf(Question $question): array
    {
        if (! $question->exists) {
            return [];
        }

        return array_values(DB::table('question_options')->where('question_id', $question->id)->orderBy('sort')->orderBy('id')
            ->get(['key', 'side', 'body'])
            ->map(fn (object $o): array => [(string) $o->key, $o->side === null ? null : (string) $o->side, (string) $o->body])
            ->all());
    }

    /**
     * @return list<array{0: string, 1: string|null, 2: string}>
     */
    private function defOptions(QuestionDef $def): array
    {
        return array_map(fn (OptionDef $o): array => [$o->key, $o->side, $o->body], $def->options);
    }

    /**
     * Options are written directly (not through the model) so the question's version
     * goes up once per import, not once per option.
     */
    private function replaceOptions(Question $question, QuestionDef $def): void
    {
        DB::table('question_options')->where('question_id', $question->id)->delete();
        $now = now();
        $rows = [];
        foreach ($def->options as $i => $o) {
            $rows[] = [
                'question_id' => $question->id, 'key' => $o->key, 'side' => $o->side, 'body' => $o->body,
                'media_path' => null, 'sort' => $i + 1, 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        if ($rows !== []) {
            DB::table('question_options')->insert($rows);
        }
    }
}
