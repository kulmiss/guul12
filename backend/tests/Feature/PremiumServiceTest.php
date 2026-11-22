<?php

namespace Tests\Feature;

use App\Enums\PremiumPlan;
use App\Enums\UserRole;
use App\Models\PremiumGrant;
use App\Models\User;
use App\Services\PremiumService;
use App\Services\Settings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PremiumServiceTest extends TestCase
{
    use RefreshDatabase;

    private PremiumService $premium;

    private User $helper;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-12-01 10:00:00');
        $this->premium = app(PremiumService::class);
        $this->helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        $this->student = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_helper_grants_a_term_and_it_is_logged(): void
    {
        $grant = $this->premium->grant($this->student, PremiumPlan::Term, $this->helper, ' TX-123 ');

        $this->assertTrue($this->premium->isPremium($this->student));
        $this->assertSame('TX-123', $grant->payment_ref);
        $this->assertTrue($grant->ends_at->equalTo(Carbon::now()->addDays(120)));
        $this->assertDatabaseHas('audit_logs', ['action' => 'premium.granted', 'actor_id' => $this->helper->id, 'subject_id' => $grant->id]);
    }

    public function test_plan_lengths_come_from_settings(): void
    {
        app(Settings::class)->set('premium_year_days', 366);

        $grant = $this->premium->grant($this->student, PremiumPlan::Year, $this->helper, 'TX-1');

        $this->assertSame(366, (int) $grant->starts_at->diffInDays($grant->ends_at));
    }

    public function test_new_grant_starts_when_the_current_one_ends(): void
    {
        $first = $this->premium->grant($this->student, PremiumPlan::Term, $this->helper, 'TX-1');
        $second = $this->premium->grant($this->student, PremiumPlan::Year, $this->helper, 'TX-2');

        $this->assertTrue($second->starts_at->equalTo($first->ends_at));
        $this->assertTrue($this->premium->activeUntil($this->student)?->equalTo($second->ends_at));
    }

    public function test_premium_ends_and_student_returns_to_free(): void
    {
        $grant = $this->premium->grant($this->student, PremiumPlan::Term, $this->helper, 'TX-1');

        Carbon::setTestNow($grant->ends_at->copy()->subSecond());
        $this->assertTrue($this->premium->isPremium($this->student));

        Carbon::setTestNow($grant->ends_at);
        $this->assertFalse($this->premium->isPremium($this->student));
        $this->assertNull($this->premium->activeUntil($this->student));
    }

    public function test_undo_removes_premium_and_is_logged(): void
    {
        $grant = $this->premium->grant($this->student, PremiumPlan::Term, $this->helper, 'TX-1');

        $this->premium->revoke($grant, $this->helper, 'wrong student');
        $this->premium->revoke($grant->refresh(), $this->helper); // second undo does nothing

        $this->assertFalse($this->premium->isPremium($this->student));
        $this->assertSame($this->helper->id, $grant->refresh()->revoked_by);
        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertDatabaseHas('audit_logs', ['action' => 'premium.revoked']);
    }

    public function test_only_payment_helpers_and_super_admin_can_grant(): void
    {
        $checker = User::factory()->staff(UserRole::ContentChecker)->create();

        $this->expectException(AuthorizationException::class);
        $this->premium->grant($this->student, PremiumPlan::Term, $checker, 'TX-1');
    }

    public function test_super_admin_can_grant(): void
    {
        $admin = User::factory()->staff(UserRole::SuperAdmin)->create();

        $this->premium->grant($this->student, PremiumPlan::Term, $admin, 'TX-1');

        $this->assertTrue($this->premium->isPremium($this->student));
    }

    public function test_content_checker_cannot_undo(): void
    {
        $grant = $this->premium->grant($this->student, PremiumPlan::Term, $this->helper, 'TX-1');
        $checker = User::factory()->staff(UserRole::ContentChecker)->create();

        $this->expectException(AuthorizationException::class);
        $this->premium->revoke($grant, $checker);
    }

    public function test_paid_plan_needs_a_payment_reference(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->premium->grant($this->student, PremiumPlan::Term, $this->helper, '   ');
    }

    public function test_referral_reward_needs_no_reference_and_lasts_seven_days(): void
    {
        $grant = $this->premium->grant($this->student, PremiumPlan::Referral, null);

        $this->assertSame(7, (int) $grant->starts_at->diffInDays($grant->ends_at));
        $this->assertNull($grant->granted_by);
    }

    public function test_staff_cannot_receive_premium(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->premium->grant($this->helper, PremiumPlan::Term, $this->helper, 'TX-1');
    }

    public function test_future_grant_does_not_count_yet(): void
    {
        PremiumGrant::query()->create([
            'user_id' => $this->student->id,
            'plan' => PremiumPlan::Term,
            'starts_at' => Carbon::now()->addDay(),
            'ends_at' => Carbon::now()->addDays(10),
        ]);

        $this->assertFalse($this->premium->isPremium($this->student));
        $this->assertNull($this->premium->activeUntil($this->student));
    }
}
