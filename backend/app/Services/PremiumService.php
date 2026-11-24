<?php

namespace App\Services;

use App\Enums\PremiumPlan;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\PremiumGrant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Premium is granted by a helper after a student pays outside the app.
 * Every grant and undo is logged (who, when, payment reference).
 */
class PremiumService
{
    public function __construct(private readonly Settings $settings) {}

    public function isPremium(User $user, ?Carbon $at = null): bool
    {
        $at ??= Carbon::now();

        return PremiumGrant::query()->where('user_id', $user->id)->activeAt($at)->exists();
    }

    /**
     * End of the unbroken Premium period that covers $at (grants that follow each other are joined).
     * null when the user is not Premium at $at.
     */
    public function activeUntil(User $user, ?Carbon $at = null): ?Carbon
    {
        $at ??= Carbon::now();

        $grants = PremiumGrant::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where('ends_at', '>', $at)
            ->orderBy('starts_at')
            ->get(['starts_at', 'ends_at', 'revoked_at']);

        return self::untilFromGrants($grants, $at);
    }

    /**
     * The same as activeUntil() for grants that are already loaded (lists in Nova load them for a whole
     * page at once). Revoked grants are ignored.
     *
     * @param  iterable<PremiumGrant>  $grants
     */
    public static function untilFromGrants(iterable $grants, Carbon $at): ?Carbon
    {
        $sorted = collect($grants)
            ->filter(fn (PremiumGrant $g): bool => $g->revoked_at === null && $g->ends_at->greaterThan($at))
            ->sortBy(fn (PremiumGrant $g): int => $g->starts_at->getTimestamp());

        $until = null;

        foreach ($sorted as $grant) {
            $coversStart = $until === null ? $grant->starts_at->lessThanOrEqualTo($at) : $grant->starts_at->lessThanOrEqualTo($until);

            if (! $coversStart) {
                continue;
            }

            if ($until === null || $grant->ends_at->greaterThan($until)) {
                $until = $grant->ends_at->copy();
            }
        }

        return $until;
    }

    public function daysFor(PremiumPlan $plan): int
    {
        return match ($plan) {
            PremiumPlan::Term => $this->settings->int('premium_term_days'),
            PremiumPlan::Year => $this->settings->int('premium_year_days'),
            PremiumPlan::Referral => $this->settings->int('referral_premium_days'),
            PremiumPlan::Extra => throw new \InvalidArgumentException('Extra days are given with extend().'),
        };
    }

    /**
     * Gives Premium. If the student already has Premium, the new period starts when the current one ends.
     *
     * @throws AuthorizationException
     * @throws \InvalidArgumentException for staff, Extra days (use extend()) or a missing payment reference
     */
    public function grant(User $student, PremiumPlan $plan, ?User $actor, ?string $paymentRef = null, ?string $note = null): PremiumGrant
    {
        if ($actor !== null && ! $actor->hasRole(UserRole::SuperAdmin, UserRole::PaymentsHelper)) {
            throw new AuthorizationException('Only payment helpers can grant Premium.');
        }

        if ($student->isStaff()) {
            throw new \InvalidArgumentException('Premium can only be given to students.');
        }

        $paymentRef = $paymentRef !== null ? trim($paymentRef) : null;

        if ($plan === PremiumPlan::Extra) {
            throw new \InvalidArgumentException('Extra days are given with extend().');
        }

        if ($plan !== PremiumPlan::Referral && ($paymentRef === null || $paymentRef === '')) {
            throw new \InvalidArgumentException('A payment reference is required for paid plans.');
        }

        return DB::transaction(function () use ($student, $plan, $actor, $paymentRef, $note): PremiumGrant {
            $now = Carbon::now();
            $start = $this->activeUntil($student, $now) ?? $now;

            $grant = PremiumGrant::query()->create([
                'user_id' => $student->id,
                'plan' => $plan,
                'starts_at' => $start,
                'ends_at' => $start->copy()->addDays($this->daysFor($plan)),
                'payment_ref' => $paymentRef === '' ? null : $paymentRef,
                'granted_by' => $actor?->id,
                'note' => $note,
            ]);

            AuditLog::write($actor, 'premium.granted', $grant, [
                'student_id' => $student->student_id,
                'plan' => $plan->value,
                'starts_at' => $grant->starts_at->toIso8601String(),
                'ends_at' => $grant->ends_at->toIso8601String(),
                'payment_ref' => $grant->payment_ref,
            ]);

            return $grant;
        });
    }

    /**
     * Undo a grant (wrong student, wrong plan, refund).
     *
     * @throws AuthorizationException
     */
    public function revoke(PremiumGrant $grant, User $actor, ?string $reason = null): void
    {
        if (! $actor->hasRole(UserRole::SuperAdmin, UserRole::PaymentsHelper)) {
            throw new AuthorizationException('Only payment helpers can undo Premium.');
        }

        if ($grant->revoked_at !== null) {
            return;
        }

        DB::transaction(function () use ($grant, $actor, $reason): void {
            $grant->forceFill([
                'revoked_at' => Carbon::now(),
                'revoked_by' => $actor->id,
            ])->save();

            AuditLog::write($actor, 'premium.revoked', $grant, [
                'student_id' => User::query()->whereKey($grant->user_id)->value('student_id'),
                'reason' => $reason,
            ]);
        });
    }

    /** Most days staff can add at once. */
    public const MAX_EXTRA_DAYS = 365;

    /**
     * Adds days by hand (compensation, a prize). Starts when the current Premium ends, or now.
     *
     * @throws AuthorizationException
     * @throws \InvalidArgumentException when the days are out of range or the user is staff
     */
    public function extend(User $student, int $days, User $actor, string $reason): PremiumGrant
    {
        self::mustManagePremium($actor);
        if ($student->isStaff()) {
            throw new \InvalidArgumentException('Premium can only be given to students.');
        }
        if ($days < 1 || $days > self::MAX_EXTRA_DAYS) {
            throw new \InvalidArgumentException('Give between 1 and '.self::MAX_EXTRA_DAYS.' days.');
        }

        return DB::transaction(function () use ($student, $days, $actor, $reason): PremiumGrant {
            $now = Carbon::now();
            $before = $this->activeUntil($student, $now);
            $start = $before ?? $now;
            $grant = PremiumGrant::query()->create([
                'user_id' => $student->id,
                'plan' => PremiumPlan::Extra,
                'starts_at' => $start,
                'ends_at' => $start->copy()->addDays($days),
                'granted_by' => $actor->id,
                'note' => trim($reason),
            ]);
            AuditLog::change($actor, 'premium.extended', $student,
                ['premium_until' => $before?->toIso8601String()],
                ['premium_until' => $grant->ends_at->toIso8601String()],
                ['days' => $days, 'reason' => trim($reason)]);

            return $grant;
        });
    }

    /**
     * Stops Premium now (a refund, a rule broken): the running grant ends today, later ones are undone.
     * Returns how many grants changed.
     *
     * @throws AuthorizationException
     */
    public function endNow(User $student, User $actor, string $reason): int
    {
        self::mustManagePremium($actor);

        return DB::transaction(function () use ($student, $actor, $reason): int {
            $now = Carbon::now();
            $before = $this->activeUntil($student, $now);
            $grants = PremiumGrant::query()->where('user_id', $student->id)->whereNull('revoked_at')
                ->where('ends_at', '>', $now)->lockForUpdate()->get();
            foreach ($grants as $grant) {
                $grant->starts_at->lessThanOrEqualTo($now)
                    ? $grant->forceFill(['ends_at' => $now])->save()
                    : $grant->forceFill(['revoked_at' => $now, 'revoked_by' => $actor->id])->save();
            }
            if ($grants->isNotEmpty()) {
                AuditLog::change($actor, 'premium.ended', $student,
                    ['premium_until' => $before?->toIso8601String()], ['premium_until' => null], ['reason' => trim($reason)]);
            }

            return $grants->count();
        });
    }

    /**
     * Undoes the newest grant that is not undone yet (it was given by mistake). Null when none is left.
     *
     * @throws AuthorizationException
     */
    public function undoLatest(User $student, User $actor, string $reason): ?PremiumGrant
    {
        self::mustManagePremium($actor);
        $grant = PremiumGrant::query()->where('user_id', $student->id)->whereNull('revoked_at')
            ->latest('created_at')->latest('id')->first();
        if ($grant === null) {
            return null;
        }
        $this->revoke($grant, $actor, $reason);

        return $grant;
    }

    /** @throws AuthorizationException */
    private static function mustManagePremium(User $actor): void
    {
        if (! $actor->hasRole(UserRole::SuperAdmin, UserRole::PaymentsHelper)) {
            throw new AuthorizationException('Only payment helpers can change Premium.');
        }
    }
}
