<?php

namespace App\Nova\Filters;

use App\Enums\Access;
use App\Enums\NicknameReportReason;
use App\Enums\NicknameReportStatus;
use App\Enums\PaymentRequestStatus;
use App\Enums\PremiumPlan;
use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\PremiumGrant;
use App\Models\Referral;
use App\Models\Subject;
use App\Models\User;
use App\Support\LocalTime;
use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The filters of each Nova list, in one place. Keys are stable: they are kept in the address and in
 * each staff member's remembered filters (resources/nova/guul-nova.js).
 */
final class FilterSets
{
    private const GRADES = [9 => 'Grade 9', 10 => 'Grade 10', 11 => 'Grade 11', 12 => 'Grade 12'];

    private const RECENT = ['1' => 'Today', '7' => 'Last 7 days', '30' => 'Last 30 days'];

    /** @return list<Choice> */
    public static function students(): array
    {
        return [
            self::column('Grade', 'student-grade', self::GRADES, 'grade'),
            self::column('Stream', 'student-stream', self::labels(Subject::STREAMS), 'stream'),
            new Choice('Region', 'student-region', fn (): array => self::regions(), fn (Builder $q, string $v) => $q->where('region', $v)),
            new Choice('Plan', 'student-plan', ['premium' => 'Premium now', 'free' => 'Free'], function (Builder $q, string $v): void {
                $active = fn (Builder $g) => $g->where('starts_at', '<=', Carbon::now());
                $v === 'premium' ? $q->whereHas('currentPremiumGrants', $active) : $q->whereDoesntHave('currentPremiumGrants', $active);
            }),
            self::recent('Joined', 'student-joined', 'created_at'),
            new Choice('Last active', 'student-active', ['1' => 'Today', '7' => 'Last 7 days', 'inactive14' => 'Not for 14+ days'], function (Builder $q, string $v): void {
                if ($v === 'inactive14') {
                    self::inactiveFor14Days($q);

                    return;
                }
                $q->where('last_active_at', '>=', LocalTime::daysBack((int) $v));
            }),
            new Choice('Leaderboard', 'student-leaderboard', ['blocked' => 'Blocked', 'shown' => 'Shown'],
                fn (Builder $q, string $v) => $v === 'blocked' ? $q->whereNotNull('leaderboard_blocked_at') : $q->whereNull('leaderboard_blocked_at')),
        ];
    }

    /** Students not seen for 14 days (or never, when they joined more than 14 days ago). */
    public static function inactiveFor14Days(Builder $query): Builder
    {
        $since = Carbon::now()->subDays(14);

        return $query->where(fn (Builder $q) => $q->where('last_active_at', '<', $since)
            ->orWhere(fn (Builder $never) => $never->whereNull('last_active_at')->where('created_at', '<', $since)));
    }

    /** @return list<Choice> */
    public static function paymentRequests(): array
    {
        return [
            self::column('Status', 'payment-status', self::enumLabels(PaymentRequestStatus::cases()), 'status'),
            self::column('Plan', 'payment-plan', self::enumLabels([PremiumPlan::Term, PremiumPlan::Year]), 'plan'),
            self::recent('Sent', 'payment-sent', 'created_at'),
        ];
    }

    /** @return list<Choice> */
    public static function premiumGrants(): array
    {
        return [
            self::column('Plan', 'grant-plan', self::enumLabels(PremiumPlan::cases()), 'plan'),
            new Choice('State', 'grant-state', ['active' => 'Active', 'next' => 'Starts later', 'expired' => 'Expired', 'undone' => 'Undone'], function (Builder $q, string $v): void {
                $now = Carbon::now();
                match ($v) {
                    'undone' => $q->whereNotNull('revoked_at'),
                    'active' => $q->whereNull('revoked_at')->where('starts_at', '<=', $now)->where('ends_at', '>', $now),
                    'next' => $q->whereNull('revoked_at')->where('starts_at', '>', $now),
                    default => $q->whereNull('revoked_at')->where('ends_at', '<=', $now),
                };
            }),
            self::recent('Given', 'grant-given', 'created_at'),
            new Choice('Given by', 'grant-given-by', fn (): array => self::staffWho(PremiumGrant::query()->select('granted_by')->whereNotNull('granted_by')),
                fn (Builder $q, string $v) => $q->where('granted_by', (int) $v)),
        ];
    }

    /** @return list<Choice> */
    public static function questions(): array
    {
        return [
            self::column('Status', 'question-status', self::enumLabels(QuestionStatus::cases()), 'status'),
            self::column('Type', 'question-type', self::enumLabels(QuestionType::cases()), 'type'),
            self::column('Difficulty', 'question-difficulty', ['1' => '1 – very easy', '2' => '2', '3' => '3', '4' => '4', '5' => '5 – very hard'], 'difficulty'),
            new Choice('Subject', 'question-subject', fn (): array => self::subjects(),
                fn (Builder $q, string $v) => $q->whereHas('chapter.unit', fn (Builder $u) => $u->where('subject_id', (int) $v))),
            new Choice('Grade', 'question-grade', self::GRADES,
                fn (Builder $q, string $v) => $q->whereHas('chapter.unit', fn (Builder $u) => $u->where('grade', (int) $v))),
            new Choice('Reports', 'question-reports', ['open' => 'Has open reports', 'none' => 'No open reports'], function (Builder $q, string $v): void {
                $open = fn (Builder $r) => $r->where('status', ReportStatus::Open->value);
                $v === 'open' ? $q->whereHas('reports', $open) : $q->whereDoesntHave('reports', $open);
            }),
        ];
    }

    /** @return list<Choice> */
    public static function chapters(): array
    {
        return [
            new Choice('Subject', 'chapter-subject', fn (): array => self::subjects(),
                fn (Builder $q, string $v) => $q->whereHas('unit', fn (Builder $u) => $u->where('subject_id', (int) $v))),
            new Choice('Grade', 'chapter-grade', self::GRADES,
                fn (Builder $q, string $v) => $q->whereHas('unit', fn (Builder $u) => $u->where('grade', (int) $v))),
            new Choice('Video', 'chapter-video', ['none' => 'No video', Access::Free->value => 'Free video', Access::Premium->value => 'Premium video'],
                fn (Builder $q, string $v) => $v === 'none' ? $q->whereNull('video_youtube_id') : $q->whereNotNull('video_youtube_id')->where('video_access', $v)),
            self::column('Shown in the app', 'chapter-active', ['1' => 'Yes', '0' => 'No'], 'is_active'),
        ];
    }

    /** @return list<Choice> */
    public static function questionReports(): array
    {
        return [
            self::column('Status', 'report-status', self::enumLabels(ReportStatus::cases()), 'status'),
            self::column('Reason', 'report-reason', self::enumLabels(ReportReason::cases()), 'reason'),
        ];
    }

    /** @return list<Choice> */
    public static function nicknameReports(): array
    {
        return [
            self::column('Status', 'nickname-report-status', self::enumLabels(NicknameReportStatus::cases()), 'status'),
            self::column('Reason', 'nickname-report-reason', self::enumLabels(NicknameReportReason::cases()), 'reason'),
        ];
    }

    /** @return list<Choice> */
    public static function auditLogs(): array
    {
        return [
            new Choice('Action', 'log-action', fn (): array => self::labels(AuditLog::query()->distinct()->orderBy('action')->limit(200)->pluck('action')->all(), raw: true),
                fn (Builder $q, string $v) => $q->where('action', $v)),
            new Choice('Staff member', 'log-staff', fn (): array => self::staffWho(AuditLog::query()->select('actor_id')->whereNotNull('actor_id')),
                fn (Builder $q, string $v) => $q->where('actor_id', (int) $v)),
            self::recent('When', 'log-when', 'created_at'),
        ];
    }

    /** @return list<Choice> */
    public static function battles(): array
    {
        return [
            new Choice('Status', 'battle-status', ['waiting' => 'Waiting', 'matched' => 'Matched', 'expired' => 'Expired'], function (Builder $q, string $v): void {
                match ($v) {
                    'matched' => $q->whereNotNull('opponent_id'),
                    'waiting' => $q->whereNull('opponent_id')->where('expires_at', '>', Carbon::now()),
                    default => $q->whereNull('opponent_id')->where('expires_at', '<=', Carbon::now()),
                };
            }),
            self::column('Type', 'battle-type', ['0' => 'With a friend', '1' => 'Random opponent'], 'is_random'),
        ];
    }

    /** @return list<Choice> */
    public static function referrals(): array
    {
        return [
            self::column('Status', 'referral-status', [Referral::PENDING => 'Waiting', Referral::VALID => 'Counted', Referral::EXPIRED => 'Expired'], 'status'),
            self::recent('Invited', 'referral-invited', 'created_at'),
        ];
    }

    /** @return list<Choice> */
    public static function mocks(): array
    {
        return [
            new Choice('Subject', 'mock-subject', fn (): array => self::subjects(), fn (Builder $q, string $v) => $q->where('subject_id', (int) $v)),
            self::column('Grade', 'mock-grade', self::GRADES, 'grade'),
            self::column('Access', 'mock-access', self::enumLabels(Access::cases()), 'access'),
        ];
    }

    // ---- helpers --------------------------------------------------------------------------------

    /** @param  array<int|string, string>  $choices */
    private static function column(string $name, string $key, array $choices, string $column): Choice
    {
        return new Choice($name, $key, $choices, fn (Builder $q, string $v) => $q->where($column, $v));
    }

    private static function recent(string $name, string $key, string $column): Choice
    {
        return new Choice($name, $key, self::RECENT, fn (Builder $q, string $v) => $q->where($column, '>=', LocalTime::daysBack((int) $v)));
    }

    /**
     * @param  array<int, BackedEnum>  $cases
     * @return array<string, string>
     */
    private static function enumLabels(array $cases): array
    {
        $out = [];
        foreach ($cases as $case) {
            $out[(string) $case->value] = ucfirst(str_replace('_', ' ', (string) $case->value));
        }

        return $out;
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<string, string> value => easy label ("addis_ababa" => "Addis ababa"; raw keeps the value)
     */
    private static function labels(array $values, bool $raw = false): array
    {
        $out = [];
        foreach ($values as $value) {
            if (is_string($value) && $value !== '') {
                $out[$value] = $raw ? $value : ucfirst(str_replace('_', ' ', $value));
            }
        }

        return $out;
    }

    /** @return array<string, string> regions students typed, in easy words */
    private static function regions(): array
    {
        return self::labels(User::query()->where('role', UserRole::Student->value)->whereNotNull('region')
            ->distinct()->orderBy('region')->limit(100)->pluck('region')->all());
    }

    /** @return array<int, string> subject id => name */
    private static function subjects(): array
    {
        $out = [];
        foreach (Subject::query()->orderBy('sort')->orderBy('name')->get(['id', 'name']) as $subject) {
            $out[$subject->id] = $subject->name;
        }

        return $out;
    }

    /**
     * Staff who appear in a column (who gave Premium, who did something).
     *
     * @template TModel of Model
     *
     * @param  EloquentBuilder<TModel>  $ids  a query selecting one user id column
     * @return array<int, string> user id => name
     */
    private static function staffWho(EloquentBuilder $ids): array
    {
        $out = [];
        foreach (User::query()->where('role', '!=', UserRole::Student->value)->whereIn('id', $ids)->orderBy('name')->get(['id', 'name']) as $staff) {
            $out[$staff->id] = $staff->name ?? 'Staff';
        }

        return $out;
    }
}
