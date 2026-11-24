<?php

namespace Tests\Nova;

use App\Enums\PremiumPlan;
use App\Enums\UserRole;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\Battle;
use App\Models\Chapter;
use App\Models\Mock;
use App\Models\NicknameReport;
use App\Models\PremiumGrant;
use App\Models\Question;
use App\Models\QuestionReport;
use App\Models\Referral;
use App\Models\Subject;
use App\Models\User;
use App\Services\PaymentRequests;
use App\Services\PremiumService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Every role is refused on resources outside its job, not only in lists but also on detail pages,
 * saves and deletes called directly (Nova's own checks only look at policies, which we do not use).
 * Runs only where the licensed Nova package is installed (php artisan test --testsuite=Nova).
 */
class NovaAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: int, 1: list<UserRole>}> resource uri => [record id, roles allowed]
     */
    private function records(): array
    {
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        $student = User::factory()->create(['nickname' => 'Someone']);
        $grant = app(PremiumService::class)->grant($student, PremiumPlan::Term, $helper, 'TX-M1');
        $payment = app(PaymentRequests::class)->create(User::factory()->create(), PremiumPlan::Term, 'TX-M2', null, null, $helper);
        $question = Question::factory()->create(['chapter_id' => Chapter::factory()->create()->id]);
        $setting = AppSetting::query()->create(['key' => 'exam_date', 'value' => '2027-06-29']);
        $log = AuditLog::write(null, 'test.event');
        $report = NicknameReport::query()->create(['reporter_id' => User::factory()->create()->id, 'reported_user_id' => $student->id, 'nickname' => 'Someone', 'reason' => 'rude']);
        $mock = Mock::query()->create(['uid' => 'm-1', 'subject_id' => Subject::factory()->create()->id, 'grade' => 12, 'number' => 1, 'title' => 'Mock 1', 'minutes' => 60]);
        $battle = Battle::query()->create(['code' => 'ABC123', 'key' => 'g12-natural', 'is_random' => false, 'question_ids' => [1], 'creator_id' => $student->id, 'expires_at' => now()->addDay()]);
        $referral = Referral::query()->create(['inviter_id' => $student->id, 'invitee_id' => User::factory()->create()->id, 'status' => Referral::PENDING]);

        $premium = [UserRole::SuperAdmin, UserRole::PaymentsHelper];
        $content = [UserRole::SuperAdmin, UserRole::ContentChecker];
        $admin = [UserRole::SuperAdmin];

        return [
            'students' => [$student->id, $premium],
            'premium-grants' => [$grant->id, $premium],
            'payment-requests' => [$payment->id, $premium],
            'questions' => [$question->id, $content],
            'chapters' => [$question->chapter_id, $content],
            'nickname-reports' => [$report->id, $content],
            'mocks' => [$mock->id, $content],
            'battles' => [$battle->id, $content],
            'referrals' => [$referral->id, $premium],
            'app-settings' => [$setting->id, $admin],
            'audit-logs' => [$log->id, $admin],
            'staff' => [$helper->id, $admin],
        ];
    }

    public function test_roles_are_refused_on_detail_save_and_delete_outside_their_job(): void
    {
        $records = $this->records();
        $staff = [];
        foreach (UserRole::cases() as $role) {
            if ($role !== UserRole::Student) {
                $staff[$role->value] = User::factory()->staff($role)->create();
            }
        }

        foreach ($records as $uri => [$id, $allowed]) {
            foreach ($staff as $roleValue => $user) {
                $can = in_array(UserRole::from($roleValue), $allowed, true);
                $label = "{$roleValue} on {$uri}";

                $this->actingAs($user)->getJson("/nova-api/{$uri}/{$id}")->assertStatus($can ? 200 : 403);
                if ($can) {
                    continue;
                }
                $table = $this->tableFor($uri);
                $before = DB::table($table)->where('id', $id)->first();

                $this->actingAs($user)->putJson("/nova-api/{$uri}/{$id}", ['note' => 'x', 'grade' => 9, 'value' => 'x'])
                    ->assertStatus(403);
                $this->actingAs($user)->deleteJson("/nova-api/{$uri}?resources[]={$id}");

                $this->assertEquals($before, DB::table($table)->where('id', $id)->first(), "{$label}: the row changed");
            }
        }
    }

    public function test_actions_on_resources_outside_the_job_are_refused(): void
    {
        $records = $this->records();
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        $checker = User::factory()->staff(UserRole::ContentChecker)->create();
        $question = Question::query()->findOrFail($records['questions'][0]);
        $report = QuestionReport::query()->create([
            'question_id' => $question->id, 'user_id' => User::factory()->create()->id,
            'question_version' => (int) $question->refresh()->version, 'reason' => 'wrong_answer',
        ]);

        // A payments helper cannot resolve question reports; a content checker cannot give Premium.
        $keys = array_column((array) $this->actingAs($checker)->getJson('/nova-api/question-reports/actions')->assertOk()->json('actions'), 'uriKey');
        $this->assertContains('reject-the-question-is-right', $keys);
        $this->assertRefused($this->actingAs($helper)->postJson('/nova-api/question-reports/action?action=reject-the-question-is-right', ['resources' => (string) $report->id]));
        $this->assertSame('open', $report->refresh()->status->value);
        $this->assertRefused($this->actingAs($checker)->postJson('/nova-api/students/action?action=give-premium', [
            'resources' => (string) $records['students'][0], 'plan' => 'term', 'payment_ref' => 'TX-X',
        ]));
        $this->assertRefused($this->actingAs($checker)->postJson('/nova-api/payment-requests/action?action=approve-payment', ['resources' => (string) $records['payment-requests'][0]]));
        $this->assertSame(1, PremiumGrant::query()->count());
    }

    /**
     * Refused: forbidden, or the action does not exist for this role. Never run.
     *
     * @param  TestResponse<Response>  $response
     */
    private function assertRefused(TestResponse $response): void
    {
        $this->assertContains($response->status(), [403, 404], 'expected the action to be refused, got '.$response->status());
    }

    private function tableFor(string $uri): string
    {
        return match ($uri) {
            'students', 'staff' => 'users',
            'app-settings' => 'app_settings',
            'audit-logs' => 'audit_logs',
            default => str_replace('-', '_', $uri),
        };
    }
}
