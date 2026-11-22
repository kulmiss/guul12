<?php

namespace App\Services;

use App\Enums\PracticeMode;
use App\Models\Attempt;
use App\Models\DailyChallenge;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The daily challenge: 10 single-choice questions, the same for every student of one grade and
 * stream on one day (Addis Ababa time), taking turns between their exam subjects.
 */
class DailyChallengeService
{
    public const TIMEZONE = 'Africa/Addis_Ababa';

    public const SIZE = 10;

    public function __construct(
        private readonly Settings $settings,
        private readonly QuestionPicker $picker,
    ) {}

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

        return $this->picker->pick(
            (int) substr($gradePart, 1),
            $stream === 'all' ? null : $stream,
            null,
            self::SIZE,
            crc32('challenge|'.$date.'|'.$key),
        );
    }
}
