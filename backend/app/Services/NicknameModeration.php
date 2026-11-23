<?php

namespace App\Services;

use App\Enums\NicknameReportReason;
use App\Enums\NicknameReportStatus;
use App\Models\AuditLog;
use App\Models\NicknameReport;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Nickname reports and what staff do about them (DECISIONS #33).
 *
 * The phone never learns another student's ID: leaderboard and battle rows carry an encrypted
 * reference that only the server can read.
 */
class NicknameModeration
{
    /** Nickname reports one student may send per day. */
    public const DAILY_LIMIT = 20;

    private const REF_PREFIX = 'nick:';

    public function refFor(User $user): string
    {
        return Crypt::encryptString(self::REF_PREFIX.$user->id);
    }

    public function userFromRef(string $ref): ?User
    {
        try {
            $plain = Crypt::decryptString($ref);
        } catch (DecryptException) {
            return null;
        }

        if (! str_starts_with($plain, self::REF_PREFIX) || ! ctype_digit(substr($plain, strlen(self::REF_PREFIX)))) {
            return null;
        }

        return User::query()->find((int) substr($plain, strlen(self::REF_PREFIX)));
    }

    /**
     * Reports the target's current nickname. Returns the report and whether it is new
     * (a second report of the same nickname by the same student changes nothing).
     *
     * @return array{0: NicknameReport, 1: bool}
     */
    public function report(User $reporter, User $target, NicknameReportReason $reason, ?string $note): array
    {
        $report = NicknameReport::query()->firstOrCreate(
            ['reporter_id' => $reporter->id, 'reported_user_id' => $target->id, 'nickname' => (string) $target->nickname],
            ['reason' => $reason, 'note' => $note, 'status' => NicknameReportStatus::Open],
        );

        return [$report, $report->wasRecentlyCreated];
    }

    public function reportsToday(User $reporter): int
    {
        return NicknameReport::query()
            ->where('reporter_id', $reporter->id)
            ->where('created_at', '>=', Carbon::now()->startOfDay())
            ->count();
    }

    /**
     * Removes the nickname (the student must pick a new one) and closes its open reports.
     * Returns how many reports were closed.
     */
    public function reset(User $target, User $staff): int
    {
        return DB::transaction(function () use ($target, $staff): int {
            $old = $target->nickname;
            $closed = NicknameReport::query()
                ->where('reported_user_id', $target->id)
                ->where('status', NicknameReportStatus::Open->value)
                ->update(['status' => NicknameReportStatus::Reset->value, 'resolved_by' => $staff->id, 'resolved_at' => Carbon::now()]);

            $target->forceFill(['nickname' => null])->save();
            AuditLog::write($staff, 'nickname.reset', $target, ['old' => $old, 'new' => null, 'reports' => $closed]);
            self::refreshLeaderboard();

            return $closed;
        });
    }

    public function dismiss(NicknameReport $report, User $staff): void
    {
        $report->forceFill([
            'status' => NicknameReportStatus::Dismissed,
            'resolved_by' => $staff->id,
            'resolved_at' => Carbon::now(),
        ])->save();
        AuditLog::write($staff, 'nickname_report.dismissed', $report, ['nickname' => $report->nickname]);
    }

    public function block(User $target, User $staff): void
    {
        $target->forceFill(['leaderboard_blocked_at' => Carbon::now()])->save();
        AuditLog::write($staff, 'leaderboard.blocked', $target, ['old' => null, 'new' => 'blocked']);
        self::refreshLeaderboard();
    }

    public function unblock(User $target, User $staff): void
    {
        $target->forceFill(['leaderboard_blocked_at' => null])->save();
        AuditLog::write($staff, 'leaderboard.unblocked', $target, ['old' => 'blocked', 'new' => null]);
        self::refreshLeaderboard();
    }

    /** True when staff removed this nickname before (it cannot be chosen again, in any spelling). */
    public function wasRemoved(string $nickname): bool
    {
        $wanted = NicknameFilter::squash($nickname);

        return NicknameReport::query()
            ->where('status', NicknameReportStatus::Reset->value)
            ->distinct()
            ->pluck('nickname')
            ->contains(fn (string $removed): bool => NicknameFilter::squash($removed) === $wanted);
    }

    /** Makes the leaderboard cache start over, so a change shows at once. */
    public static function refreshLeaderboard(): void
    {
        Cache::forever(Leaderboard::VERSION_KEY, (int) Cache::get(Leaderboard::VERSION_KEY, 0) + 1);
    }
}
