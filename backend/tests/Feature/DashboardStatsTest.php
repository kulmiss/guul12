<?php

namespace Tests\Feature;

use App\Enums\PaymentRequestStatus;
use App\Enums\PremiumPlan;
use App\Enums\QuestionStatus;
use App\Enums\UserRole;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Battle;
use App\Models\Chapter;
use App\Models\NicknameReport;
use App\Models\PaymentRequest;
use App\Models\PremiumGrant;
use App\Models\Question;
use App\Models\Referral;
use App\Models\User;
use App\Services\Settings;
use App\Services\Stats\DashboardStats;
use App\Services\Stats\StatsRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Dashboard numbers from seeded data with known answers. "Now" is Wed 25 Nov 2026, 12:00 Addis Ababa.
 */
class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    private DashboardStats $stats;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-25 12:00', StatsRange::TIMEZONE));
        $this->stats = app(DashboardStats::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $local): Carbon
    {
        return Carbon::parse($local, StatsRange::TIMEZONE)->utc();
    }

    /** @param  array<string, mixed>  $extra */
    private function student(string $joined, array $extra = []): User
    {
        $user = User::factory()->create($extra);
        $user->forceFill(['created_at' => $this->at($joined)])->save();

        return $user;
    }

    private function local(?Carbon $time, string $format = 'Y-m-d'): string
    {
        $this->assertNotNull($time);

        return $time->copy()->setTimezone(StatsRange::TIMEZONE)->format($format);
    }

    public function test_ranges_follow_addis_ababa_days_and_compare_with_the_period_before(): void
    {
        $seven = StatsRange::make('7');
        $this->assertSame('2026-11-19 00:00', $this->local($seven->start, 'Y-m-d H:i'));
        $this->assertSame(7, $seven->days());
        $this->assertSame('2026-11-12 12:00', $this->local($seven->previousStart, 'Y-m-d H:i'));
        $this->assertNull(StatsRange::make('ALL')->start);
        $this->assertSame('30', StatsRange::make('nonsense')->key);

        // This term: 11 September by default, or the date in the settings.
        $this->assertSame('2026-09-11', $this->local(StatsRange::make('TERM')->start));
        app(Settings::class)->set('term_starts_on', '2026-10-01');
        $this->assertSame('2026-10-01', $this->local(StatsRange::make('TERM')->start));
        // An old setting is ignored once a newer term has started (1 Feb 2027).
        $this->assertSame('2027-02-01', StatsRange::termStart(Carbon::parse('2027-05-10', StatsRange::TIMEZONE))->toDateString());
        // A setting in the future does not count yet.
        app(Settings::class)->set('term_starts_on', '2026-12-01');
        $this->assertSame('2026-09-11', $this->local(StatsRange::make('TERM')->start));
    }

    public function test_new_students_count_only_students_in_the_range_with_the_period_before(): void
    {
        $this->student('2026-11-25 08:00');
        $this->student('2026-11-19 00:30');
        $this->student('2026-11-18 23:30'); // yesterday of the 7-day range: previous period
        $this->student('2026-11-13 10:00'); // previous period (12 Nov 12:00 to 19 Nov 00:00)
        $this->student('2026-11-01 10:00'); // older
        User::factory()->staff(UserRole::ContentChecker)->create();

        $this->assertSame(['value' => 2, 'previous' => 2], $this->stats->newStudents(StatsRange::make('7')));
        $this->assertSame(5, $this->stats->newStudents(StatsRange::make('ALL'))['value']);
        $this->assertNull($this->stats->newStudents(StatsRange::make('ALL'))['previous']);
        $days = $this->stats->newStudentsPerDay(StatsRange::make('7'));
        $this->assertSame(['2026-11-19' => 1, '2026-11-20' => 0, '2026-11-21' => 0, '2026-11-22' => 0, '2026-11-23' => 0, '2026-11-24' => 0, '2026-11-25' => 1], $days);
    }

    public function test_premium_sold_active_expiring_and_waiting(): void
    {
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();
        $grant = fn (User $u, string $plan, string $start, string $end, string $made, bool $undone = false) => PremiumGrant::query()->create([
            'user_id' => $u->id, 'plan' => $plan, 'starts_at' => $this->at($start), 'ends_at' => $this->at($end),
            'payment_ref' => Str::random(6), 'granted_by' => $helper->id,
        ])->forceFill(['created_at' => $this->at($made), 'revoked_at' => $undone ? now() : null])->save();

        $grant($a, 'term', '2026-11-20', '2026-11-29', '2026-11-20');  // active, ends in 4 days
        $grant($b, 'year', '2026-11-01', '2027-11-01', '2026-11-24');  // active, far away
        $grant($c, 'term', '2026-11-22', '2026-11-30', '2026-11-22', undone: true); // undone: never counts
        $grant($c, 'referral', '2026-11-23', '2026-11-30', '2026-11-23'); // reward, not sold

        $this->assertSame(['value' => 2, 'previous' => 0], $this->stats->premiumSold(StatsRange::make('7')));
        $this->assertSame(3, $this->stats->premiumActiveNow());
        $this->assertSame(2, $this->stats->premiumExpiringSoon(7), 'a (29 Nov) and c (referral, 30 Nov)');
        $this->assertSame(['Premium' => 3, 'Free' => 0], $this->stats->premiumVsFree());

        PaymentRequest::query()->create(['user_id' => $a->id, 'plan' => PremiumPlan::Term, 'payment_ref' => 'W1']);
        PaymentRequest::query()->create(['user_id' => $a->id, 'plan' => PremiumPlan::Term, 'payment_ref' => 'W2', 'status' => PaymentRequestStatus::Rejected]);
        $this->assertSame(1, $this->stats->paymentsWaiting());
        $this->assertSame(['W2', 'W1'], array_column($this->stats->latestPayments(), 'payment_ref'));
    }

    public function test_answers_minutes_and_answering_students_per_day(): void
    {
        $chapter = Chapter::factory()->create();
        $q = Question::factory()->create(['chapter_id' => $chapter->id]);
        $s1 = User::factory()->create();
        $s2 = User::factory()->create();
        $attempt = function (User $u, string $when, array $times) use ($q): void {
            $a = Attempt::query()->create([
                'user_id' => $u->id, 'client_uuid' => (string) Str::uuid(), 'mode' => 'quick10',
                'started_at' => $this->at($when), 'finished_at' => $this->at($when), 'total' => count($times), 'correct' => 0,
            ]);
            foreach ($times as $ms) {
                AttemptAnswer::query()->create(['attempt_id' => $a->id, 'question_id' => $q->id, 'question_version' => 1, 'response' => 'A', 'is_correct' => false, 'time_ms' => $ms])
                    ->forceFill(['created_at' => $this->at($when)])->save();
            }
        };
        $attempt($s1, '2026-11-25 01:00', [60_000, 60_000, 30_000]); // 2.5 min on 25 Nov (local)
        $attempt($s2, '2026-11-25 02:30', [120_000]);
        $attempt($s1, '2026-11-24 23:59', [90_000]);                  // late on 24 Nov, local time

        $range = StatsRange::make('7');
        $this->assertSame(['value' => 5, 'previous' => 0], $this->stats->answersGiven($range));
        $this->assertSame(1, $this->stats->answersPerDay($range)['2026-11-24']);
        $this->assertSame(4, $this->stats->answersPerDay($range)['2026-11-25']);
        $this->assertSame(['2026-11-24' => 1, '2026-11-25' => 4], array_slice($this->stats->studyMinutesPerDay($range), -2, 2, true));
        $this->assertSame(2, $this->stats->answeringStudentsPerDay($range)['2026-11-25']);
    }

    public function test_partitions_include_not_given(): void
    {
        User::factory()->create(['grade' => 12, 'stream' => 'natural', 'region' => 'somali', 'gender' => 'female']);
        User::factory()->create(['grade' => 12, 'stream' => 'social', 'region' => 'somali', 'gender' => null]);
        User::factory()->create(['grade' => 11, 'stream' => null, 'region' => null, 'gender' => 'male']);
        User::factory()->staff(UserRole::SuperAdmin)->create(['grade' => 9]);

        $this->assertSame(['Grade 12' => 2, 'Grade 11' => 1], $this->stats->studentsBy('grade'));
        $this->assertSame(['Somali' => 2, 'Not given' => 1], $this->stats->studentsBy('region'));
        $gender = $this->stats->studentsBy('gender');
        ksort($gender);
        $this->assertSame(['Female' => 1, 'Male' => 1, 'Not given' => 1], $gender);
    }

    public function test_questions_hardest_easiest_weakest_and_most_reported(): void
    {
        $easyChapter = Chapter::factory()->create(['title' => 'Easy chapter']);
        $hardChapter = Chapter::factory()->create(['title' => 'Hard chapter']);
        $make = fn (Chapter $c, int $answered, int $right, int $reports = 0, string $status = 'published') => Question::factory()->create([
            'chapter_id' => $c->id, 'times_answered' => $answered, 'times_correct' => $right, 'reports_count' => $reports, 'status' => $status,
        ]);
        $hard = $make($hardChapter, 40, 4);
        $make($hardChapter, 30, 15, 2);
        $easy = $make($easyChapter, 50, 48);
        $make($easyChapter, 10, 0, 5, 'beta'); // too few answers to be "hardest"

        $this->assertSame([$hard->id], array_slice(array_column($this->stats->hardestQuestions(), 'id'), 0, 1));
        $this->assertSame(10.0, $this->stats->hardestQuestions()[0]['percent']);
        $this->assertSame($easy->id, $this->stats->easiestQuestions()[0]['id']);
        $this->assertSame(['Hard chapter', 'Easy chapter'], array_column($this->stats->weakestChapters(), 'title'));
        $this->assertSame([5, 2], array_column($this->stats->mostReportedQuestions(), 'reports'));
        $this->assertSame(['Beta' => 1, 'Published' => 3], $this->stats->questionsByStatus());
        $this->assertSame(3, $this->stats->questionsWithStatus(QuestionStatus::Published));
    }

    public function test_battles_referrals_and_reports(): void
    {
        $a = User::factory()->create(['nickname' => 'A']);
        $b = User::factory()->create(['nickname' => 'B']);
        Battle::query()->create(['code' => 'AAAAAA', 'key' => 'g12', 'is_random' => true, 'question_ids' => [1], 'creator_id' => $a->id, 'opponent_id' => $b->id, 'expires_at' => now()->addDay()]);
        Battle::query()->create(['code' => 'BBBBBB', 'key' => 'g12', 'is_random' => true, 'question_ids' => [1], 'creator_id' => $a->id, 'expires_at' => now()->addDay()]);
        Referral::query()->create(['inviter_id' => $a->id, 'invitee_id' => $b->id, 'status' => Referral::VALID, 'valid_at' => now()->subDay()]);
        NicknameReport::query()->create(['reporter_id' => $a->id, 'reported_user_id' => $b->id, 'nickname' => 'B', 'reason' => 'rude']);

        $this->assertSame(1, $this->stats->battlesPlayed(StatsRange::make('7'))['value']);
        $this->assertSame(1, $this->stats->referralsRewarded(StatsRange::make('30'))['value']);
        $this->assertSame(1, $this->stats->openNicknameReports());
        $this->assertSame(['nickname'], array_column($this->stats->latestReports(), 'type'));
    }
}
