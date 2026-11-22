<?php

namespace App\Services;

use App\Enums\PracticeMode;
use App\Enums\UserRole;
use App\Models\Attempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Weekly leaderboard. Points are the same XP the app shows: 10 per right answer, 2 per wrong
 * answer, 50 per finished exam, from server-scored attempts finished since Monday 00:00
 * (Addis Ababa time). Only students who chose a nickname appear; only nickname + avatar are shown.
 */
class Leaderboard
{
    public const TIMEZONE = 'Africa/Addis_Ababa';

    public const TOP = 50;

    public const XP_CORRECT = 10;

    public const XP_WRONG = 2;

    public const XP_EXAM = 50;

    private const CACHE_SECONDS = 60;

    /** Monday 00:00 in Addis Ababa, as a UTC time. */
    public static function weekStart(Carbon $now): Carbon
    {
        return $now->copy()->setTimezone(self::TIMEZONE)->startOfWeek(Carbon::MONDAY)->utc();
    }

    /**
     * Everyone with points this week, best first (ties: earlier account first).
     *
     * @return list<array{user_id: int, xp: int}>
     */
    public function standings(Carbon $now): array
    {
        $start = self::weekStart($now);

        /** @var list<array{user_id: int, xp: int}> $rows */
        $rows = Cache::remember(
            'leaderboard.'.$start->format('Y-m-d').'.'.intdiv($now->getTimestamp(), self::CACHE_SECONDS),
            self::CACHE_SECONDS,
            function () use ($start, $now): array {
                return $this->weekAttempts($start, $now)
                    ->join('users', 'users.id', '=', 'attempts.user_id')
                    ->where('users.role', UserRole::Student->value)
                    ->whereNotNull('users.nickname')
                    ->groupBy('attempts.user_id')
                    ->selectRaw('attempts.user_id as user_id, '.self::xpSql().' as xp', self::examModes())
                    ->orderByDesc('xp')
                    ->orderBy('attempts.user_id')
                    ->toBase()
                    ->get()
                    ->map(fn (object $r): array => ['user_id' => (int) $r->user_id, 'xp' => (int) $r->xp])
                    ->filter(fn (array $r): bool => $r['xp'] > 0)
                    ->values()
                    ->all();
            },
        );

        return $rows;
    }

    /** This week's points of one student (live, not cached). */
    public function xpOf(User $user, Carbon $now): int
    {
        $xp = $this->weekAttempts(self::weekStart($now), $now)
            ->where('attempts.user_id', $user->id)
            ->selectRaw(self::xpSql().' as xp', self::examModes())
            ->toBase()
            ->value('xp');

        return (int) $xp;
    }

    /**
     * Competition ranking (1, 2, 2, 4): one more than the number of students with more points.
     *
     * @param  list<array{user_id: int, xp: int}>  $standings
     */
    public static function rankFor(array $standings, int $userId, int $xp): ?int
    {
        if ($xp <= 0) {
            return null;
        }
        $ahead = 0;
        foreach ($standings as $row) {
            if ($row['user_id'] !== $userId && $row['xp'] > $xp) {
                $ahead++;
            }
        }

        return $ahead + 1;
    }

    /**
     * @return Builder<Attempt>
     */
    private function weekAttempts(Carbon $start, Carbon $now): Builder
    {
        // finished_at comes from the phone: never count a time before Monday or in the future.
        return Attempt::query()
            ->where('attempts.finished_at', '>=', $start)
            ->where('attempts.finished_at', '<=', $now->copy()->utc());
    }

    private static function xpSql(): string
    {
        $placeholders = implode(', ', array_fill(0, count(self::examModes()), '?'));

        return 'SUM(attempts.correct * '.self::XP_CORRECT
            .' + (attempts.total - attempts.correct) * '.self::XP_WRONG
            .' + CASE WHEN attempts.mode IN ('.$placeholders.') THEN '.self::XP_EXAM.' ELSE 0 END)';
    }

    /**
     * @return list<string>
     */
    private static function examModes(): array
    {
        return array_values(array_map(
            fn (PracticeMode $m): string => $m->value,
            array_filter(PracticeMode::cases(), fn (PracticeMode $m): bool => $m->isExam()),
        ));
    }
}
