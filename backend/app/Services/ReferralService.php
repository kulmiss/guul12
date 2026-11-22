<?php

namespace App\Services;

use App\Enums\PremiumPlan;
use App\Models\PremiumGrant;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Invite a friend: the friend enters the inviter's code soon after first opening the app. When the
 * friend has really studied (enough real answers in time), the inviter gets Premium days, up to a
 * monthly limit. All numbers are settings (Nova).
 */
class ReferralService
{
    private const LETTERS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private readonly Settings $settings,
        private readonly PremiumService $premium,
    ) {}

    public function codeFor(User $user): string
    {
        $existing = User::query()->whereKey($user->id)->value('referral_code');
        if (is_string($existing)) {
            return $existing;
        }
        for ($try = 0; ; $try++) {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::LETTERS[random_int(0, strlen(self::LETTERS) - 1)];
            }
            try {
                // Only if still empty: two requests at once keep the first code.
                User::query()->whereKey($user->id)->whereNull('referral_code')->update(['referral_code' => $code]);

                return (string) User::query()->whereKey($user->id)->value('referral_code');
            } catch (UniqueConstraintViolationException $e) {
                if ($try >= 5) {
                    throw $e;
                }
            }
        }
    }

    /** True while the student may still enter a friend's code. */
    public function canEnterCode(User $user): bool
    {
        return ! $user->isStaff()
            && $user->created_at->copy()->addDays($this->settings->int('referral_enter_days'))->isFuture()
            && ! Referral::query()->where('invitee_id', $user->id)->exists();
    }

    /**
     * @return string|null null when linked, else an error code: not_found, own_code, too_late, already_invited
     */
    public function enterCode(User $invitee, string $code): ?string
    {
        $inviter = User::query()->where('referral_code', strtoupper(trim($code)))->first();
        if ($inviter === null || $inviter->isStaff()) {
            return 'not_found';
        }
        if ($inviter->id === $invitee->id) {
            return 'own_code';
        }
        if (Referral::query()->where('invitee_id', $invitee->id)->exists()) {
            return 'already_invited';
        }
        if (! $this->canEnterCode($invitee)) {
            return 'too_late';
        }
        try {
            Referral::query()->create(['inviter_id' => $inviter->id, 'invitee_id' => $invitee->id, 'status' => Referral::PENDING]);
        } catch (UniqueConstraintViolationException) {
            return 'already_invited';
        }

        return null;
    }

    /**
     * Called after each upload: makes the friend's referral valid once they answered enough real
     * questions in time, and rewards the inviter (within the monthly limit).
     */
    public function check(User $invitee): void
    {
        $referral = Referral::query()->where('invitee_id', $invitee->id)->where('status', Referral::PENDING)->first();
        if ($referral === null) {
            return;
        }
        $deadline = $referral->created_at->copy()->addDays($this->settings->int('referral_qualify_days'));
        if (Carbon::now()->greaterThan($deadline)) {
            $referral->forceFill(['status' => Referral::EXPIRED])->save();

            return;
        }

        $real = (int) DB::table('attempt_answers')
            ->join('attempts', 'attempts.id', '=', 'attempt_answers.attempt_id')
            ->where('attempts.user_id', $invitee->id)
            ->where('attempt_answers.too_fast', false)
            ->where('attempt_answers.created_at', '>=', $referral->created_at)
            ->count();
        if ($real < $this->settings->int('referral_min_answers')) {
            return;
        }

        DB::transaction(function () use ($referral): void {
            // Only one request turns it valid.
            $won = Referral::query()->whereKey($referral->id)->where('status', Referral::PENDING)
                ->update(['status' => Referral::VALID, 'valid_at' => Carbon::now()]);
            if ($won !== 1) {
                return;
            }
            $inviter = $referral->inviter;
            if (! $this->deservesReward($inviter)) {
                return;
            }
            $grant = $this->premium->grant($inviter, PremiumPlan::Referral, null, null, 'Friend invited: referral #'.$referral->id);
            Referral::query()->whereKey($referral->id)->update(['premium_grant_id' => $grant->id]);
        });
    }

    /**
     * @return array{code: string, pending: int, valid: int, rewarded_days: int, can_enter_code: bool, reward_days: int, min_answers: int, max_per_month: int}
     */
    public function summary(User $user): array
    {
        $mine = Referral::query()->where('inviter_id', $user->id);
        $grants = PremiumGrant::query()
            ->whereIn('id', (clone $mine)->whereNotNull('premium_grant_id')->select('premium_grant_id'))
            ->whereNull('revoked_at')
            ->get(['starts_at', 'ends_at']);

        return [
            'code' => $this->codeFor($user),
            'pending' => (clone $mine)->where('status', Referral::PENDING)->count(),
            'valid' => (clone $mine)->where('status', Referral::VALID)->count(),
            'rewarded_days' => (int) $grants->sum(fn (PremiumGrant $g): int => (int) round($g->starts_at->diffInDays($g->ends_at))),
            'can_enter_code' => $this->canEnterCode($user),
            'reward_days' => $this->settings->int('referral_premium_days'),
            'min_answers' => $this->settings->int('referral_min_answers'),
            'max_per_month' => $this->settings->int('referral_max_per_month'),
        ];
    }

    private function deservesReward(User $inviter): bool
    {
        if ($inviter->isStaff() || $this->settings->int('referral_premium_days') <= 0) {
            return false;
        }
        $thisMonth = Referral::query()
            ->where('inviter_id', $inviter->id)
            ->whereNotNull('premium_grant_id')
            ->where('valid_at', '>=', Carbon::now(DailyChallengeService::TIMEZONE)->startOfMonth()->utc())
            ->count();

        return $thisMonth < $this->settings->int('referral_max_per_month');
    }
}
