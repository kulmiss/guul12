<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Support\Collection;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Builds a random mock from the bank, like content/tools/make-mocks.py does for fixed mocks:
 * choice questions only, every chapter in proportion, reading passages kept whole, chapter order,
 * and an answer key with no pattern (same letter 4 times in a row, or A, B, C, D, A… 5 in a row).
 */
class RandomExamBuilder
{
    public const SIZE = 50;

    public const SECONDS_PER_QUESTION = 90;

    private const NEXT = ['A' => 'B', 'B' => 'C', 'C' => 'D', 'D' => 'A'];

    private Randomizer $random;

    /**
     * @return Collection<int, Question> in exam order (empty when the subject has no choice questions)
     */
    public function build(Subject $subject, int $grade, ?string $stream, int $seed): Collection
    {
        $this->random = new Randomizer(new Mt19937($seed));

        /** @var Collection<int, Question> $pool */
        $pool = Question::query()
            ->visibleToStudents()
            ->whereIn('type', [QuestionType::SingleChoice->value, QuestionType::PassageChoice->value])
            ->whereHas('chapter', fn ($c) => $c->where('is_active', true)
                ->whereHas('unit', fn ($u) => $u->where('is_active', true)
                    ->where('subject_id', $subject->id)
                    ->where('grade', $grade)
                    ->where(fn ($s) => $s->whereNull('stream')->when($stream !== null, fn ($q) => $q->orWhere('stream', $stream)))))
            ->with(['options', 'passage', 'chapter.unit'])
            ->orderBy('id')
            ->get();

        // Units of the draw: one question, or all questions of one passage.
        /** @var array<int, list<list<Question>>> $byChapter chapter id => units */
        $byChapter = [];
        /** @var array<int, list<Question>> $passageUnits */
        $passageUnits = [];
        foreach ($pool as $q) {
            if ($q->passage_id !== null) {
                $passageUnits[$q->passage_id][] = $q;
            } else {
                $byChapter[$q->chapter_id][] = [$q];
            }
        }
        foreach ($passageUnits as $unit) {
            $byChapter[$unit[0]->chapter_id][] = $unit;
        }
        if ($byChapter === []) {
            return collect();
        }

        // Every unit in chapter order (shuffled inside each chapter), then evenly spaced picks,
        // so each chapter gets its share.
        $chapterOrder = $this->chapterOrder($byChapter);
        $all = [];
        foreach ($chapterOrder as $id) {
            foreach ($this->random->shuffleArray($byChapter[$id]) as $unit) {
                $all[] = ['chapter' => $id, 'unit' => $unit];
            }
        }
        $step = count($all) / min(self::SIZE, count($all));
        $chosen = [];
        $count = 0;
        for ($i = 0; $count < self::SIZE && (int) floor($i * $step) < count($all); $i++) {
            $pick = $all[(int) floor($i * $step)];
            $chosen[$pick['chapter']][] = $pick['unit'];
            $count += count($pick['unit']);
        }

        return collect($this->ordered($chosen, $chapterOrder));
    }

    /**
     * True when the key has the same letter 4 times in a row, or A, B, C, D, A… 5 in a row.
     *
     * @param  list<Question>  $questions
     */
    public function hasPattern(array $questions): bool
    {
        $same = 0;
        $cycle = 0;
        $previous = null;
        foreach ($questions as $q) {
            $letter = is_string($q->answer_key['correct'] ?? null) ? $q->answer_key['correct'] : null;
            $same = $letter !== null && $letter === $previous ? $same + 1 : 1;
            $cycle = $letter !== null && $previous !== null && (self::NEXT[$previous] ?? null) === $letter ? $cycle + 1 : 1;
            if ($same >= 4 || $cycle >= 5) {
                return true;
            }
            $previous = $letter;
        }

        return false;
    }

    /**
     * Chapter ids by unit order, then chapter order.
     *
     * @param  array<int, list<list<Question>>>  $byChapter
     * @return list<int>
     */
    private function chapterOrder(array $byChapter): array
    {
        $ids = array_keys($byChapter);
        usort($ids, function (int $a, int $b) use ($byChapter): int {
            $ca = $byChapter[$a][0][0]->chapter;
            $cb = $byChapter[$b][0][0]->chapter;

            return [$ca->unit->sort, $ca->unit->number, $ca->sort, $ca->number] <=> [$cb->unit->sort, $cb->unit->number, $cb->sort, $cb->number];
        });

        return $ids;
    }

    /**
     * Chapter order; shuffle inside chapters, then (if needed) the whole exam,
     * until the key shows no pattern.
     *
     * @param  array<int, list<list<Question>>>  $chosen
     * @param  list<int>  $chapterOrder
     * @return list<Question>
     */
    private function ordered(array $chosen, array $chapterOrder): array
    {
        $chapterOrder = array_values(array_filter($chapterOrder, fn (int $id) => isset($chosen[$id])));
        for ($try = 0; $try < 2000; $try++) {
            $out = [];
            foreach ($chapterOrder as $id) {
                foreach ($this->random->shuffleArray($chosen[$id]) as $unit) {
                    array_push($out, ...$unit);
                }
            }
            if (! $this->hasPattern($out)) {
                return $out;
            }
        }

        $units = array_merge(...array_values($chosen));
        for ($try = 0; $try < 5000; $try++) {
            $units = $this->random->shuffleArray($units);
            $out = array_merge(...$units);
            if (! $this->hasPattern($out)) {
                return $out;
            }
        }

        // A tiny bank may not allow it; the exam is still valid, only less tidy.
        return array_merge(...$units);
    }
}
