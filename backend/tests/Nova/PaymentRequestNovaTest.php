<?php

namespace Tests\Nova;

use App\Enums\PaymentRequestStatus;
use App\Enums\PremiumPlan;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\PaymentRequest;
use App\Models\User;
use App\Services\PaymentRequests;
use App\Services\PremiumService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Payment requests in Nova: who can open them, adding one, Approve and Reject.
 * Runs only where the licensed Nova package is installed (php artisan test --testsuite=Nova).
 */
class PaymentRequestNovaTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_payment_staff_open_payment_requests(): void
    {
        foreach ([[UserRole::SuperAdmin, 200], [UserRole::PaymentsHelper, 200], [UserRole::ContentChecker, 403]] as [$role, $status]) {
            $this->actingAs(User::factory()->staff($role)->create())->getJson('/nova-api/payment-requests')->assertStatus($status);
        }
    }

    public function test_a_helper_adds_a_request_and_it_waits(): void
    {
        $student = User::factory()->create();
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();

        $this->actingAs($helper)->postJson('/nova-api/payment-requests', [
            'student' => $student->id,
            'plan' => PremiumPlan::Term->value,
            'payment_ref' => ' TX-900 ',
            'amount_birr' => 250,
        ])->assertCreated();

        $request = PaymentRequest::query()->sole();
        $this->assertSame([$student->id, PaymentRequestStatus::Waiting, 'TX-900', $helper->id],
            [$request->user_id, $request->status, $request->payment_ref, $request->created_by]);
        $this->assertSame(1, AuditLog::query()->where('action', 'payment_request.created')->count());
        $this->assertFalse(app(PremiumService::class)->isPremium($student));

        // The same reference again is refused with a clear message, and Referral is not a paid plan.
        $this->actingAs($helper)->postJson('/nova-api/payment-requests', [
            'student' => User::factory()->create()->id, 'plan' => PremiumPlan::Term->value, 'payment_ref' => 'tx-900',
        ])->assertUnprocessable()->assertJsonValidationErrors('payment_ref');
        $this->actingAs($helper)->postJson('/nova-api/payment-requests', [
            'student' => $student->id, 'plan' => PremiumPlan::Referral->value, 'payment_ref' => 'TX-901',
        ])->assertUnprocessable()->assertJsonValidationErrors('plan');
        $this->assertSame(1, PaymentRequest::query()->count());
    }

    public function test_approve_and_reject_actions(): void
    {
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        $student = User::factory()->create();
        $requests = app(PaymentRequests::class);
        $first = $requests->create($student, PremiumPlan::Term, 'TX-1', 250, null, $helper);
        $second = $requests->create(User::factory()->create(), PremiumPlan::Year, 'TX-2', 600, null, $helper);

        $this->actingAs($helper)->postJson('/nova-api/payment-requests/action?action=approve-payment', ['resources' => (string) $first->id])
            ->assertOk()->assertJsonPath('message', 'Premium given for 1 request(s).');
        $this->assertSame(PaymentRequestStatus::Approved, $first->refresh()->status);
        $this->assertTrue(app(PremiumService::class)->isPremium($student));

        $this->actingAs($helper)->postJson('/nova-api/payment-requests/action?action=reject-payment', ['resources' => (string) $second->id])
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->actingAs($helper)->postJson('/nova-api/payment-requests/action?action=reject-payment', ['resources' => (string) $second->id, 'reason' => 'Money not received'])
            ->assertOk();
        $this->assertSame(PaymentRequestStatus::Rejected, $second->refresh()->status);

        // Approving a decided request again does nothing and says so.
        $this->actingAs($helper)->postJson('/nova-api/payment-requests/action?action=approve-payment', ['resources' => (string) $second->id])
            ->assertOk()->assertJsonPath('danger', 'Nothing done: 1 request(s) were already decided.');
    }

    public function test_decided_requests_cannot_be_edited_and_nobody_deletes(): void
    {
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        $request = app(PaymentRequests::class)->create(User::factory()->create(), PremiumPlan::Term, 'TX-7', null, null, $helper);

        $edit = ['student' => $request->user_id, 'plan' => PremiumPlan::Year->value, 'payment_ref' => 'TX-7B'];
        $this->actingAs($helper)->getJson("/nova-api/payment-requests/{$request->id}")->assertOk()->assertJsonPath('resource.authorizedToUpdate', true);
        $this->actingAs($helper)->putJson("/nova-api/payment-requests/{$request->id}", $edit)->assertOk();
        $this->assertSame([PremiumPlan::Year, 'TX-7B'], [$request->refresh()->plan, $request->payment_ref]);
        $this->assertSame(1, AuditLog::query()->where('action', 'payment_request.edited')->count());

        app(PaymentRequests::class)->approve($request, $helper);
        // Decided: no Edit button, and saving is refused.
        $this->actingAs($helper)->getJson("/nova-api/payment-requests/{$request->id}")->assertJsonPath('resource.authorizedToUpdate', false);
        $this->actingAs($helper)->putJson("/nova-api/payment-requests/{$request->id}", [...$edit, 'payment_ref' => 'TX-7C'])->assertForbidden();
        $this->assertSame('TX-7B', $request->refresh()->payment_ref);
        $this->actingAs(User::factory()->staff(UserRole::SuperAdmin)->create())
            ->deleteJson('/nova-api/payment-requests?resources[]='.$request->id)->assertOk();
        $this->assertNotNull(PaymentRequest::query()->find($request->id), 'payment requests are never deleted');
    }
}
