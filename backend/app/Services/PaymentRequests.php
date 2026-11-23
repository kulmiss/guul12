<?php

namespace App\Services;

use App\Enums\PaymentRequestStatus;
use App\Enums\PremiumPlan;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\PaymentRequest;
use App\Models\PremiumGrant;
use App\Models\User;
use App\Services\PaymentRequests\PaymentRequestProblem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The payment-request queue (DECISIONS #32). A student paid outside the app and messaged staff:
 * staff note it (waiting), someone checks the money arrived, then Approve (gives Premium through
 * PremiumService) or Reject (with a reason). Every step is logged.
 */
class PaymentRequests
{
    public function __construct(private readonly PremiumService $premium) {}

    public function create(User $student, PremiumPlan $plan, string $paymentRef, ?int $amountBirr, ?string $note, User $staff): PaymentRequest
    {
        self::mustManagePremium($staff);
        if ($student->isStaff()) {
            throw new PaymentRequestProblem('not_a_student', 'Premium can only be given to students.');
        }
        if (! in_array($plan, [PremiumPlan::Term, PremiumPlan::Year], true)) {
            throw new PaymentRequestProblem('paid_plan_only', 'Choose Term or Year.');
        }
        $ref = trim($paymentRef);
        if ($ref === '') {
            throw new PaymentRequestProblem('reference_required', 'Type the payment reference from the receipt.');
        }
        if ($this->referenceUsed($ref)) {
            throw new PaymentRequestProblem('duplicate_reference', 'This payment reference was already used. Check that it is not the same payment twice.');
        }
        $note = $note !== null && trim($note) !== '' ? trim($note) : null;

        $request = PaymentRequest::query()->create([
            'user_id' => $student->id,
            'plan' => $plan,
            'payment_ref' => $ref,
            'amount_birr' => $amountBirr,
            'note' => $note,
            'status' => PaymentRequestStatus::Waiting,
            'created_by' => $staff->id,
        ]);
        AuditLog::write($staff, 'payment_request.created', $request, [
            'student_id' => $student->student_id, 'plan' => $plan->value, 'payment_ref' => $ref, 'amount_birr' => $amountBirr,
        ]);

        return $request;
    }

    /** Gives Premium for a waiting request. A request can be approved only once, even with two quick clicks. */
    public function approve(PaymentRequest $request, User $staff): PremiumGrant
    {
        self::mustManagePremium($staff);

        return DB::transaction(function () use ($request, $staff): PremiumGrant {
            $this->claim($request, PaymentRequestStatus::Approved, $staff, null);
            $student = User::query()->findOrFail($request->user_id);
            $grant = $this->premium->grant($student, $request->plan, $staff, $request->payment_ref, "Payment request #{$request->id}".($request->note !== null ? ": {$request->note}" : ''));
            $request->forceFill(['premium_grant_id' => $grant->id])->save();
            AuditLog::write($staff, 'payment_request.approved', $request, ['old' => 'waiting', 'new' => 'approved', 'grant_id' => $grant->id]);

            return $grant;
        });
    }

    public function reject(PaymentRequest $request, User $staff, string $reason): void
    {
        self::mustManagePremium($staff);
        $reason = trim($reason);
        if ($reason === '') {
            throw new PaymentRequestProblem('reason_required', 'Say why it is rejected, so others understand.');
        }

        DB::transaction(function () use ($request, $staff, $reason): void {
            $this->claim($request, PaymentRequestStatus::Rejected, $staff, $reason);
            AuditLog::write($staff, 'payment_request.rejected', $request, ['old' => 'waiting', 'new' => 'rejected', 'reason' => $reason]);
        });
    }

    /**
     * True when a waiting/approved request or a Premium grant already has this reference (any letter case).
     * $ignoreRequestId leaves out the request being edited.
     */
    public function referenceUsed(string $ref, ?int $ignoreRequestId = null): bool
    {
        $lower = mb_strtolower(trim($ref));

        return PaymentRequest::query()
            ->where('status', '!=', PaymentRequestStatus::Rejected->value)
            ->whereRaw('lower(payment_ref) = ?', [$lower])
            ->when($ignoreRequestId !== null, fn ($query) => $query->whereKeyNot($ignoreRequestId))
            ->exists()
            || PremiumGrant::query()->whereRaw('lower(payment_ref) = ?', [$lower])->exists();
    }

    /** Moves a waiting request to its decision in one database step, so only the first decision wins. */
    private function claim(PaymentRequest $request, PaymentRequestStatus $to, User $staff, ?string $reason): void
    {
        $now = Carbon::now();
        $changed = PaymentRequest::query()
            ->whereKey($request->id)
            ->where('status', PaymentRequestStatus::Waiting->value)
            ->update(['status' => $to->value, 'decided_by' => $staff->id, 'decided_at' => $now, 'reject_reason' => $reason]);

        if ($changed !== 1) {
            throw new PaymentRequestProblem('already_decided', 'Someone already decided this request. Reload the page.');
        }
        $request->refresh();
    }

    private static function mustManagePremium(User $staff): void
    {
        if (! $staff->hasRole(UserRole::SuperAdmin, UserRole::PaymentsHelper)) {
            throw new PaymentRequestProblem('not_allowed', 'Only payment helpers can do this.');
        }
    }
}
