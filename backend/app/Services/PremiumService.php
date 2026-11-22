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
            ->get(['starts_at', 'ends_at']);

        $until = null;

        foreach ($grants as $grant) {
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
            PremiumPlan::Referral => 7,
        };
    }

    /**
     * Gives Premium. If the student already has Premium, the new period starts when the current one ends.
     *
     * @throws AuthorizationException
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
}
