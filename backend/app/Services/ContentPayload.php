<?php

namespace App\Services;

use App\Enums\QuestionStatus;
use App\Models\Chapter;
use App\Models\Flashcard;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\SolvedExercise;
use App\Models\Subject;
use App\Models\Unit;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the JSON the app stores offline. Only content students may see is included.
 */
class ContentPayload
{
    /**
     * @return array<string, mixed>
     */
    public function catalog(): array
    {
        $subjects = Subject::query()->where('is_active', true)->orderBy('sort')->orderBy('id')->get();
        $units = Unit::query()->where('is_active', true)->orderBy('sort')->orderBy('number')->get();
        $chapters = Chapter::query()->where('is_active', true)->orderBy('sort')->orderBy('number')->get();

        return [
            'subjects' => $subjects->map(fn (Subject $s): array => [
                'id' => $s->id,
                'code' => $s->code,
                'name' => $s->name,
                'stream' => $s->stream,
                'is_exam_subject' => $s->is_exam_subject,
                'icon' => $s->icon,
                'sort' => $s->sort,
            ])->values()->all(),
            'units' => $units->map(fn (Unit $u): array => [
                'id' => $u->id,
                'subject_id' => $u->subject_id,
                'grade' => $u->grade,
                'stream' => $u->stream,
                'number' => $u->number,
                'title' => $u->title,
                'sort' => $u->sort,
            ])->values()->all(),
            'chapters' => $chapters->map(fn (Chapter $c): array => $this->chapterHeader($c))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function chapterHeader(Chapter $chapter): array
    {
        return [
            'id' => $chapter->id,
            'unit_id' => $chapter->unit_id,
            'number' => $chapter->number,
            'title' => $chapter->title,
            'textbook_url' => $chapter->textbook_url,
            'video_youtube_id' => $chapter->video_youtube_id,
            'video_access' => $chapter->video_access->value,
            'has_summary_pdf' => $chapter->summary_pdf_path !== null,
            // Free for everyone (QUESTIONS.md #33). The PDF links only to the price-free landing page.
            'summary_pdf_url' => $chapter->summary_pdf_path === null ? null : Storage::disk('public')->url($chapter->summary_pdf_path),
            'sort' => $chapter->sort,
        ];
    }

    /**
     * Everything the app needs to study one chapter offline.
     *
     * @return array<string, mixed>
     */
    public function chapter(Chapter $chapter): array
    {
        $visible = array_map(fn (QuestionStatus $s): string => $s->value, QuestionStatus::visibleToStudents());

        $summary = $chapter->summary()->whereIn('status', $visible)->first();
        $exercises = $chapter->solvedExercises()->whereIn('status', $visible)->orderBy('sort')->orderBy('id')->get();
        $flashcards = $chapter->flashcards()->whereIn('status', $visible)->orderBy('sort')->orderBy('id')->get();
        $questions = $chapter->questions()->visibleToStudents()->with(['options', 'passage'])->orderBy('id')->get();

        $passages = $questions->pluck('passage')->filter()->unique('id')->values();

        return [
            'chapter' => $this->chapterHeader($chapter),
            'summary' => $summary === null ? null : [
                'id' => $summary->id,
                'key_points' => $summary->key_points,
                'formulas' => $summary->formulas,
                'body' => $summary->body,
            ],
            'solved_exercises' => $exercises->map(fn (SolvedExercise $e): array => [
                'id' => $e->id,
                'problem' => $e->problem,
                'steps' => $e->steps,
                'final_answer' => $e->final_answer,
                'sort' => $e->sort,
            ])->values()->all(),
            'flashcards' => $flashcards->map(fn (Flashcard $f): array => [
                'id' => $f->id,
                'term' => $f->term,
                'definition' => $f->definition,
                'sort' => $f->sort,
            ])->values()->all(),
            'passages' => $passages->map(fn ($p): array => [
                'id' => $p->id,
                'title' => $p->title,
                'body' => $p->body,
            ])->all(),
            'questions' => $questions->map(fn (Question $q): array => $this->question($q))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function question(Question $question): array
    {
        return [
            'id' => $question->id,
            'chapter_id' => $question->chapter_id,
            'passage_id' => $question->passage_id,
            'type' => $question->type->value,
            'stem' => $question->stem,
            'media_url' => $question->media_path !== null ? asset('storage/'.$question->media_path) : null,
            'difficulty' => $question->difficulty,
            'is_beta' => $question->status === QuestionStatus::Beta,
            // Needed for instant feedback in offline practice; the server still re-scores every answer.
            'answer_key' => $question->answer_key,
            'explanation' => $question->explanation,
            'version' => $question->version,
            'options' => $question->options->map(fn (QuestionOption $o): array => [
                'key' => $o->key,
                'side' => $o->side,
                'body' => $o->body,
                'media_url' => $o->media_path !== null ? asset('storage/'.$o->media_path) : null,
            ])->values()->all(),
        ];
    }
}
