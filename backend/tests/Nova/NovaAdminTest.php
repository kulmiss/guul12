<?php

namespace Tests\Nova;

use App\Enums\PremiumPlan;
use App\Enums\UserRole;
use App\Models\PremiumGrant;
use App\Models\User;
use App\Providers\NovaServiceProvider;
use App\Services\PremiumService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Nova\Nova;
use Tests\TestCase;

/**
 * Runs only where the licensed Nova package is installed:
 *   php artisan test --testsuite=Nova
 * (CI runs it when the NOVA_* secrets exist; see docs/nova-setup.md.)
 */
class NovaAdminTest extends TestCase
{
    use RefreshDatabase;

    private function staff(UserRole $role): User
    {
        return User::factory()->staff($role)->create();
    }

    public function test_nova_is_loaded_and_only_staff_get_in(): void
    {
        $this->assertTrue(class_exists(Nova::class));
        $this->assertArrayHasKey(NovaServiceProvider::class, $this->app->getLoadedProviders());

        $this->get('/nova/login')->assertOk();
        $this->get('/nova')->assertRedirect();

        $student = User::factory()->create();
        $this->actingAs($student)->get('/nova/dashboards/main')->assertForbidden();

        foreach ([UserRole::SuperAdmin, UserRole::PaymentsHelper, UserRole::ContentChecker] as $role) {
            $this->actingAs($this->staff($role))->get('/nova/dashboards/main')->assertOk();
        }
    }

    public function test_each_role_sees_only_its_resources(): void
    {
        $cases = [
            'students' => [UserRole::SuperAdmin, UserRole::PaymentsHelper],
            'premium-grants' => [UserRole::SuperAdmin, UserRole::PaymentsHelper],
            'questions' => [UserRole::SuperAdmin, UserRole::ContentChecker],
            'question-reports' => [UserRole::SuperAdmin, UserRole::ContentChecker],
            'app-settings' => [UserRole::SuperAdmin],
            'staff' => [UserRole::SuperAdmin],
        ];
        $people = [
            UserRole::SuperAdmin->value => $this->staff(UserRole::SuperAdmin),
            UserRole::PaymentsHelper->value => $this->staff(UserRole::PaymentsHelper),
            UserRole::ContentChecker->value => $this->staff(UserRole::ContentChecker),
        ];

        foreach ($cases as $resource => $allowed) {
            foreach ($people as $role => $user) {
                $response = $this->actingAs($user)->getJson("/nova-api/{$resource}");
                $can = in_array(UserRole::from($role), $allowed, true);
                $this->assertSame($can ? 200 : 403, $response->status(), "{$role} on {$resource}");
            }
        }
    }

    public function test_a_helper_finds_a_student_by_id_and_gives_premium(): void
    {
        $student = User::factory()->create(['student_id' => 'ST-7K3Q9']);
        User::factory()->count(3)->create();
        $helper = $this->staff(UserRole::PaymentsHelper);

        // "st 7k3q9" finds "ST-7K3Q9".
        $found = $this->actingAs($helper)->getJson('/nova-api/students?search='.urlencode('st 7k3q9'))
            ->assertOk()->json('resources');
        $this->assertCount(1, $found);
        $this->assertSame($student->id, $found[0]['id']['value']);

        $this->actingAs($helper)->postJson('/nova-api/students/action?action=give-premium', [
            'resources' => (string) $student->id,
            'plan' => PremiumPlan::Term->value,
            'payment_ref' => 'TX-123',
        ])->assertOk();

        $grant = PremiumGrant::query()->where('user_id', $student->id)->firstOrFail();
        $this->assertSame(['TX-123', $helper->id], [$grant->payment_ref, $grant->granted_by]);
        $this->assertTrue(app(PremiumService::class)->isPremium($student));

        // Lists show the computed columns (plan, signed in, Premium until).
        $grants = $this->actingAs($helper)->getJson('/nova-api/premium-grants')->assertOk()->json('resources');
        $plans = array_values(array_filter($grants[0]['fields'], fn (array $f): bool => $f['name'] === 'Plan'));
        $this->assertSame(PremiumPlan::Term->value, $plans[0]['value'] ?? null);
        $this->actingAs($helper)->getJson("/nova-api/students/{$student->id}")->assertOk()
            ->assertJsonFragment(['name' => 'Signed in', 'value' => false])
            ->assertJsonFragment(['name' => 'Plan', 'value' => 'Premium']);

        // Undo Premium is a destructive action and needs a reason.
        $this->actingAs($helper)->postJson('/nova-api/premium-grants/action?action=undo-premium', [
            'resources' => (string) $grant->id,
        ])->assertUnprocessable();
        $this->actingAs($helper)->postJson('/nova-api/premium-grants/action?action=undo-premium', [
            'resources' => (string) $grant->id,
            'reason' => 'Payment bounced',
        ])->assertOk();
        $this->assertNotNull($grant->refresh()->revoked_at);
        $this->assertFalse(app(PremiumService::class)->isPremium($student->refresh()));
    }

    public function test_a_super_admin_creates_staff_with_a_hidden_student_id(): void
    {
        $admin = $this->staff(UserRole::SuperAdmin);

        $this->actingAs($admin)->postJson('/nova-api/staff', [
            'name' => 'Helper One',
            'email' => 'helper@kulmiss.com',
            'role' => UserRole::PaymentsHelper->value,
            'password' => 'a-long-password-123',
        ])->assertCreated();

        $helper = User::query()->where('email', 'helper@kulmiss.com')->firstOrFail();
        $this->assertSame(UserRole::PaymentsHelper, $helper->role);
        $this->assertMatchesRegularExpression('/^ST-[A-Z0-9]{5}$/', $helper->student_id);
    }
}
