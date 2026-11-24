<?php

namespace App\Services\Stats;

use App\Enums\NicknameReportStatus;
use App\Enums\PaymentRequestStatus;
use App\Enums\PremiumPlan;
use App\Enums\QuestionStatus;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Referral;
use App\Services\Leaderboard;
use App\Services\Settings;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Every number on the Nova dashboard, computed from the database. Results are plain arrays so they
 * can be cached (StatsCache); pages read the cache, the scheduler refreshes it.
 *
 * Values with a range return ['value' => int|float, 'previous' => int|float|null] (previous = the
 * period just before, for the ↑/↓ %). "Students" never include staff.
 */
class DashboardStats
{
    /** Minimum answers before a question or chapter can be called hard or weak. */
    public const MIN_ANSWERS_QUESTION = 20;

    public const MIN_ANSWERS_CHAPTER = 50;

    public function __construct(private readonly Settings $settings) {}

    // ---- values with a range ------------------------------------------------------------------

    /** @return array{value: int, previous: int|null} accounts created */
    public function newStudents(StatsRange $range): array
    {
        return $this->countIn(fn () => $this->students(), 'created_at', $range);
    }

    /** @return array{value: int, previous: int|null} students who opened the app (reached the server) */
    public function activeStudents(StatsRange $range): array
    {
        return $this->countIn(fn () => $this->students(), 'last_active_at', $range, previousKnown: false);
    }

    /** @return array{value: int, previous: int|null} paid Premium periods given (term or year, not undone) */
    public function premiumSold(StatsRange $range): array
    {
        return $this->countIn(fn () => DB::table('premium_grants')
            ->whereIn('plan', [PremiumPlan::Term->value, PremiumPlan::Year->value])
            ->whereNull('revoked_at'), 'created_at', $range);
    }

    /** @return array{value: int, previous: int|null} battles where a second student joined */
    public function battlesPlayed(StatsRange $range): array
    {
        return $this->countIn(fn () => DB::table('battles')->whereNotNull('opponent_id'), 'created_at', $range);
    }

    /** @return array{value: int, previous: int|null} friends who counted (the inviter was rewarded) */
    public function referralsRewarded(StatsRange $range): array
    {
        return $this->countIn(fn () => DB::table('referrals')->where('status', Referral::VALID), 'valid_at', $range);
    }

    /** @return array{value: int, previous: int|null} answers that reached the server */
    public function answersGiven(StatsRange $range): array
    {
        return $this->countIn(fn () => DB::table('attempt_answers'), 'created_at', $range);
    }

    // ---- values "now" -------------------------------------------------------------------------

    public function premiumActiveNow(?Carbon $now = null): int
    {
        $now ??= Carbon::now();

        return (int) DB::table('premium_grants')
            ->whereNull('revoked_at')->where('starts_at', '<=', $now)->where('ends_at', '>', $now)
            ->distinct()->count('user_id');
    }

    /** Students whose Premium ends within $days and who have nothing after it. */
    public function premiumExpiringSoon(int $days = 7, ?Carbon $now = null): int
    {
        $now ??= Carbon::now();
        $until = $now->copy()->addDays($days);

        $lastEnd = DB::table('premium_grants')->whereNull('revoked_at')
            ->groupBy('user_id')->selectRaw('user_id, max(ends_at) as last_end');

        return (int) DB::query()->fromSub($lastEnd, 'g')
            ->where('last_end', '>', $now)->where('last_end', '<=', $until)
            ->count();
    }

    public function paymentsWaiting(): int
    {
        return DB::table('payment_requests')->where('status', PaymentRequestStatus::Waiting->value)->count();
    }

    public function openQuestionReports(): int
    {
        return DB::table('question_reports')->where('status', ReportStatus::Open->value)->count();
    }

    public function openNicknameReports(): int
    {
        return DB::table('nickname_reports')->where('status', NicknameReportStatus::Open->value)->count();
    }

    public function questionsWithStatus(QuestionStatus $status): int
    {
        return DB::table('questions')->where('status', $status->value)->count();
    }

    /** Beta questions with at least half of the answers they need before they can be published. */
    public function betaNearPublishing(): int
    {
        $needed = max(1, $this->settings->int('beta_min_answers'));

        return DB::table('questions')
            ->where('status', QuestionStatus::Beta->value)
            ->where('times_answered', '>=', (int) ceil($needed / 2))
            ->where('reports_count', 0)
            ->count();
    }

    // ---- trends (one number per local day or week) ---------------------------------------------

    /** @return array<string, int> local date => accounts created */
    public function newStudentsPerDay(StatsRange $range): array
    {
        return $this->perDay($this->students(), 'created_at', $range);
    }

    /** @return array<string, int> local date => students who answered at least one question */
    public function answeringStudentsPerDay(StatsRange $range): array
    {
        return $this->perDay(DB::table('attempts'), 'finished_at', $range, 'count(distinct user_id)');
    }

    /** @return array<string, int> local date => answers */
    public function answersPerDay(StatsRange $range): array
    {
        return $this->perDay(DB::table('attempt_answers'), 'created_at', $range);
    }

    /** @return array<string, int> local date => minutes spent answering (from answer times; QUESTIONS #48) */
    public function studyMinutesPerDay(StatsRange $range): array
    {
        return array_map(
            fn (int $ms): int => intdiv($ms, 60_000),
            $this->perDay(DB::table('attempt_answers'), 'created_at', $range, 'coalesce(sum(time_ms), 0)'),
        );
    }

    /** @return array<string, int> week start (local Monday) => paid Premium periods given */
    public function premiumSoldPerWeek(StatsRange $range): array
    {
        $days = $this->perDay(DB::table('premium_grants')
            ->whereIn('plan', [PremiumPlan::Term->value, PremiumPlan::Year->value])
            ->whereNull('revoked_at'), 'created_at', $range);
        $weeks = [];
        foreach ($days as $date => $count) {
            $monday = Carbon::createFromFormat('Y-m-d', $date, StatsRange::TIMEZONE)?->startOfWeek(Carbon::MONDAY)->toDateString() ?? $date;
            $weeks[$monday] = ($weeks[$monday] ?? 0) + $count;
        }

        return $weeks;
    }

    // ---- partitions ------------------------------------------------------------------------------

    /**
     * Students by one profile column. Missing values are counted as "not given".
     *
     * @param  'grade'|'stream'|'region'|'gender'  $column
     * @return array<string, int>
     */
    public function studentsBy(string $column): array
    {
        $rows = $this->students()->groupBy($column)->selectRaw("{$column} as label, count(*) as n")->get();
        $out = [];
        foreach ($rows as $row) {
            $label = $row->label === null || $row->label === '' ? 'Not given' : self::labelFor($column, (string) $row->label);
            $out[$label] = ($out[$label] ?? 0) + (int) $row->n;
        }
        arsort($out);

        return $out;
    }

    /** @return array{Premium: int, Free: int} */
    public function premiumVsFree(): array
    {
        $premium = $this->premiumActiveNow();

        return ['Premium' => $premium, 'Free' => max(0, $this->students()->count() - $premium)];
    }

    /** @return array<string, int> question status label => count */
    public function questionsByStatus(): array
    {
        $out = [];
        foreach (DB::table('questions')->groupBy('status')->selectRaw('status, count(*) as n')->get() as $row) {
            $out[ucfirst((string) $row->status)] = (int) $row->n;
        }
        ksort($out);

        return $out;
    }

    // ---- tables ----------------------------------------------------------------------------------

    /** @return list<array{id: int, text: string, percent: float, answered: int}> lowest share right first */
    public function hardestQuestions(int $limit = 10): array
    {
        return $this->questionsByRate('asc', $limit);
    }

    /** @return list<array{id: int, text: string, percent: float, answered: int}> highest share right first */
    public function easiestQuestions(int $limit = 10): array
    {
        return $this->questionsByRate('desc', $limit);
    }

    /** @return list<array{id: int, title: string, percent: float, answered: int}> */
    public function weakestChapters(int $limit = 10): array
    {
        return array_values(DB::table('questions')
            ->join('chapters', 'chapters.id', '=', 'questions.chapter_id')
            ->groupBy('chapters.id', 'chapters.title')
            ->havingRaw('sum(questions.times_answered) >= ?', [self::MIN_ANSWERS_CHAPTER])
            ->selectRaw('chapters.id as id, chapters.title as title, sum(questions.times_correct) as right_answers, sum(questions.times_answered) as answered')
            ->orderByRaw('sum(questions.times_correct) * 1.0 / sum(questions.times_answered) asc')
            ->orderBy('chapters.id')
            ->limit($limit)->get()
            ->map(fn (object $r): array => [
                'id' => (int) $r->id,
                'title' => (string) $r->title,
                'percent' => round(100 * (int) $r->right_answers / max(1, (int) $r->answered), 1),
                'answered' => (int) $r->answered,
            ])->all());
    }

    /** @return list<array{id: int, text: string, reports: int}> */
    public function mostReportedQuestions(int $limit = 10): array
    {
        return array_values(DB::table('questions')->where('reports_count', '>', 0)
            ->orderByDesc('reports_count')->orderBy('id')->limit($limit)
            ->get(['id', 'stem', 'reports_count'])
            ->map(fn (object $r): array => ['id' => (int) $r->id, 'text' => self::short((string) $r->stem), 'reports' => (int) $r->reports_count])
            ->all());
    }

    /** @return list<array{rank: int, nickname: string, xp: int}> this week's leaderboard, nicknames only */
    public function topStudentsThisWeek(int $limit = 10): array
    {
        $standings = array_slice(app(Leaderboard::class)->standings(Carbon::now()), 0, $limit);
        $names = DB::table('users')->whereIn('id', array_column($standings, 'user_id'))->pluck('nickname', 'id');
        $out = [];
        foreach ($standings as $row) {
            $out[] = [
                'rank' => Leaderboard::rankFor($standings, $row['user_id'], $row['xp']) ?? 0,
                'nickname' => (string) ($names[$row['user_id']] ?? '—'),
                'xp' => $row['xp'],
            ];
        }

        return $out;
    }

    /** @return list<array{id: int, student_id: string, plan: string, payment_ref: string, status: string, at: string}> */
    public function latestPayments(int $limit = 5): array
    {
        return array_values(DB::table('payment_requests')
            ->join('users', 'users.id', '=', 'payment_requests.user_id')
            ->orderByDesc('payment_requests.created_at')->orderByDesc('payment_requests.id')->limit($limit)
            ->get(['payment_requests.id', 'users.student_id', 'payment_requests.plan', 'payment_requests.payment_ref', 'payment_requests.status', 'payment_requests.created_at'])
            ->map(fn (object $r): array => [
                'id' => (int) $r->id, 'student_id' => (string) $r->student_id, 'plan' => (string) $r->plan,
                'payment_ref' => (string) $r->payment_ref, 'status' => (string) $r->status, 'at' => (string) $r->created_at,
            ])->all());
    }

    /** @return list<array{type: string, id: int, text: string, at: string}> newest question and nickname reports */
    public function latestReports(int $limit = 5): array
    {
        $questions = DB::table('question_reports')->join('questions', 'questions.id', '=', 'question_reports.question_id')
            ->orderByDesc('question_reports.created_at')->limit($limit)
            ->get(['question_reports.id', 'questions.stem', 'question_reports.created_at'])
            ->map(fn (object $r): array => ['type' => 'question', 'id' => (int) $r->id, 'text' => self::short((string) $r->stem), 'at' => (string) $r->created_at]);
        $nicknames = DB::table('nickname_reports')->orderByDesc('created_at')->limit($limit)
            ->get(['id', 'nickname', 'created_at'])
            ->map(fn (object $r): array => ['type' => 'nickname', 'id' => (int) $r->id, 'text' => (string) $r->nickname, 'at' => (string) $r->created_at]);

        return array_values($questions->concat($nicknames)->sortByDesc('at')->take($limit)->all());
    }

    // ---- helpers ---------------------------------------------------------------------------------

    private function students(): Builder
    {
        return DB::table('users')->where('role', UserRole::Student->value);
    }

    /**
     * @param  \Closure(): Builder  $query  a fresh query each call
     * @return array{value: int, previous: int|null}
     */
    private function countIn(\Closure $query, string $column, StatsRange $range, bool $previousKnown = true): array
    {
        $current = $query();
        if ($range->start !== null) {
            $current->where($column, '>=', $range->start);
        }
        $value = $current->where($column, '<=', $range->end)->count();

        $previous = null;
        // "Last active" keeps only the newest visit, so an earlier period cannot be counted.
        if ($previousKnown && $range->previousStart !== null && $range->previousEnd !== null) {
            $previous = $query()->where($column, '>=', $range->previousStart)->where($column, '<', $range->previousEnd)->count();
        }

        return ['value' => $value, 'previous' => $previous];
    }

    /**
     * Counts per Addis Ababa day for the range (days with nothing are 0, oldest first).
     *
     * @return array<string, int>
     */
    private function perDay(Builder $query, string $column, StatsRange $range, string $aggregate = 'count(*)'): array
    {
        $days = $range->days();
        $lastDay = $range->end->copy()->setTimezone(StatsRange::TIMEZONE)->startOfDay();
        $firstDay = $lastDay->copy()->subDays($days - 1);
        $out = [];
        for ($d = $firstDay->copy(); $d->lessThanOrEqualTo($lastDay); $d->addDay()) {
            $out[$d->toDateString()] = 0;
        }

        $localDay = self::localDateSql(DB::getDriverName(), $column);
        $rows = $query->where($column, '>=', $firstDay->copy()->utc())->where($column, '<=', $range->end)
            ->groupByRaw($localDay)->selectRaw("{$localDay} as day, {$aggregate} as n")->get();
        foreach ($rows as $row) {
            $day = substr((string) $row->day, 0, 10);
            if (array_key_exists($day, $out)) {
                $out[$day] = (int) $row->n;
            }
        }

        return $out;
    }

    /** SQL for "the Addis Ababa date of this UTC time" (UTC+3 all year, no summer time). */
    private static function localDateSql(string $driver, string $column): string
    {
        return match ($driver) {
            'sqlite' => "date({$column}, '+3 hours')",
            default => "date(date_add({$column}, interval 3 hour))",
        };
    }

    /** @return list<array{id: int, text: string, percent: float, answered: int}> */
    private function questionsByRate(string $direction, int $limit): array
    {
        return array_values(DB::table('questions')->where('times_answered', '>=', self::MIN_ANSWERS_QUESTION)
            ->orderByRaw("times_correct * 1.0 / times_answered {$direction}")->orderBy('id')->limit($limit)
            ->get(['id', 'stem', 'times_correct', 'times_answered'])
            ->map(fn (object $r): array => [
                'id' => (int) $r->id,
                'text' => self::short((string) $r->stem),
                'percent' => round(100 * (int) $r->times_correct / max(1, (int) $r->times_answered), 1),
                'answered' => (int) $r->times_answered,
            ])->all());
    }

    private static function labelFor(string $column, string $value): string
    {
        return match ($column) {
            'grade' => "Grade {$value}",
            default => ucfirst(str_replace('_', ' ', $value)),
        };
    }

    private static function short(string $text): string
    {
        $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));

        return mb_strlen($plain) > 80 ? mb_substr($plain, 0, 79).'…' : $plain;
    }
}
