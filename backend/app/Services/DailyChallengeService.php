<?php

namespace App\Services;

use App\Enums\PracticeMode;
use App\Enums\QuestionType;
use App\Models\Attempt;
use App\Models\DailyChallenge;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * The daily challenge: 10 single-choice questions, the same for every student of one grade and
 * stream on one day (Addis Ababa time), taking turns between their exam subjects.
 */
class DailyChallengeService
{
    public const TIMEZONE = 'Africa/Addis_Ababa';

    public const SIZE = 10;

    public function __construct(private readonly Settings $settings) {}

    public static function today(): string
    {
        return Carbon::now(self::TIMEZONE)->toDateString();
    }

    /** True for today and yesterday (a challenge started before midnight may be uploaded later). */
    public static function acceptsDate(string $date): bool
    {
        $today = Carbon::now(self::TIMEZONE)->startOfDay();

        return in_array($date, [$today->toDateString(), $today->copy()->subDay()->toDateString()], true);
    }

    public static function keyFor(User $user): string
    {
        $grade = $user->grade ?? 12;
        $stream = $grade >= 11 && $user->stream !== null ? $user->stream : 'all';

        return 'g'.$grade.'-'.$stream;
    }

    /**
     * The day's question list, saved the first time it is asked for.
     *
     * @return list<int>
     */
    public function questionIds(string $date, string $key): array
    {
        $saved = DailyChallenge::query()->whereDate('date', $date)->where('key', $key)->first();
        if ($saved !== null) {
            return array_map('intval', $saved->question_ids);
        }

        $ids = $this->draw($date, $key);
        if ($ids === []) {
            return [];
        }

        try {
            DailyChallenge::query()->create(['date' => $date, 'key' => $key, 'question_ids' => $ids]);
        } catch (UniqueConstraintViolationException) {
            // Another request saved it at the same moment: use that one.
            return $this->questionIds($date, $key);
        }

        return $ids;
    }

    /**
     * @return Collection<int, Question> in challenge order (hidden questions left out)
     */
    public function questions(string $date, string $key): Collection
    {
        $ids = $this->questionIds($date, $key);
        $found = Question::query()->visibleToStudents()->whereIn('id', $ids)->with('options')->get()->keyBy('id');

        return collect($ids)->map(fn (int $id) => $found->get($id))->filter()->values();
    }

    /** The student's first upload of that day's challenge, if any. */
    public function firstTry(User $user, string $date, string $key): ?Attempt
    {
        return Attempt::query()
            ->where('user_id', $user->id)
            ->where('mode', PracticeMode::DailyChallenge->value)
            ->whereDate('challenge_date', $date)
            ->where('challenge_key', $key)
            ->where('total', '>', 0)
            ->orderBy('id')
            ->first();
    }

    /**
     * "Better than X% of today's players": every other student's first try of the same challenge.
     *
     * @return array{0: int|null, 1: int}
     */
    public function percentile(Attempt $attempt): array
    {
        if ($attempt->challenge_date === null || $attempt->challenge_key === null || $attempt->total === 0) {
            return [null, 0];
        }
        $firsts = Attempt::query()
            ->where('mode', PracticeMode::DailyChallenge->value)
            ->whereDate('challenge_date', $attempt->challenge_date->toDateString())
            ->where('challenge_key', $attempt->challenge_key)
            ->where('user_id', '!=', $attempt->user_id)
            ->where('total', '>', 0)
            ->groupBy('user_id')
            ->selectRaw('min(id) as id');
        $others = Attempt::query()->whereIn('id', $firsts->toBase());
        $players = (clone $others)->count();
        if ($players < $this->settings->int('percentile_min_students')) {
            return [null, $players];
        }
        $lower = (clone $others)->whereRaw('correct * ? < ? * total', [$attempt->total, $attempt->correct])->count();

        return [(int) floor(100 * $lower / $players), $players];
    }

    /**
     * @return list<int>
     */
    private function draw(string $date, string $key): array
    {
        [$gradePart, $stream] = explode('-', $key, 2);
        $grade = (int) substr($gradePart, 1);
        $pool = $this->pool($grade, $stream === 'all' ? null : $stream);
        if ($pool->count() < self::SIZE && $grade !== 12) {
            // Not enough questions for this grade yet: use Grade 12 (always the main content).
            $pool = $this->pool(12, $stream === 'all' ? null : $stream);
        }
        if ($pool->isEmpty()) {
            return [];
        }

        $random = new Randomizer(new Mt19937(crc32('challenge|'.$date.'|'.$key)));
        /** @var array<int, list<int>> $bySubject */
        $bySubject = [];
        foreach ($pool as $row) {
            $bySubject[(int) $row->subject_id][] = (int) $row->id;
        }
        ksort($bySubject);
        $queues = [];
        foreach ($bySubject as $subjectId => $ids) {
            $queues[$subjectId] = $random->shuffleArray($ids);
        }
        $subjects = $random->shuffleArray(array_keys($queues));

        // Take turns between subjects until 10 questions are picked.
        $out = [];
        while (count($out) < self::SIZE) {
            $added = false;
            foreach ($subjects as $s) {
                if (count($out) >= self::SIZE) {
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
     * Visible single-choice questions (no reading passages) of the student's exam subjects.
     *
     * @return Collection<int, object{id: int, subject_id: int}>
     */
    private function pool(int $grade, ?string $stream): Collection
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
            ->where('subjects.is_exam_subject', true)
            ->where(fn ($s) => $s->whereNull('subjects.stream')->when($stream !== null, fn ($q) => $q->orWhere('subjects.stream', $stream)))
            ->orderBy('questions.id')
            ->toBase()
            ->get(['questions.id as id', 'subjects.id as subject_id']);

        return $rows;
    }
}
