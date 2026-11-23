<?php

namespace Tests\Feature;

use App\Enums\PaymentRequestStatus;
use App\Enums\PremiumPlan;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\PaymentRequest;
use App\Models\PremiumGrant;
use App\Models\User;
use App\Services\PaymentRequests;
use App\Services\PaymentRequests\PaymentRequestProblem;
use App\Services\PremiumService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The payment-request queue (DECISIONS #32): noted → checked → Premium given or rejected.
 */
class PaymentRequestTest extends TestCase
{
    use RefreshDatabase;

    private PaymentRequests $requests;

    private User $helper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requests = app(PaymentRequests::class);
        $this->helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
    }

    public function test_a_request_waits_and_is_logged(): void
    {
        $student = User::factory()->create();

        $request = $this->requests->create($student, PremiumPlan::Term, '  TX-100 ', 250, 'Paid by phone', $this->helper);

        $this->assertSame([PaymentRequestStatus::Waiting, 'TX-100', 250, $this->helper->id],
            [$request->status, $request->payment_ref, $request->amount_birr, $request->created_by]);
        $this->assertFalse(app(PremiumService::class)->isPremium($student));
        $this->assertSame(1, AuditLog::query()->where('action', 'payment_request.created')->count());
    }

    public function test_approving_gives_premium_once(): void
    {
        $student = User::factory()->create();
        $request = $this->requests->create($student, PremiumPlan::Year, 'TX-200', null, null, $this->helper);
        $admin = User::factory()->staff(UserRole::SuperAdmin)->create();

        $grant = $this->requests->approve($request, $admin);

        $request->refresh();
        $this->assertSame([PaymentRequestStatus::Approved, $admin->id, $grant->id], [$request->status, $request->decided_by, $request->premium_grant_id]);
        $this->assertNotNull($request->decided_at);
        $this->assertSame([PremiumPlan::Year, 'TX-200', $admin->id], [$grant->plan, $grant->payment_ref, $grant->granted_by]);
        $this->assertTrue(app(PremiumService::class)->isPremium($student));
        $log = AuditLog::query()->where('action', 'payment_request.approved')->sole();
        $data = (array) $log->data;
        ksort($data);
        $this->assertSame(['grant_id' => $grant->id, 'new' => 'approved', 'old' => 'waiting'], $data);

        // A second click (or a second staff member) changes nothing.
        try {
            $this->requests->approve($request, $this->helper);
            $this->fail('A decided request was approved again.');
        } catch (PaymentRequestProblem $e) {
            $this->assertSame('already_decided', $e->reason);
        }
        $this->assertSame(1, PremiumGrant::query()->count());
    }

    public function test_rejecting_needs_a_reason_and_gives_nothing(): void
    {
        $student = User::factory()->create();
        $request = $this->requests->create($student, PremiumPlan::Term, 'TX-300', 100, null, $this->helper);

        try {
            $this->requests->reject($request, $this->helper, '   ');
            $this->fail('Rejected without a reason.');
        } catch (PaymentRequestProblem $e) {
            $this->assertSame('reason_required', $e->reason);
        }

        $this->requests->reject($request, $this->helper, 'Amount was 100 Br, not 250 Br');

        $request->refresh();
        $this->assertSame([PaymentRequestStatus::Rejected, 'Amount was 100 Br, not 250 Br'], [$request->status, $request->reject_reason]);
        $this->assertFalse(app(PremiumService::class)->isPremium($student));
        $this->assertSame(1, AuditLog::query()->where('action', 'payment_request.rejected')->count());

        $this->expectException(PaymentRequestProblem::class);
        $this->requests->approve($request, $this->helper);
    }

    public function test_a_payment_reference_cannot_be_used_twice(): void
    {
        $student = User::factory()->create();
        $this->requests->create($student, PremiumPlan::Term, 'TX-400', null, null, $this->helper);

        foreach (['TX-400', 'tx-400 '] as $again) {
            try {
                $this->requests->create(User::factory()->create(), PremiumPlan::Term, $again, null, null, $this->helper);
                $this->fail("{$again} was accepted twice.");
            } catch (PaymentRequestProblem $e) {
                $this->assertSame('duplicate_reference', $e->reason);
            }
        }

        // A reference already used for a grant made directly is refused too.
        app(PremiumService::class)->grant(User::factory()->create(), PremiumPlan::Term, $this->helper, 'TX-500');
        $this->expectException(PaymentRequestProblem::class);
        $this->requests->create($student, PremiumPlan::Term, 'TX-500', null, null, $this->helper);
    }

    public function test_a_rejected_reference_may_be_entered_again(): void
    {
        $request = $this->requests->create(User::factory()->create(), PremiumPlan::Term, 'TX-600', null, null, $this->helper);
        $this->requests->reject($request, $this->helper, 'Wrong student');

        $again = $this->requests->create(User::factory()->create(), PremiumPlan::Term, 'TX-600', null, null, $this->helper);
        $this->assertSame(PaymentRequestStatus::Waiting, $again->status);
    }

    public function test_bad_input_is_refused(): void
    {
        $staff = User::factory()->staff(UserRole::ContentChecker)->create();
        $student = User::factory()->create();
        $cases = [
            'not_a_student' => fn () => $this->requests->create($staff, PremiumPlan::Term, 'TX-1', null, null, $this->helper),
            'paid_plan_only' => fn () => $this->requests->create($student, PremiumPlan::Referral, 'TX-2', null, null, $this->helper),
            'reference_required' => fn () => $this->requests->create($student, PremiumPlan::Term, '  ', null, null, $this->helper),
            'not_allowed' => fn () => $this->requests->create($student, PremiumPlan::Term, 'TX-3', null, null, $staff),
        ];
        foreach ($cases as $reason => $call) {
            try {
                $call();
                $this->fail("{$reason} was not refused.");
            } catch (PaymentRequestProblem $e) {
                $this->assertSame($reason, $e->reason);
            }
        }
        $this->assertSame(0, PaymentRequest::query()->count());
    }
}
