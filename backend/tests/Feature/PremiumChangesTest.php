<?php

namespace Tests\Feature;

use App\Enums\PremiumPlan;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\PremiumGrant;
use App\Models\User;
use App\Services\PaymentRequests;
use App\Services\PaymentRequests\PaymentRequestProblem;
use App\Services\PremiumService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Extend, end and undo Premium from a student's page: right dates, right roles, and every change
 * logged with the old and the new "Premium until".
 */
class PremiumChangesTest extends TestCase
{
    use RefreshDatabase;

    private PremiumService $premium;

    private User $helper;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-23 07:00:00');
        $this->premium = app(PremiumService::class);
        $this->helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function lastLog(string $action): array
    {
        $log = AuditLog::query()->where('action', $action)->latest('id')->firstOrFail();
        $this->assertSame($this->helper->id, $log->actor_id);

        return $log->data ?? [];
    }

    public function test_extend_adds_days_after_the_current_premium_and_logs_old_and_new(): void
    {
        $student = User::factory()->create();
        $this->premium->grant($student, PremiumPlan::Term, $this->helper, 'TX-1');
        $before = $this->premium->activeUntil($student);
        $this->assertNotNull($before);

        $grant = $this->premium->extend($student, 10, $this->helper, 'App was down for a week');

        $this->assertSame(PremiumPlan::Extra, $grant->plan);
        $this->assertTrue($grant->starts_at->equalTo($before));
        $this->assertTrue($this->premium->activeUntil($student)?->equalTo($before->copy()->addDays(10)));
        $this->assertSame('App was down for a week', $grant->note);
        $this->assertNull($grant->payment_ref);
        $data = $this->lastLog('premium.extended');
        $this->assertSame(['premium_until' => $before->toIso8601String()], $data['old']);
        $this->assertSame(['premium_until' => $before->copy()->addDays(10)->toIso8601String()], $data['new']);
        $this->assertSame('App was down for a week', $data['reason']);
    }

    public function test_extend_a_free_student_starts_now_and_checks_the_days(): void
    {
        $student = User::factory()->create();
        $this->premium->extend($student, 7, $this->helper, 'Competition prize');
        $this->assertTrue($this->premium->activeUntil($student)?->equalTo(Carbon::now()->addDays(7)));
        $this->assertSame(['premium_until' => null], $this->lastLog('premium.extended')['old']);

        $refused = 0;
        foreach ([0, -3, 400] as $days) {
            try {
                $this->premium->extend($student, $days, $this->helper, 'x');
            } catch (\InvalidArgumentException) {
                $refused++;
            }
        }
        $this->assertSame(3, $refused);
    }

    public function test_end_now_stops_the_running_grant_and_undoes_later_ones(): void
    {
        $student = User::factory()->create();
        $running = $this->premium->grant($student, PremiumPlan::Term, $this->helper, 'TX-2');
        $later = $this->premium->grant($student, PremiumPlan::Year, $this->helper, 'TX-3');
        $until = $this->premium->activeUntil($student);

        $changed = $this->premium->endNow($student, $this->helper, 'Refund');

        $this->assertSame(2, $changed);
        $this->assertFalse($this->premium->isPremium($student));
        $this->assertTrue($running->refresh()->ends_at->equalTo(Carbon::now()));
        $this->assertNull($running->revoked_at, 'the running grant is ended, not undone: its history stays true');
        $this->assertNotNull($later->refresh()->revoked_at);
        $data = $this->lastLog('premium.ended');
        $this->assertSame(['premium_until' => $until?->toIso8601String()], $data['old']);
        $this->assertSame(['premium_until' => null], $data['new']);

        $this->assertSame(0, $this->premium->endNow($student, $this->helper, 'again'), 'nothing left to end');
    }

    public function test_undo_latest_undoes_the_newest_grant_only(): void
    {
        $student = User::factory()->create();
        $first = $this->premium->grant($student, PremiumPlan::Term, $this->helper, 'TX-4');
        Carbon::setTestNow(Carbon::now()->addMinute());
        $mistake = $this->premium->grant($student, PremiumPlan::Year, $this->helper, 'TX-5');

        $undone = $this->premium->undoLatest($student, $this->helper, 'Wrong plan');

        $this->assertSame($mistake->id, $undone?->id);
        $this->assertNotNull($mistake->refresh()->revoked_at);
        $this->assertNull($first->refresh()->revoked_at);
        $this->assertSame(1, AuditLog::query()->where('action', 'premium.revoked')->count());

        $this->premium->undoLatest($student, $this->helper, 'again');
        $this->assertNull($this->premium->undoLatest($student, $this->helper, 'nothing left'));
    }

    public function test_only_payment_staff_change_premium(): void
    {
        $student = User::factory()->create();
        $checker = User::factory()->staff(UserRole::ContentChecker)->create();
        $refused = 0;
        foreach ([
            fn () => $this->premium->extend($student, 5, $checker, 'x'),
            fn () => $this->premium->endNow($student, $checker, 'x'),
            fn () => $this->premium->undoLatest($student, $checker, 'x'),
        ] as $change) {
            try {
                $change();
            } catch (AuthorizationException) {
                $refused++;
            }
        }
        $this->assertSame(3, $refused);
        $this->assertSame(0, PremiumGrant::query()->count());
    }

    public function test_extra_days_are_not_a_plan_students_can_pay_for(): void
    {
        $this->expectException(PaymentRequestProblem::class);
        app(PaymentRequests::class)->create(User::factory()->create(), PremiumPlan::Extra, 'TX-6', null, null, $this->helper);
    }

    public function test_extra_days_cannot_be_given_as_a_normal_grant(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->premium->grant(User::factory()->create(), PremiumPlan::Extra, $this->helper, 'TX-7');
    }
}
