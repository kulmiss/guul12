<?php

namespace Tests\Feature;

use App\Enums\PremiumPlan;
use App\Enums\UserRole;
use App\Models\Attempt;
use App\Models\AuditLog;
use App\Models\Battle;
use App\Models\Chapter;
use App\Models\NicknameReport;
use App\Models\PaymentRequest;
use App\Models\Question;
use App\Models\Referral;
use App\Models\User;
use App\Services\NicknameModeration;
use App\Services\PremiumService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_removes_the_student_and_every_row_about_them(): void
    {
        // A student with data in many tables, and another student who must not be touched.
        $other = User::factory()->create(['nickname' => 'Other']);
        $device = $this->postJson('/api/v1/auth/device', ['install_id' => (string) Str::uuid(), 'platform' => 'android', 'app_version' => '1.0.0+1'])->assertCreated();
        $token = (string) $device->json('token');
        $me = User::query()->where('student_id', $device->json('user.student_id'))->firstOrFail();
        $me->forceFill(['nickname' => 'Leaving', 'grade' => 12, 'stream' => 'natural', 'region' => 'somali'])->save();

        $q = Question::factory()->withOptions()->create(['chapter_id' => Chapter::factory()->create()->id]);
        $this->withToken($token)->postJson('/api/v1/attempts', [
            'client_uuid' => (string) Str::uuid(),
            'mode' => 'quick10',
            'started_at' => now()->subMinute()->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'answers' => [['question_id' => $q->id, 'version' => 1, 'response' => 'A', 'time_ms' => 5000]],
        ])->assertCreated();
        $this->withToken($token)->putJson("/api/v1/bookmarks/{$q->id}")->assertSuccessful();
        Battle::query()->create(['code' => 'ABCDEF', 'key' => 'g12-natural', 'is_random' => false, 'question_ids' => [$q->id], 'creator_id' => $me->id, 'expires_at' => now()->addDay()]);
        Referral::query()->create(['inviter_id' => $other->id, 'invitee_id' => $me->id, 'status' => Referral::PENDING]);
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        app(PremiumService::class)->grant($me, PremiumPlan::Term, $helper, 'TX-1');
        PaymentRequest::query()->create(['user_id' => $me->id, 'plan' => PremiumPlan::Year, 'payment_ref' => 'TX-2', 'created_by' => $helper->id]);
        // Reports in both directions: by the student and about the student.
        NicknameReport::query()->create(['reporter_id' => $me->id, 'reported_user_id' => $other->id, 'nickname' => 'Other', 'reason' => 'rude']);
        NicknameReport::query()->create(['reporter_id' => $other->id, 'reported_user_id' => $me->id, 'nickname' => 'Me', 'reason' => 'rude']);

        $this->withToken($token)->deleteJson('/api/v1/me')->assertUnprocessable()->assertJsonPath('code', 'confirm_required');
        $this->withToken($token)->deleteJson('/api/v1/me?confirm=delete-my-account')->assertOk()->assertJsonPath('deleted', true);

        $this->assertNull(User::query()->find($me->id));
        // No row anywhere still points at the deleted student.
        foreach (Schema::getTables() as $table) {
            $name = $table['name'];
            foreach (['user_id', 'creator_id', 'opponent_id', 'invitee_id', 'tokenable_id', 'reporter_id', 'reported_user_id'] as $column) {
                if (Schema::hasColumn($name, $column)) {
                    $query = DB::table($name)->where($column, $me->id);
                    if ($column === 'tokenable_id') {
                        $query->where('tokenable_type', User::class);
                    }
                    $this->assertSame(0, $query->count(), "{$name}.{$column}");
                }
            }
        }
        $this->assertSame(0, Attempt::query()->count());
        $this->assertNotNull(User::query()->find($other->id));

        // The old token no longer works.
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_staff_logs_about_the_student_go_too_except_the_premium_accounting_lines(): void
    {
        $me = User::factory()->create(['nickname' => 'Leaving']);
        $other = User::factory()->create(['nickname' => 'Other']);
        $staff = User::factory()->staff(UserRole::SuperAdmin)->create();
        // I invited a friend (inviter side), and staff acted on my nickname and my reports.
        Referral::query()->create(['inviter_id' => $me->id, 'invitee_id' => $other->id, 'status' => Referral::PENDING]);
        $aboutMe = NicknameReport::query()->create(['reporter_id' => $other->id, 'reported_user_id' => $me->id, 'nickname' => 'Leaving', 'reason' => 'rude']);
        $moderation = app(NicknameModeration::class);
        $moderation->dismiss($aboutMe, $staff);
        $moderation->block($me, $staff);
        $me->forceFill(['nickname' => 'Leaving'])->save();
        $moderation->reset($me, $staff);
        $grant = app(PremiumService::class)->grant($me, PremiumPlan::Term, $staff, 'TX-9');
        $me->createToken('phone');
        $this->assertGreaterThanOrEqual(4, AuditLog::query()->count());

        // Deleted the way Nova does it (and the app: both call delete on the student).
        $me->delete();

        $this->assertSame(0, Referral::query()->count());
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $me->id)->count(), 'tokens go even without the app');
        $left = AuditLog::query()->get();
        $this->assertSame(['premium.granted'], $left->pluck('action')->all(), 'only the accounting line stays (delete page: "What we keep")');
        $this->assertSame($grant->id, $left->sole()->subject_id);
        $this->assertStringNotContainsString('Leaving', (string) json_encode($left->all()));
    }

    public function test_a_student_s_own_actions_never_store_their_ip_address(): void
    {
        $student = User::factory()->create();
        $staff = User::factory()->staff(UserRole::SuperAdmin)->create();
        $this->app->instance('request', Request::create('/api/v1/auth/google', 'POST', server: ['REMOTE_ADDR' => '196.188.1.2']));
        AuditLog::write($student, 'account.google_linked', $student);
        AuditLog::write($staff, 'ops.cache_refreshed');

        $this->assertNull(AuditLog::query()->where('actor_id', $student->id)->value('ip'));
        $this->assertSame('196.188.1.2', AuditLog::query()->where('actor_id', $staff->id)->value('ip'), 'staff actions keep the IP, for safety');
    }

    public function test_staff_cannot_delete_themselves_here_and_guests_cannot_call_it(): void
    {
        $this->deleteJson('/api/v1/me?confirm=delete-my-account')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->staff(UserRole::SuperAdmin)->create());
        $this->deleteJson('/api/v1/me?confirm=delete-my-account')->assertForbidden()->assertJsonPath('code', 'staff_account');
    }
}
