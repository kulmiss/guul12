<?php

namespace App\Services;

use App\Enums\QuestionStatus;
use App\Models\Chapter;
use App\Models\Flashcard;
use App\Models\Mock;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Support\Facades\Cache;

/**
 * The real numbers the landing page shows (subjects, chapters, questions, flashcards, mock exams),
 * counted from what students can actually open. Cached for 10 minutes.
 */
class LandingStats
{
    public const CACHE_KEY = 'landing.stats';

    /**
     * @return array{totals: array{subjects: int, chapters: int, questions: int, flashcards: int, mocks: int},
     *     subjects: list<array{code: string, name: string, stream: string|null, chapters: int, questions: int}>}
     */
    public function get(): array
    {
        /** @var array{totals: array{subjects: int, chapters: int, questions: int, flashcards: int, mocks: int}, subjects: list<array{code: string, name: string, stream: string|null, chapters: int, questions: int}>} */
        return Cache::remember(self::CACHE_KEY, 600, fn (): array => $this->count());
    }

    /**
     * @return array{totals: array{subjects: int, chapters: int, questions: int, flashcards: int, mocks: int},
     *     subjects: list<array{code: string, name: string, stream: string|null, chapters: int, questions: int}>}
     */
    public function count(): array
    {
        $visible = array_map(fn (QuestionStatus $s): string => $s->value, QuestionStatus::visibleToStudents());

        // Chapters students can open: active chapter, active unit (the subject is checked below).
        $chapters = Chapter::query()
            ->join('units', 'units.id', '=', 'chapters.unit_id')
            ->where('chapters.is_active', true)
            ->where('units.is_active', true);

        $chaptersBySubject = (clone $chapters)
            ->selectRaw('units.subject_id as subject_id, count(*) as n')
            ->groupBy('units.subject_id')
            ->pluck('n', 'subject_id');

        $questionsBySubject = Question::query()
            ->join('chapters', 'chapters.id', '=', 'questions.chapter_id')
            ->join('units', 'units.id', '=', 'chapters.unit_id')
            ->where('chapters.is_active', true)
            ->where('units.is_active', true)
            ->whereIn('questions.status', $visible)
            ->selectRaw('units.subject_id as subject_id, count(*) as n')
            ->groupBy('units.subject_id')
            ->pluck('n', 'subject_id');

        $flashcardsBySubject = Flashcard::query()
            ->join('chapters', 'chapters.id', '=', 'flashcards.chapter_id')
            ->join('units', 'units.id', '=', 'chapters.unit_id')
            ->where('chapters.is_active', true)
            ->where('units.is_active', true)
            ->whereIn('flashcards.status', $visible)
            ->selectRaw('units.subject_id as subject_id, count(*) as n')
            ->groupBy('units.subject_id')
            ->pluck('n', 'subject_id');

        $mocksBySubject = Mock::query()->openToStudents()
            ->selectRaw('subject_id, count(*) as n')
            ->groupBy('subject_id')
            ->pluck('n', 'subject_id');

        $subjects = [];
        $totals = ['subjects' => 0, 'chapters' => 0, 'questions' => 0, 'flashcards' => 0, 'mocks' => 0];
        foreach (Subject::query()->where('is_active', true)->orderBy('sort')->orderBy('id')->get() as $subject) {
            $chapterCount = (int) ($chaptersBySubject[$subject->id] ?? 0);
            if ($chapterCount === 0) {
                continue;
            }
            $questionCount = (int) ($questionsBySubject[$subject->id] ?? 0);
            $subjects[] = [
                'code' => $subject->code,
                'name' => $subject->name,
                'stream' => $subject->stream,
                'chapters' => $chapterCount,
                'questions' => $questionCount,
            ];
            $totals['subjects']++;
            $totals['chapters'] += $chapterCount;
            $totals['questions'] += $questionCount;
            $totals['flashcards'] += (int) ($flashcardsBySubject[$subject->id] ?? 0);
            $totals['mocks'] += (int) ($mocksBySubject[$subject->id] ?? 0);
        }

        return ['totals' => $totals, 'subjects' => $subjects];
    }
}
