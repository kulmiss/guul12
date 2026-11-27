<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Attempt;
use App\Models\User;
use App\Services\FeatureSwitches;
use App\Services\Leaderboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Wednesday 25 Nov 2026, 12:00 in Addis Ababa (09:00 UTC). The week started Monday 23 Nov.
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
            'region' => 'somali',
        ]);
    }

    private function attempt(User $user, int $correct, int $total, string $finished, string $mode = 'quick10'): void
    {
        // Stored in UTC, as AttemptService does.
        $at = Carbon::parse($finished, Leaderboard::TIMEZONE)->utc();
        Attempt::query()->create([
            'user_id' => $user->id,
            'client_uuid' => (string) Str::uuid(),
            'mode' => $mode,
            'started_at' => $at,
            'finished_at' => $at,
            'total' => $total,
            'correct' => $correct,
            'answered' => $total,
        ]);
    }

    public function test_week_starts_monday_midnight_in_addis_ababa(): void
    {
        $start = Leaderboard::weekStart(Carbon::now());
        $this->assertSame('2026-11-22 21:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $start->getTimezone()->getName());
        // Monday 00:30 Addis still belongs to that week; Sunday 23:59 to the week before.
        $this->assertSame('2026-11-22 21:00:00', Leaderboard::weekStart(Carbon::parse('2026-11-23 00:30', Leaderboard::TIMEZONE))->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-15 21:00:00', Leaderboard::weekStart(Carbon::parse('2026-11-22 23:59', Leaderboard::TIMEZONE))->format('Y-m-d H:i:s'));
    }

    public function test_points_match_the_app_and_only_this_week_counts(): void
    {
        $ayan = $this->student('Ayan');
        $this->attempt($ayan, 7, 10, '2026-11-23 00:00');           // 70 + 6 = 76
        $this->attempt($ayan, 30, 50, '2026-11-24 18:00', 'exam_fixed'); // 300 + 40 + 50 = 390
        $this->attempt($ayan, 10, 10, '2026-11-22 23:59');           // last week
        $this->attempt($ayan, 10, 10, '2026-11-25 12:01');           // in the future
        Sanctum::actingAs($ayan);

        $this->getJson('/api/v1/leaderboard')
            ->assertOk()
            ->assertJsonPath('week_start', '2026-11-23')
            ->assertJsonPath('me', ['has_nickname' => true, 'xp' => 466, 'rank' => 1])
            ->assertJsonPath('top.0.xp', 466)
            ->assertJsonPath('top.0.is_me', true);
    }

    public function test_only_nickname_and_avatar_are_shown_and_only_students_with_a_nickname(): void
    {
        $shown = $this->student('Hodan');
        $noNick = $this->student(null);
        $staff = $this->student('Admin');
        $staff->forceFill(['role' => UserRole::SuperAdmin])->save();
        $idle = $this->student('Idle');
        foreach ([$shown, $noNick, $staff] as $u) {
            $this->attempt($u, 5, 5, '2026-11-24 10:00');
        }
        Sanctum::actingAs($noNick);

        $json = $this->getJson('/api/v1/leaderboard')->assertOk()->json();
        $this->assertCount(1, $json['top']);
        // Exactly these fields; 'ref' is an encrypted reference for "Report nickname" (never an ID).
        $row = $json['top'][0];
        $this->assertIsString($row['ref']);
        unset($row['ref']);
        $this->assertSame(
            ['rank' => 1, 'nickname' => 'Hodan', 'avatar_style' => 'thumbs', 'avatar_seed' => 'a01', 'xp' => 50, 'is_me' => false],
            $row,
        );
        $this->assertSame(['rank', 'nickname', 'avatar_style', 'avatar_seed', 'xp', 'is_me', 'ref'], array_keys($json['top'][0]));
        $this->assertSame(['has_nickname' => false, 'xp' => 50, 'rank' => null], $json['me']);
        $this->assertStringNotContainsString('somali', json_encode($json) ?: '');
        $this->assertNotContains('Idle', array_column($json['top'], 'nickname'));
    }

    public function test_ties_share_a_rank_and_my_rank_shows_outside_the_top_50(): void
    {
        $users = [];
        for ($i = 0; $i < 52; $i++) {
            $users[$i] = $this->student('S'.$i);
            // S0 and S1 tie on 1000; then 990, 980, ...
            $this->attempt($users[$i], max(1, 100 - max(0, $i - 1)), max(1, 100 - max(0, $i - 1)), '2026-11-24 10:00');
        }
        Sanctum::actingAs($users[51]);

        $json = $this->getJson('/api/v1/leaderboard')->assertOk()->json();
        $this->assertCount(Leaderboard::TOP, $json['top']);
        $this->assertSame([1, 1, 3], array_column(array_slice($json['top'], 0, 3), 'rank'));
        $this->assertSame(['S0', 'S1'], array_column(array_slice($json['top'], 0, 2), 'nickname'));
        $this->assertSame(['has_nickname' => true, 'xp' => 500, 'rank' => 52], $json['me']);
    }

    public function test_my_points_are_live_even_when_the_list_is_cached(): void
    {
        $me = $this->student('Live');
        Sanctum::actingAs($me);
        $this->getJson('/api/v1/leaderboard')->assertJsonPath('me.xp', 0)->assertJsonPath('me.rank', null);
        $this->attempt($me, 1, 1, '2026-11-25 11:00');
        $this->getJson('/api/v1/leaderboard')->assertJsonPath('me.xp', 10)->assertJsonPath('me.rank', 1);
        Cache::flush();
        $this->getJson('/api/v1/leaderboard')->assertJsonPath('top.0.nickname', 'Live');
    }

    public function test_switched_off_and_needs_a_token(): void
    {
        $this->getJson('/api/v1/leaderboard')->assertUnauthorized();
        Sanctum::actingAs($this->student('X'));
        app(FeatureSwitches::class)->set('leaderboard', false);
        $this->getJson('/api/v1/leaderboard')->assertForbidden()->assertJsonPath('code', 'feature_disabled');
    }
}
