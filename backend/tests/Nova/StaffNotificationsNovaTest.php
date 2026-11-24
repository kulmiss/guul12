<?php

namespace Tests\Nova;

use App\Contracts\StaffNotifier;
use App\Enums\PremiumPlan;
use App\Enums\UserRole;
use App\Models\User;
use App\Nova\NovaStaffNotifier;
use App\Services\PaymentRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The bell in Nova: staff notifications land in nova_notifications, only for staff with the
 * ability, never for the person who caused them, and each user sees only their own.
 * Runs only where the licensed Nova package is installed (php artisan test --testsuite=Nova).
 */
class StaffNotificationsNovaTest extends TestCase
{
    use RefreshDatabase;
    use ResetsNovaPageData;

    public function test_a_waiting_payment_rings_the_other_payment_staff_in_the_bell(): void
    {
        $this->assertInstanceOf(NovaStaffNotifier::class, app(StaffNotifier::class));
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        $otherHelper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        $boss = User::factory()->staff(UserRole::SuperAdmin)->create();
        $checker = User::factory()->staff(UserRole::ContentChecker)->create();
        $student = User::factory()->create();

        $request = app(PaymentRequests::class)->create($student, PremiumPlan::Term, 'TX-B1', 300, null, $helper);

        $bell = fn (User $u) => $this->actingAs($u)->getJson('/nova-api/nova-notifications')->assertOk()->json('notifications');
        $this->assertSame([], $bell($helper), 'the helper who typed it in gets no bell');
        $this->assertSame([], $bell($checker), 'content checkers cannot approve payments');
        foreach ([$otherHelper, $boss] as $staff) {
            $notes = $bell($staff);
            $this->assertCount(1, $notes);
            $this->assertSame("Payment waiting for approval: {$student->student_id}, Term, reference TX-B1.", $notes[0]['message']);
            $this->assertSame("/resources/payment-requests/{$request->id}", $notes[0]['actionUrl']);
            $this->assertSame('info', $notes[0]['type']);
        }
        $this->assertDatabaseMissing('nova_notifications', ['notifiable_id' => $student->id]);
    }

    public function test_an_unknown_type_falls_back_to_info(): void
    {
        $boss = User::factory()->staff(UserRole::SuperAdmin)->create();
        app(StaffNotifier::class)->notify($boss, 'Hello', 'shout');

        $notes = $this->actingAs($boss)->getJson('/nova-api/nova-notifications')->assertOk()->json('notifications');
        $this->assertSame('info', $notes[0]['type']);
        $this->assertNull($notes[0]['actionUrl']);
    }
}
