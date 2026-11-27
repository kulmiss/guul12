<?php

namespace Tests\Nova;

use App\Enums\NicknameReportStatus;
use App\Enums\UserRole;
use App\Models\Attempt;
use App\Models\AuditLog;
use App\Models\NicknameReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Nickname reports in Nova: who sees them, and the Reset / Not a problem / Block actions.
 * Runs only where the licensed Nova package is installed (php artisan test --testsuite=Nova).
 */
class NicknameModerationNovaTest extends TestCase
{
    use RefreshDatabase;

    private function report(User $target, string $by = 'Reporter'): NicknameReport
    {
        return NicknameReport::query()->create([
            'reporter_id' => User::factory()->create(['nickname' => $by])->id,
            'reported_user_id' => $target->id,
            'nickname' => (string) $target->nickname,
            'reason' => 'rude',
        ]);
    }

    public function test_only_moderators_open_nickname_reports(): void
    {
        $this->report(User::factory()->create(['nickname' => 'Badname']));

        foreach ([[UserRole::SuperAdmin, 200], [UserRole::ContentChecker, 200], [UserRole::PaymentsHelper, 403]] as [$role, $status]) {
            $staff = User::factory()->staff($role)->create();
            $this->actingAs($staff)->getJson('/nova-api/nickname-reports')->assertStatus($status);
        }
        $checker = User::factory()->staff(UserRole::ContentChecker)->create();
        $rows = $this->actingAs($checker)->getJson('/nova-api/nickname-reports')->json('resources');
        $this->assertIsArray($rows);
        $this->assertCount(1, $rows);
        // Students cannot be created from here, and reports cannot be edited.
        $this->actingAs($checker)->getJson('/nova-api/nickname-reports/creation-fields')->assertForbidden();
    }

    public function test_reset_needs_the_word_confirm_and_closes_all_open_reports(): void
    {
        $bad = User::factory()->create(['nickname' => 'Badname']);
        $first = $this->report($bad, 'A');
        $this->report($bad, 'B');
        $checker = User::factory()->staff(UserRole::ContentChecker)->create();

        $this->actingAs($checker)->postJson('/nova-api/nickname-reports/action?action=reset-nickname', [
            'resources' => (string) $first->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('confirm');
        $this->actingAs($checker)->postJson('/nova-api/nickname-reports/action?action=reset-nickname', [
            'resources' => (string) $first->id, 'confirm' => 'confirm',
        ])->assertUnprocessable();
        $this->assertSame('Badname', $bad->refresh()->nickname);

        $this->actingAs($checker)->postJson('/nova-api/nickname-reports/action?action=reset-nickname', [
            'resources' => (string) $first->id, 'confirm' => 'CONFIRM',
        ])->assertOk();

        $this->assertNull($bad->refresh()->nickname);
        $this->assertSame(2, NicknameReport::query()->where('status', NicknameReportStatus::Reset->value)->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'nickname.reset')->where('actor_id', $checker->id)->count());
    }

    public function test_reset_twice_says_nothing_to_reset(): void
    {
        $bad = User::factory()->create(['nickname' => 'Badname']);
        $report = $this->report($bad);
        $checker = User::factory()->staff(UserRole::ContentChecker)->create();
        $bad->forceFill(['nickname' => null])->save();

        $this->actingAs($checker)->postJson('/nova-api/nickname-reports/action?action=reset-nickname', [
            'resources' => (string) $report->id, 'confirm' => 'CONFIRM',
        ])->assertOk()->assertJsonPath('danger', 'Nothing to reset: the nickname was already removed.');
        $this->assertSame(0, AuditLog::query()->where('action', 'nickname.reset')->count());
    }

    public function test_not_a_problem_and_block_from_a_report(): void
    {
        $user = User::factory()->create(['nickname' => 'Fine']);
        $report = $this->report($user);
        $checker = User::factory()->staff(UserRole::ContentChecker)->create();

        $this->actingAs($checker)->postJson('/nova-api/nickname-reports/action?action=not-a-problem', ['resources' => (string) $report->id])->assertOk();
        $this->assertSame(NicknameReportStatus::Dismissed, $report->refresh()->status);
        $this->assertSame('Fine', $user->refresh()->nickname);

        $this->actingAs($checker)->postJson('/nova-api/nickname-reports/action?action=block-from-leaderboard', ['resources' => (string) $report->id])->assertOk();
        $this->assertNotNull($user->refresh()->leaderboard_blocked_at);
    }

    public function test_student_page_actions_follow_the_role(): void
    {
        $student = User::factory()->create(['nickname' => 'Badname']);
        $admin = User::factory()->staff(UserRole::SuperAdmin)->create();
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();

        $names = fn (User $staff): array => array_column(
            (array) $this->actingAs($staff)->getJson("/nova-api/students/actions?resourceId={$student->id}&display=detail")->assertOk()->json('actions'),
            'uriKey',
        );
        $this->assertContains('reset-nickname', $names($admin));
        $this->assertContains('block-from-leaderboard', $names($admin));
        $this->assertNotContains('reset-nickname', $names($helper));
        $this->assertNotContains('block-from-leaderboard', $names($helper));

        // A payments helper cannot run it even by calling the address directly.
        $this->actingAs($helper)->postJson('/nova-api/students/action?action=block-from-leaderboard', ['resources' => (string) $student->id])->assertForbidden();
        $this->assertNull($student->refresh()->leaderboard_blocked_at);

        $this->actingAs($admin)->postJson('/nova-api/students/action?action=block-from-leaderboard', ['resources' => (string) $student->id])->assertOk();
        $this->assertNotNull($student->refresh()->leaderboard_blocked_at);
        $this->actingAs($admin)->postJson('/nova-api/students/action?action=unblock-from-leaderboard', ['resources' => (string) $student->id])->assertOk();
        $this->assertNull($student->refresh()->leaderboard_blocked_at);
    }

    /**
     * The whole path (QUESTIONS #45): a student flags a nickname from the leaderboard in the app →
     * the moderators' bell rings and the report is in the Nova queue → Reset (CONFIRM) → the
     * leaderboard drops the name at once and it cannot be chosen again.
     */
    public function test_end_to_end_from_the_app_flag_to_the_reset(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-25 12:00', 'Africa/Addis_Ababa'));
        $profile = ['grade' => 12, 'stream' => 'natural', 'avatar_style' => 'thumbs', 'avatar_seed' => 'a01'];
        $rude = User::factory()->create(['nickname' => 'Badname', ...$profile]);
        $viewer = User::factory()->create(['nickname' => 'Ayan', ...$profile]);
        foreach ([$rude, $viewer] as $student) {
            Attempt::query()->create([
                'user_id' => $student->id, 'client_uuid' => (string) Str::uuid(), 'mode' => 'quick10',
                'started_at' => Carbon::now()->subHour(), 'finished_at' => Carbon::now()->subHour(), 'total' => 10, 'correct' => 5,
            ]);
        }
        $checker = User::factory()->staff(UserRole::ContentChecker)->create();
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();

        // 1. The app: the leaderboard row carries the private reference; the student flags it.
        Sanctum::actingAs($viewer);
        $rows = collect((array) $this->getJson('/api/v1/leaderboard')->assertOk()->json('top'));
        $ref = $rows->firstWhere('nickname', 'Badname')['ref'] ?? null;
        $this->assertIsString($ref);
        $this->postJson('/api/v1/nickname-reports', ['ref' => $ref, 'reason' => 'rude'])->assertCreated();

        // 2. Nova: the bell rings for moderators only, and the report waits in the queue.
        $bell = fn (User $u): array => (array) $this->actingAs($u)->getJson('/nova-api/nova-notifications')->assertOk()->json('notifications');
        $this->assertSame('New nickname report: "Badname" (rude).', $bell($checker)[0]['message'] ?? null);
        $this->assertSame([], $bell($helper));
        $queue = (array) $this->actingAs($checker)->getJson('/nova-api/nickname-reports')->assertOk()->json('resources');
        $this->assertCount(1, $queue);
        $reportId = (string) $queue[0]['id']['value'];
        $this->actingAs($helper)->getJson('/nova-api/nickname-reports')->assertForbidden();

        // 3. The action: Reset with the CONFIRM word, logged old → new.
        $this->actingAs($checker)->postJson('/nova-api/nickname-reports/action?action=reset-nickname', [
            'resources' => $reportId, 'confirm' => 'CONFIRM',
        ])->assertOk();
        $this->assertSame(NicknameReportStatus::Reset, NicknameReport::query()->sole()->status);
        $log = AuditLog::query()->where('action', 'nickname.reset')->sole();
        $this->assertSame(['old' => 'Badname', 'new' => null], array_intersect_key((array) $log->data, ['old' => 0, 'new' => 0]));

        // 4. Back in the app: the name is gone from the leaderboard and cannot come back.
        Sanctum::actingAs($viewer);
        $this->assertNull(collect((array) $this->getJson('/api/v1/leaderboard')->json('top'))->firstWhere('nickname', 'Badname'));
        Sanctum::actingAs($rude);
        $this->patchJson('/api/v1/me', ['nickname' => 'BADNAME'])->assertStatus(422)->assertJsonValidationErrors('nickname');
        $this->patchJson('/api/v1/me', ['nickname' => 'Kindname'])->assertOk();
        Carbon::setTestNow();
    }
}
