<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\Question;
use Illuminate\Support\Collection;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Picks single-choice questions (no reading passages) of a student's exam subjects, taking turns
 * between subjects. Used by the daily challenge and battles. The same seed gives the same list.
 */
class QuestionPicker
{
    /**
     * @return list<int> question ids
     */
    public function pick(int $grade, ?string $stream, ?int $subjectId, int $size, int $seed): array
    {
        $pool = $this->pool($grade, $stream, $subjectId);
        if ($pool->count() < $size && $grade !== 12) {
            // Not enough questions for this grade yet: use Grade 12 (always the main content).
            $pool = $this->pool(12, $stream, $subjectId);
        }
        if ($pool->isEmpty()) {
            return [];
        }

        $random = new Randomizer(new Mt19937($seed));
        /** @var array<int, list<int>> $bySubject */
        $bySubject = [];
        foreach ($pool as $row) {
            $bySubject[(int) $row->subject_id][] = (int) $row->id;
        }
        ksort($bySubject);
        $queues = [];
        foreach ($bySubject as $id => $ids) {
            $queues[$id] = $random->shuffleArray($ids);
        }
        $subjects = $random->shuffleArray(array_keys($queues));

        // Take turns between subjects until enough questions are picked.
        $out = [];
        while (count($out) < $size) {
            $added = false;
            foreach ($subjects as $s) {
                if (count($out) >= $size) {
                    break;
                }
                if ($queues[$s] !== []) {
                    $out[] = array_shift($queues[$s]);
                    $added = true;
                }
            }
            if (! $added) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return Collection<int, object{id: int, subject_id: int}>
     */
    private function pool(int $grade, ?string $stream, ?int $subjectId): Collection
    {
        /** @var Collection<int, object{id: int, subject_id: int}> $rows */
        $rows = Question::query()
            ->visibleToStudents()
            ->where('questions.type', QuestionType::SingleChoice->value)
            ->whereNull('questions.passage_id')
            ->join('chapters', 'chapters.id', '=', 'questions.chapter_id')
            ->join('units', 'units.id', '=', 'chapters.unit_id')
            ->join('subjects', 'subjects.id', '=', 'units.subject_id')
            ->where('chapters.is_active', true)
            ->where('units.is_active', true)
            ->where('units.grade', $grade)
            ->where(fn ($s) => $s->whereNull('units.stream')->when($stream !== null, fn ($q) => $q->orWhere('units.stream', $stream)))
            ->where('subjects.is_active', true)
            ->where('subjects.is_exam_subject', true)
            ->where(fn ($s) => $s->whereNull('subjects.stream')->when($stream !== null, fn ($q) => $q->orWhere('subjects.stream', $stream)))
            ->when($subjectId !== null, fn ($q) => $q->where('subjects.id', $subjectId))
            ->orderBy('questions.id')
            ->toBase()
            ->get(['questions.id as id', 'subjects.id as subject_id']);

        return $rows;
    }
}
