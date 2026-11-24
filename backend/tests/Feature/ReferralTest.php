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
            'min_answers' => 50,
            'max_per_month' => 4,
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

    public function test_the_friend_counts_after_50_real_answers_and_the_inviter_gets_premium(): void
    {
        $inviter = User::factory()->create();
        $friend = $this->invite($inviter);

        $this->answer($friend, 40);
        $this->answer($friend, 30, 100); // too fast: guessing does not count
        $this->assertSame(Referral::PENDING, Referral::query()->sole()->status);
        $this->assertFalse(app(PremiumService::class)->isPremium($inviter));

        $this->answer($friend, 10);
        $referral = Referral::query()->sole();
        $this->assertSame(Referral::VALID, $referral->status);
        $grant = PremiumGrant::query()->findOrFail($referral->premium_grant_id);
        $this->assertSame(PremiumPlan::Referral, $grant->plan);
        $this->assertSame($inviter->id, $grant->user_id);
        $this->assertSame(7, (int) $grant->starts_at->diffInDays($grant->ends_at));
        $this->assertTrue(app(PremiumService::class)->isPremium($inviter));
        $this->assertSame(['valid' => 1, 'rewarded_days' => 7], array_intersect_key($this->codeOfSummary($inviter), ['valid' => 0, 'rewarded_days' => 0]));

        // More answers later do not give a second reward.
        $this->answer($friend, 20);
        $this->assertSame(1, PremiumGrant::query()->count());
    }

    public function test_monthly_limit_and_premium_days_setting(): void
    {
        $inviter = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->answer($this->invite($inviter), 50);
        }
        $this->assertSame(5, Referral::query()->where('status', Referral::VALID)->count());
        $this->assertSame(4, Referral::query()->whereNotNull('premium_grant_id')->count());

        // A new month: rewards again. With 0 days set: valid but no Premium.
        Carbon::setTestNow(Carbon::parse('2026-12-02 08:00', 'UTC'));
        app(Settings::class)->set('referral_premium_days', 0);
        $this->answer($this->invite($inviter), 50);
        $this->assertSame(4, Referral::query()->whereNotNull('premium_grant_id')->count());
        app(Settings::class)->set('referral_premium_days', 7);
        $this->answer($this->invite($inviter), 50);
        $this->assertSame(5, Referral::query()->whereNotNull('premium_grant_id')->count());
    }

    public function test_a_friend_who_does_not_study_in_time_expires(): void
    {
        $inviter = User::factory()->create();
        $friend = $this->invite($inviter);
        Carbon::setTestNow(Carbon::now()->addDays(15));
        $this->answer($friend, 50);
        $this->assertSame(Referral::EXPIRED, Referral::query()->sole()->status);
        $this->assertSame(0, PremiumGrant::query()->count());
    }

    public function test_switched_off(): void
    {
        $inviter = User::factory()->create();
        $friend = $this->invite($inviter);
        app(FeatureSwitches::class)->set('referral', false);
        $this->getJson('/api/v1/referral')->assertForbidden()->assertJsonPath('code', 'feature_disabled');
        $this->answer($friend, 50);
        $this->assertSame(Referral::PENDING, Referral::query()->sole()->status, 'no reward while switched off');
    }
}
