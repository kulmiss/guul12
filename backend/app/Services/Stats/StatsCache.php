<?php

namespace App\Services\Stats;

use App\Enums\QuestionStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Cached dashboard numbers. The scheduler refreshes every number every 10 minutes (guul:stats), so
 * opening the dashboard only reads the cache. If a number is missing (first start), it is worked out
 * once and kept.
 */
class StatsCache
{
    private const PREFIX = 'stats.v1.';

    /** Kept a little longer than the refresh interval, so a late refresh never empties a card. */
    private const TTL_SECONDS = 3 * 3600;

    public function __construct(private readonly DashboardStats $stats) {}

    /**
     * Every number the dashboards show: name => [needs a range?, how to work it out].
     *
     * @return array<string, array{0: bool, 1: \Closure(DashboardStats, StatsRange): mixed}>
     */
    public static function definitions(): array
    {
        return [
            // With a range.
            'new_students' => [true, fn (DashboardStats $s, StatsRange $r) => $s->newStudents($r)],
            'active_students' => [true, fn (DashboardStats $s, StatsRange $r) => $s->activeStudents($r)],
            'answers_given' => [true, fn (DashboardStats $s, StatsRange $r) => $s->answersGiven($r)],
            'premium_sold' => [true, fn (DashboardStats $s, StatsRange $r) => $s->premiumSold($r)],
            'battles_played' => [true, fn (DashboardStats $s, StatsRange $r) => $s->battlesPlayed($r)],
            'referrals_rewarded' => [true, fn (DashboardStats $s, StatsRange $r) => $s->referralsRewarded($r)],
            'new_students_per_day' => [true, fn (DashboardStats $s, StatsRange $r) => $s->newStudentsPerDay($r)],
            'answering_students_per_day' => [true, fn (DashboardStats $s, StatsRange $r) => $s->answeringStudentsPerDay($r)],
            'answers_per_day' => [true, fn (DashboardStats $s, StatsRange $r) => $s->answersPerDay($r)],
            'study_minutes_per_day' => [true, fn (DashboardStats $s, StatsRange $r) => $s->studyMinutesPerDay($r)],
            'premium_sold_per_week' => [true, fn (DashboardStats $s, StatsRange $r) => $s->premiumSoldPerWeek($r)],
            // Now.
            'premium_active' => [false, fn (DashboardStats $s) => $s->premiumActiveNow()],
            'premium_expiring_7' => [false, fn (DashboardStats $s) => $s->premiumExpiringSoon(7)],
            'payments_waiting' => [false, fn (DashboardStats $s) => $s->paymentsWaiting()],
            'open_question_reports' => [false, fn (DashboardStats $s) => $s->openQuestionReports()],
            'open_nickname_reports' => [false, fn (DashboardStats $s) => $s->openNicknameReports()],
            'questions_published' => [false, fn (DashboardStats $s) => $s->questionsWithStatus(QuestionStatus::Published)],
            'questions_beta' => [false, fn (DashboardStats $s) => $s->questionsWithStatus(QuestionStatus::Beta)],
            'beta_near_publishing' => [false, fn (DashboardStats $s) => $s->betaNearPublishing()],
            'students_by_grade' => [false, fn (DashboardStats $s) => $s->studentsBy('grade')],
            'students_by_stream' => [false, fn (DashboardStats $s) => $s->studentsBy('stream')],
            'students_by_region' => [false, fn (DashboardStats $s) => $s->studentsBy('region')],
            'students_by_gender' => [false, fn (DashboardStats $s) => $s->studentsBy('gender')],
            'premium_vs_free' => [false, fn (DashboardStats $s) => $s->premiumVsFree()],
            'questions_by_status' => [false, fn (DashboardStats $s) => $s->questionsByStatus()],
            'hardest_questions' => [false, fn (DashboardStats $s) => $s->hardestQuestions()],
            'weakest_chapters' => [false, fn (DashboardStats $s) => $s->weakestChapters()],
            'most_reported_questions' => [false, fn (DashboardStats $s) => $s->mostReportedQuestions()],
            'top_students_week' => [false, fn (DashboardStats $s) => $s->topStudentsThisWeek()],
            'latest_payments' => [false, fn (DashboardStats $s) => $s->latestPayments()],
            'latest_reports' => [false, fn (DashboardStats $s) => $s->latestReports()],
        ];
    }

    /** Ranges every ranged number is kept for ("Today" for value cards, then 7/30/90 days, term, all). */
    public const RANGES = ['1', '7', '30', '90', 'TERM', 'ALL'];

    /** A number from the cache; worked out and kept if missing. */
    public function get(string $name, ?string $range = null): mixed
    {
        [$ranged] = self::definition($name);
        $range = $ranged ? StatsRange::make($range ?? '30')->key : null;
        $stored = Cache::get(self::key($name, $range));
        if (is_array($stored) && array_key_exists('data', $stored)) {
            return $stored['data'];
        }

        return $this->refresh($name, $range);
    }

    /** When this number was last worked out (null if never). */
    public function updatedAt(string $name, ?string $range = null): ?Carbon
    {
        [$ranged] = self::definition($name);
        $stored = Cache::get(self::key($name, $ranged ? StatsRange::make($range ?? '30')->key : null));

        return is_array($stored) && is_int($stored['at'] ?? null) ? Carbon::createFromTimestamp($stored['at']) : null;
    }

    /** Works out every number for every range and keeps it. Returns how many were stored. */
    public function refreshAll(): int
    {
        $count = 0;
        foreach (self::definitions() as $name => [$ranged]) {
            foreach ($ranged ? self::RANGES : [null] as $range) {
                $this->refresh($name, $range);
                $count++;
            }
        }

        return $count;
    }

    private function refresh(string $name, ?string $range): mixed
    {
        [, $compute] = self::definition($name);
        $data = $compute($this->stats, StatsRange::make($range ?? 'ALL'));
        Cache::put(self::key($name, $range), ['data' => $data, 'at' => Carbon::now()->getTimestamp()], self::TTL_SECONDS);

        return $data;
    }

    /** @return array{0: bool, 1: \Closure(DashboardStats, StatsRange): mixed} */
    private static function definition(string $name): array
    {
        return self::definitions()[$name] ?? throw new \InvalidArgumentException("Unknown dashboard number [{$name}].");
    }

    private static function key(string $name, ?string $range): string
    {
        return self::PREFIX.$name.($range !== null ? '.'.$range : '');
    }
}
