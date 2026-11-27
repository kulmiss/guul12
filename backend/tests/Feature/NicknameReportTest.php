<?php

namespace Tests\Feature;

use App\Enums\NicknameReportStatus;
use App\Enums\UserRole;
use App\Models\Attempt;
use App\Models\AuditLog;
use App\Models\Chapter;
use App\Models\NicknameReport;
use App\Models\Question;
use App\Models\User;
use App\Services\Leaderboard;
use App\Services\NicknameModeration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * "Report nickname" from the leaderboard and battles, and what staff can do about it (DECISIONS #33).
 */
class NicknameReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-25 12:00', Leaderboard::TIMEZONE));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function student(?string $nickname): User
    {
        return User::factory()->create([
            'nickname' => $nickname,
            'avatar_style' => $nickname === null ? null : 'thumbs',
            'avatar_seed' => $nickname === null ? null : 'a01',
            'grade' => 12,
            'stream' => 'natural',
        ]);
    }

    private function studied(User $user, int $correct = 5): void
    {
        $at = Carbon::now()->subHour()->utc();
        Attempt::query()->create([
            'user_id' => $user->id, 'client_uuid' => (string) Str::uuid(), 'mode' => 'quick10',
            'started_at' => $at, 'finished_at' => $at, 'total' => 10, 'correct' => $correct,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function leaderboardAs(User $user): array
    {
        Sanctum::actingAs($user);
        $rows = $this->getJson('/api/v1/leaderboard')->assertOk()->json('top');
        $this->assertIsArray($rows);

        return $rows;
    }

    private function refOf(User $viewer, string $nickname): string
    {
        foreach ($this->leaderboardAs($viewer) as $row) {
            if ($row['nickname'] === $nickname) {
                $this->assertIsString($row['ref']);

                return $row['ref'];
            }
        }
        $this->fail("{$nickname} is not on the leaderboard.");
    }

    public function test_leaderboard_rows_carry_a_private_report_reference_except_my_own(): void
    {
        $me = $this->student('Ayan');
        $other = $this->student('Badname');
        $this->studied($me);
        $this->studied($other, 8);

        $rows = $this->leaderboardAs($me);
        $mine = collect($rows)->firstWhere('nickname', 'Ayan');
        $theirs = collect($rows)->firstWhere('nickname', 'Badname');
        $this->assertIsArray($mine);
        $this->assertIsArray($theirs);
        $this->assertNull($mine['ref']);
        $this->assertIsString($theirs['ref']);
        // Rows have no ID fields, the student ID never appears, and the reference is encrypted (not readable).
        $this->assertSame(['rank', 'nickname', 'avatar_style', 'avatar_seed', 'xp', 'is_me', 'ref'], array_keys($theirs));
        $this->assertStringNotContainsString($other->student_id, (string) json_encode($rows));
        $this->assertStringNotContainsString('nick:', (string) base64_decode($theirs['ref']));
        $this->assertSame($other->id, app(NicknameModeration::class)->userFromRef($theirs['ref'])?->id);
    }

    public function test_a_student_reports_a_nickname_once(): void
    {
        $me = $this->student('Ayan');
        $other = $this->student('Badname');
        $this->studied($other);
        $ref = $this->refOf($me, 'Badname');

        $this->postJson('/api/v1/nickname-reports', ['ref' => $ref, 'reason' => 'rude', 'note' => '  rude word  '])
            ->assertCreated()->assertJsonPath('report.reason', 'rude');
        $this->postJson('/api/v1/nickname-reports', ['ref' => $ref, 'reason' => 'other'])->assertOk();

        $report = NicknameReport::query()->sole();
        $this->assertSame([$me->id, $other->id, 'Badname', 'rude word', NicknameReportStatus::Open],
            [$report->reporter_id, $report->reported_user_id, $report->nickname, $report->note, $report->status]);
    }

    public function test_bad_requests_are_refused(): void
    {
        $me = $this->student('Ayan');
        $this->studied($me);
        $mine = app(NicknameModeration::class)->refFor($me);
        Sanctum::actingAs($me);

        $this->postJson('/api/v1/nickname-reports', ['ref' => $mine, 'reason' => 'rude'])
            ->assertStatus(422)->assertJsonPath('code', 'own_nickname');
        $this->postJson('/api/v1/nickname-reports', ['ref' => 'not-a-ref', 'reason' => 'rude'])
            ->assertStatus(422)->assertJsonPath('code', 'unknown_student');
        $this->postJson('/api/v1/nickname-reports', ['ref' => $mine, 'reason' => 'spam'])->assertStatus(422);

        $noName = $this->student(null);
        $this->postJson('/api/v1/nickname-reports', ['ref' => app(NicknameModeration::class)->refFor($noName), 'reason' => 'rude'])
            ->assertStatus(422)->assertJsonPath('code', 'no_nickname');
        $this->assertSame(0, NicknameReport::query()->count());
    }

    public function test_daily_limit(): void
    {
        $me = $this->student('Ayan');
        Sanctum::actingAs($me);
        $moderation = app(NicknameModeration::class);
        for ($i = 0; $i < NicknameModeration::DAILY_LIMIT; $i++) {
            $this->postJson('/api/v1/nickname-reports', ['ref' => $moderation->refFor($this->student("Name{$i}")), 'reason' => 'rude'])->assertCreated();
        }
        $this->postJson('/api/v1/nickname-reports', ['ref' => $moderation->refFor($this->student('OneMore')), 'reason' => 'rude'])
            ->assertStatus(429)->assertJsonPath('code', 'report_limit');
    }

    public function test_daily_limit_follows_the_addis_ababa_day(): void
    {
        $me = $this->student('Ayan');
        Sanctum::actingAs($me);
        $moderation = app(NicknameModeration::class);
        // 02:30 in Addis Ababa = 23:30 UTC the day before.
        Carbon::setTestNow(Carbon::parse('2026-11-09 23:30', 'UTC'));
        for ($i = 0; $i < NicknameModeration::DAILY_LIMIT; $i++) {
            $this->postJson('/api/v1/nickname-reports', ['ref' => $moderation->refFor($this->student("Name{$i}")), 'reason' => 'rude'])->assertCreated();
        }
        // 04:00 in Addis Ababa: the same day.
        Carbon::setTestNow(Carbon::parse('2026-11-10 01:00', 'UTC'));
        $this->postJson('/api/v1/nickname-reports', ['ref' => $moderation->refFor($this->student('Later')), 'reason' => 'rude'])->assertStatus(429);
        // Midnight in Addis Ababa: a new day.
        Carbon::setTestNow(Carbon::parse('2026-11-10 21:00', 'UTC'));
        $this->postJson('/api/v1/nickname-reports', ['ref' => $moderation->refFor($this->student('NextDay')), 'reason' => 'rude'])->assertCreated();
        Carbon::setTestNow();
    }

    public function test_battle_opponent_carries_a_reference(): void
    {
        $ayan = $this->student('Ayan');
        $abdi = $this->student('Abdi');
        $chapter = Chapter::factory()->create();
        Question::factory()->count(15)->withOptions()->create(['chapter_id' => $chapter->id]);

        Sanctum::actingAs($ayan);
        $code = $this->postJson('/api/v1/battles', ['type' => 'friend'])->assertCreated()->json('battle.code');
        Sanctum::actingAs($abdi);
        $battle = $this->postJson('/api/v1/battles/join', ['code' => $code])->assertOk()->json('battle');
        $this->assertIsArray($battle);
        $this->assertNull($battle['me']['ref'] ?? null);
        $this->assertSame($ayan->id, app(NicknameModeration::class)->userFromRef((string) $battle['opponent']['ref'])?->id);
    }

    public function test_staff_reset_a_nickname_and_it_cannot_be_chosen_again(): void
    {
        $bad = $this->student('Badname');
        $checker = User::factory()->staff(UserRole::ContentChecker)->create();
        foreach (range(1, 2) as $i) {
            NicknameReport::query()->create(['reporter_id' => $this->student("R{$i}")->id, 'reported_user_id' => $bad->id, 'nickname' => 'Badname', 'reason' => 'rude']);
        }

        $count = app(NicknameModeration::class)->reset($bad, $checker);

        $this->assertSame(2, $count);
        $this->assertNull($bad->refresh()->nickname);
        $this->assertSame(2, NicknameReport::query()->where('status', NicknameReportStatus::Reset->value)->whereNotNull('resolved_at')->where('resolved_by', $checker->id)->count());
        $log = AuditLog::query()->where('action', 'nickname.reset')->sole();
        // MySQL stores JSON keys in its own order, so compare sorted.
        $data = (array) $log->data;
        ksort($data);
        $this->assertSame(['new' => null, 'old' => 'Badname', 'reports' => 2], $data);

        Sanctum::actingAs($bad);
        $this->patchJson('/api/v1/me', ['nickname' => 'badname'])->assertStatus(422)->assertJsonValidationErrors('nickname');
        $this->patchJson('/api/v1/me', ['nickname' => 'Goodname'])->assertOk();
    }

    public function test_dismiss_keeps_the_nickname(): void
    {
        $user = $this->student('Fine');
        $checker = User::factory()->staff(UserRole::ContentChecker)->create();
        $report = NicknameReport::query()->create(['reporter_id' => $this->student('R')->id, 'reported_user_id' => $user->id, 'nickname' => 'Fine', 'reason' => 'other']);

        app(NicknameModeration::class)->dismiss($report, $checker);

        $this->assertSame('Fine', $user->refresh()->nickname);
        $this->assertSame(NicknameReportStatus::Dismissed, $report->refresh()->status);
        $this->assertSame(1, AuditLog::query()->where('action', 'nickname_report.dismissed')->count());
    }

    public function test_a_blocked_student_leaves_the_leaderboard_at_once_and_comes_back(): void
    {
        $viewer = $this->student('Ayan');
        $bad = $this->student('Badname');
        $this->studied($viewer);
        $this->studied($bad, 9);
        $this->assertContains('Badname', array_column($this->leaderboardAs($viewer), 'nickname'));

        $admin = User::factory()->staff(UserRole::SuperAdmin)->create();
        app(NicknameModeration::class)->block($bad, $admin);

        $this->assertNotNull($bad->refresh()->leaderboard_blocked_at);
        $this->assertNotContains('Badname', array_column($this->leaderboardAs($viewer), 'nickname'));

        app(NicknameModeration::class)->unblock($bad, $admin);
        $this->assertContains('Badname', array_column($this->leaderboardAs($viewer), 'nickname'));
        $this->assertSame(['leaderboard.blocked', 'leaderboard.unblocked'], AuditLog::query()->orderBy('id')->pluck('action')->all());
    }
}
