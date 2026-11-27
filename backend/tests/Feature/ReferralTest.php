<?php

namespace Tests\Feature;

use App\Enums\PremiumPlan;
use App\Enums\UserRole;
use App\Models\Chapter;
use App\Models\PremiumGrant;
use App\Models\Question;
use App\Models\Referral;
use App\Models\User;
use App\Services\FeatureSwitches;
use App\Services\PremiumService;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReferralTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<int> */
    private array $questionIds;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00', 'UTC'));
        $chapter = Chapter::factory()->create();
        $this->questionIds = array_values(array_map(
            fn (Question $q): int => $q->id,
            Question::factory()->count(60)->withOptions()->create(['chapter_id' => $chapter->id])->all(),
        ));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function codeOf(User $user): string
    {
        Sanctum::actingAs($user);

        return (string) $this->getJson('/api/v1/referral')->assertOk()->json('referral.code');
    }

    private function answer(User $user, int $count, int $msEach = 5000): void
    {
        Sanctum::actingAs($user);
        $answers = [];
        foreach (array_slice($this->questionIds, 0, $count) as $id) {
            $answers[] = ['question_id' => $id, 'version' => 1, 'response' => 'A', 'time_ms' => $msEach];
        }
        $this->postJson('/api/v1/attempts', [
            'client_uuid' => (string) Str::uuid(),
            'mode' => 'quick10',
            'started_at' => now()->subMinutes(30)->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'answers' => $answers,
        ])->assertCreated();
    }

    private function invite(User $inviter): User
    {
        $friend = User::factory()->create();
        $code = $this->codeOf($inviter);
        Sanctum::actingAs($friend);
        $this->postJson('/api/v1/referral', ['code' => $code])->assertCreated();

        return $friend;
    }

    public function test_code_is_made_once_and_is_not_the_student_id(): void
    {
        $ayan = User::factory()->create();
        $code = $this->codeOf($ayan);
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{6}$/', $code);
        $this->assertSame($code, $this->codeOf($ayan));
        $this->assertNotSame($ayan->student_id, $code);
        $this->getJson('/api/v1/referral')->assertJsonPath('referral', [
            'code' => $code,
            'pending' => 0,
            'valid' => 0,
            'rewarded_days' => 0,
            'can_enter_code' => true,
            'reward_days' => 7,
            'min_answers' => 20,
            'qualify_days' => 14,
            'max_per_month' => 2,
            'friends_per_reward' => 3,
            'toward_next' => 0,
            'waiting_rewards' => 0,
        ]);
    }

    public function test_entering_a_code_and_its_errors(): void
    {
        $inviter = User::factory()->create();
        $code = $this->codeOf($inviter);
        $this->postJson('/api/v1/referral', ['code' => $code])->assertStatus(409)->assertJsonPath('code', 'own_code');

        $friend = User::factory()->create();
        Sanctum::actingAs($friend);
        $this->postJson('/api/v1/referral', ['code' => 'ZZZZZZ'])->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->postJson('/api/v1/referral', ['code' => ' '.strtolower($code).' '])->assertCreated()
            ->assertJsonPath('referral.can_enter_code', false);
        $this->postJson('/api/v1/referral', ['code' => $code])->assertStatus(409)->assertJsonPath('code', 'already_invited');
        $this->assertSame(1, $this->codeOfSummary($inviter)['pending']);

        // Too late: more than 7 days after first opening the app.
        $old = User::factory()->create(['created_at' => Carbon::now()->subDays(8)]);
        Sanctum::actingAs($old);
        $this->getJson('/api/v1/referral')->assertJsonPath('referral.can_enter_code', false);
        $this->postJson('/api/v1/referral', ['code' => $code])->assertStatus(409)->assertJsonPath('code', 'too_late');

        // Staff codes do not work.
        $staff = User::factory()->create();
        $staffCode = $this->codeOf($staff);
        $staff->forceFill(['role' => UserRole::SuperAdmin])->save();
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/referral', ['code' => $staffCode])->assertNotFound();
    }

    /**
     * @return array<string, mixed>
     */
    private function codeOfSummary(User $user): array
    {
        Sanctum::actingAs($user);

        return $this->getJson('/api/v1/referral')->json('referral');
    }

    /**
     * @return list<User>
     */
    private function inviteMany(User $inviter, int $count, int $answers = 20): array
    {
        $friends = [];
        for ($i = 0; $i < $count; $i++) {
            $friend = $this->invite($inviter);
            $this->answer($friend, $answers);
            $friends[] = $friend;
        }

        return $friends;
    }

    public function test_three_friends_who_answer_20_real_questions_give_7_premium_days(): void
    {
        $inviter = User::factory()->create();

        // Friend 1: 15 real answers + 10 too fast (guessing does not count) = still pending.
        $first = $this->invite($inviter);
        $this->answer($first, 15);
        $this->answer($first, 25, 100);
        $this->assertSame(Referral::PENDING, Referral::query()->sole()->status);
        $this->answer($first, 20);
        $this->assertSame(Referral::VALID, Referral::query()->sole()->status);

        // One or two counted friends: no reward yet, the app shows the progress.
        $this->inviteMany($inviter, 1);
        $this->assertSame(0, PremiumGrant::query()->count());
        $this->assertFalse(app(PremiumService::class)->isPremium($inviter));
        $this->assertSame(['valid' => 2, 'rewarded_days' => 0, 'toward_next' => 2], array_intersect_key(
            $this->codeOfSummary($inviter), ['valid' => 0, 'rewarded_days' => 0, 'toward_next' => 0]));

        // The third friend: 7 Premium days, one grant shared by the three referrals.
        $this->inviteMany($inviter, 1);
        $grant = PremiumGrant::query()->sole();
        $this->assertSame(PremiumPlan::Referral, $grant->plan);
        $this->assertSame($inviter->id, $grant->user_id);
        $this->assertSame(7, (int) $grant->starts_at->diffInDays($grant->ends_at));
        $this->assertSame(3, Referral::query()->where('premium_grant_id', $grant->id)->count());
        $this->assertTrue(app(PremiumService::class)->isPremium($inviter));
        $this->assertSame(['valid' => 3, 'rewarded_days' => 7, 'toward_next' => 0], array_intersect_key(
            $this->codeOfSummary($inviter), ['valid' => 0, 'rewarded_days' => 0, 'toward_next' => 0]));

        // More answers later never give a second reward.
        $this->answer($first, 20);
        $this->assertSame(1, PremiumGrant::query()->count());
    }

    public function test_the_same_question_answered_again_counts_once(): void
    {
        $inviter = User::factory()->create();
        $friend = $this->invite($inviter);
        // 40 answers, but only 10 different questions.
        for ($i = 0; $i < 4; $i++) {
            $this->answer($friend, 10);
        }
        $this->assertSame(Referral::PENDING, Referral::query()->sole()->status);
        $this->answer($friend, 20);
        $this->assertSame(Referral::VALID, Referral::query()->sole()->status);
    }

    public function test_at_most_two_rewards_a_month_and_waiting_friends_carry_over(): void
    {
        $inviter = User::factory()->create();
        $this->inviteMany($inviter, 8);
        $this->assertSame(8, Referral::query()->where('status', Referral::VALID)->count());
        $this->assertSame(2, PremiumGrant::query()->count(), '2 rewards a month');
        $this->assertSame(6, Referral::query()->whereNotNull('premium_grant_id')->count());
        $this->assertSame(['toward_next' => 2, 'waiting_rewards' => 0], array_intersect_key(
            $this->codeOfSummary($inviter), ['toward_next' => 0, 'waiting_rewards' => 0]));
        $this->inviteMany($inviter, 1);
        $this->assertSame(2, PremiumGrant::query()->count(), 'still 2: the third group waits');
        $this->assertSame(['toward_next' => 0, 'waiting_rewards' => 1], array_intersect_key(
            $this->codeOfSummary($inviter), ['toward_next' => 0, 'waiting_rewards' => 0]));

        // A new month (Addis Ababa time): the next friend who counts releases the waiting group.
        Carbon::setTestNow(Carbon::parse('2026-11-30 21:30', 'UTC')); // 1 December 00:30 in Addis Ababa
        $this->inviteMany($inviter, 1);
        $this->assertSame(3, PremiumGrant::query()->count());
        $this->assertSame(1, Referral::query()->where('status', Referral::VALID)->whereNull('premium_grant_id')->count());
        $this->inviteMany($inviter, 1);

        // With 0 days set: friends still count, but no Premium.
        app(Settings::class)->set('referral_premium_days', 0);
        $this->inviteMany($inviter, 3);
        $this->assertSame(3, PremiumGrant::query()->count());
        app(Settings::class)->set('referral_premium_days', 7);
        $this->inviteMany($inviter, 1);
        $this->assertSame(4, PremiumGrant::query()->count(), 'the waiting friends are rewarded when it is on again');
    }

    public function test_a_friend_who_does_not_study_in_time_expires(): void
    {
        $inviter = User::factory()->create();
        $friend = $this->invite($inviter);
        Carbon::setTestNow(Carbon::now()->addDays(15));
        $this->answer($friend, 20);
        $this->assertSame(Referral::EXPIRED, Referral::query()->sole()->status);
        $this->assertSame(0, PremiumGrant::query()->count());
    }

    public function test_switched_off(): void
    {
        $inviter = User::factory()->create();
        $friend = $this->invite($inviter);
        app(FeatureSwitches::class)->set('referral', false);
        $this->getJson('/api/v1/referral')->assertForbidden()->assertJsonPath('code', 'feature_disabled');
        $this->answer($friend, 20);
        $this->assertSame(Referral::PENDING, Referral::query()->sole()->status, 'no reward while switched off');
    }
}
