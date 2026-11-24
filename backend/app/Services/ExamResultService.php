<?php

namespace App\Services;

use App\Enums\PracticeMode;
use App\Models\Attempt;
use Illuminate\Support\Facades\DB;

/**
 * What an exam result shows besides the score: right answers per chapter and, for a fixed mock
 * taken by enough students, the share of students who scored lower ("better than X%").
 */
class ExamResultService
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @return array{chapters: list<array{chapter_id: int, total: int, correct: int}>, better_than_percent: int|null, students: int|null}
     */
    public function details(Attempt $attempt): array
    {
        $rows = DB::table('attempt_answers')
            ->join('questions', 'questions.id', '=', 'attempt_answers.question_id')
            ->where('attempt_answers.attempt_id', $attempt->id)
            ->groupBy('questions.chapter_id')
            ->orderBy('questions.chapter_id')
            ->selectRaw('questions.chapter_id as chapter_id, count(*) as total, sum(case when attempt_answers.is_correct then 1 else 0 end) as correct')
            ->get()
            ->map(fn (object $r): array => [
                'chapter_id' => (int) $r->chapter_id,
                'total' => (int) $r->total,
                'correct' => (int) $r->correct,
            ])->all();
        $chapters = array_values($rows);

        [$percent, $students] = $this->percentile($attempt);

        return ['chapters' => $chapters, 'better_than_percent' => $percent, 'students' => $students];
    }

    /**
     * Compares with every other student's FIRST try of the same mock (no names, no repeats).
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function percentile(Attempt $attempt): array
    {
        if ($attempt->mode !== PracticeMode::ExamFixed || $attempt->mock_id === null || $attempt->total === 0) {
            return [null, null];
        }

        $firsts = DB::table('attempts')
            ->where('mock_id', $attempt->mock_id)
            ->where('mode', PracticeMode::ExamFixed->value)
            ->where('user_id', '!=', $attempt->user_id)
            ->where('total', '>', 0)
            ->groupBy('user_id')
            ->selectRaw('min(id) as id');

        $others = DB::table('attempts')->whereIn('id', $firsts);
        $students = (clone $others)->count();
        if ($students < $this->settings->int('percentile_min_students')) {
            return [null, $students];
        }

        // correct/total < mine, compared without division: correct * my_total < my_correct * total.
        $lower = (clone $others)
            ->whereRaw('correct * ? < ? * total', [$attempt->total, $attempt->correct])
            ->count();

        return [(int) floor(100 * $lower / $students), $students];
    }
}
