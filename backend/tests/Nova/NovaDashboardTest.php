<?php

namespace Tests\Nova;

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\AiExplanation;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Models\QuestionReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The staff dashboard: which cards each role sees and that the numbers are right.
 * Runs only where the licensed Nova package is installed (php artisan test --testsuite=Nova).
 */
class NovaDashboardTest extends TestCase
{
    use RefreshDatabase;

    private const METRICS = '/nova-api/dashboards/cards/main/metrics/';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-23 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return list<string|null> */
    private function cardsFor(UserRole $role, string $dashboard = 'main'): array
    {
        $staff = User::factory()->staff($role)->create();
        $cards = $this->actingAs($staff)->getJson("/nova-api/dashboards/cards/{$dashboard}")->assertOk()->json();
        $this->assertIsArray($cards);

        // The "Find student by ID" card is not a metric and has no address (null).
        return array_values(array_map(fn (array $card): ?string => isset($card['uriKey']) ? (string) $card['uriKey'] : null, $cards));
    }

    /** The JSON of one dashboard metric, as the super admin sees it. */
    private function metric(string $metric, string $query = ''): mixed
    {
        $admin = User::factory()->staff(UserRole::SuperAdmin)->create();

        return $this->actingAs($admin)->getJson(self::METRICS.$metric.$query)->assertOk()->json('value');
    }

    private function value(string $metric, string $query = ''): mixed
    {
        $json = $this->metric($metric, $query);
        $this->assertIsArray($json);

        return $json['value'];
    }

    public function test_each_role_sees_only_its_cards(): void
    {
        $everyone = ['new-students', 'active-students', 'answers-given'];
        $charts = ['new-students-per-day', 'answering-students-per-day', 'answers-per-day', 'study-minutes-per-day',
            'students-by-grade', 'students-by-stream', 'students-by-region', 'students-by-gender', 'top-students-week'];

        $this->assertSame(
            [...$everyone, 'premium-active', 'payments-waiting', 'open-reports', 'open-nickname-reports', 'battles-played', 'ai-spend', 'system-health', ...$charts],
            $this->cardsFor(UserRole::SuperAdmin),
        );
        $this->assertSame([...$everyone, 'premium-active', 'payments-waiting', ...$charts], $this->cardsFor(UserRole::PaymentsHelper));
        $this->assertSame([...$everyone, 'open-reports', 'open-nickname-reports', 'battles-played', ...$charts], $this->cardsFor(UserRole::ContentChecker));
    }

    public function test_role_dashboards(): void
    {
        $payments = ['payments-waiting', 'premium-expiring', null, 'premium-sold', 'premium-active', 'referrals-rewarded', 'premium-sold-per-week', 'premium-vs-free', 'latest-payments'];
        $this->assertSame($payments, $this->cardsFor(UserRole::PaymentsHelper, 'payments'));
        $this->assertSame($payments, $this->cardsFor(UserRole::SuperAdmin, 'payments'));
        $content = ['open-reports', 'open-nickname-reports', 'beta-near-publishing', 'questions-published', 'questions-beta',
            'questions-by-status', 'most-reported-questions', 'hardest-questions', 'weakest-chapters', 'latest-reports'];
        $this->assertSame($content, $this->cardsFor(UserRole::ContentChecker, 'content'));

        // Another role's dashboard lists no cards, and no card can be read by address (never an error 500).
        $checker = User::factory()->staff(UserRole::ContentChecker)->create();
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        $this->assertSame([], $this->cardsFor(UserRole::ContentChecker, 'payments'));
        $this->assertSame([], $this->cardsFor(UserRole::PaymentsHelper, 'content'));
        foreach (['payments' => 'premium-sold', 'main' => 'premium-sold'] as $dashboard => $metric) {
            $this->assertContains($this->actingAs($checker)->getJson("/nova-api/dashboards/cards/{$dashboard}/metrics/{$metric}")->status(), [403, 404]);
        }
        $this->actingAs($helper)->getJson('/nova-api/dashboards/cards/payments/metrics/premium-sold')->assertOk();

        // The "Find student by ID" card is drawn by our script, which Nova serves.
        $cards = $this->actingAs($helper)->getJson('/nova-api/dashboards/cards/payments')->json();
        $this->assertIsArray($cards);
        $find = collect($cards)->firstWhere('component', 'guul-find-student');
        $this->assertNotNull($find);
        $this->actingAs($helper)->get('/nova-api/scripts/guul-nova')->assertOk()->assertSee("app.component('guul-find-student'", false);
    }

    public function test_cards_show_numbers_from_the_cache_with_the_period_before(): void
    {
        // Two students joined this week, one in the week before.
        foreach (['2026-11-23 10:00', '2026-11-20 09:00', '2026-11-15 09:00'] as $when) {
            User::factory()->create()->forceFill(['created_at' => Carbon::parse($when, 'Africa/Addis_Ababa')->utc()])->save();
        }
        $json = $this->metric('new-students', '?range=7');
        $this->assertIsArray($json);
        $this->assertSame([2, 1], [$json['value'], $json['previous']]);
        $this->assertSame([['label' => 'Today', 'value' => 1], ['label' => '7 days', 'value' => 7], ['label' => '30 days', 'value' => 30],
            ['label' => '90 days', 'value' => 90], ['label' => 'This term', 'value' => 'TERM'], ['label' => 'All time', 'value' => 'ALL']],
            $this->cardRanges('new-students'));

        // A trend card returns a point per day and the total.
        $trend = $this->metric('new-students-per-day', '?range=7');
        $this->assertIsArray($trend);
        $this->assertSame(2, $trend['value']);
        $this->assertCount(7, $trend['trend']);
        $this->assertSame(1, $trend['trend']['Nov 23']);
    }

    /** @return list<array{label: string, value: int|string}> */
    private function cardRanges(string $uriKey): array
    {
        $admin = User::factory()->staff(UserRole::SuperAdmin)->create();
        $cards = $this->actingAs($admin)->getJson('/nova-api/dashboards/cards/main')->json();
        $this->assertIsArray($cards);
        $card = collect($cards)->firstWhere('uriKey', $uriKey);
        $this->assertIsArray($card);

        return array_values($card['ranges']);
    }

    public function test_helpers_cannot_read_admin_metrics_directly(): void
    {
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        foreach (['ai-spend', 'system-health', 'open-reports'] as $metric) {
            $this->actingAs($helper)->getJson(self::METRICS.$metric)->assertNotFound();
        }
        $this->actingAs($helper)->getJson(self::METRICS.'premium-active')->assertOk();
    }

    public function test_active_students_counts_students_seen_in_the_range(): void
    {
        User::factory()->count(2)->create(['last_active_at' => now()->subHours(3)]);
        User::factory()->create(['last_active_at' => now()->subDays(10)]);
        User::factory()->create(['last_active_at' => null]);
        User::factory()->staff(UserRole::ContentChecker)->create(['last_active_at' => now()]);

        $this->assertSame(2, $this->value('active-students', '?range=7'));
        $this->assertSame(3, $this->value('active-students', '?range=30'));
    }

    public function test_answers_given_counts_answers_in_the_range(): void
    {
        $question = Question::factory()->create()->refresh();
        $student = User::factory()->create();
        foreach ([now()->subHour(), now()->subHours(2), now()->subDays(20)] as $when) {
            $attempt = Attempt::query()->create([
                'user_id' => $student->id, 'client_uuid' => (string) Str::uuid(), 'mode' => 'quick10',
                'chapter_id' => $question->chapter_id, 'started_at' => $when, 'finished_at' => $when,
                'total' => 1, 'correct' => 1,
            ]);
            $answer = AttemptAnswer::query()->create([
                'attempt_id' => $attempt->id, 'question_id' => $question->id, 'question_version' => $question->version,
                'response' => 'B', 'is_correct' => true, 'time_ms' => 5000,
            ]);
            $answer->forceFill(['created_at' => $when])->save();
        }

        $this->assertSame(2, $this->value('answers-given', '?range=1'));
        $this->assertSame(3, $this->value('answers-given', '?range=30'));
    }

    public function test_open_reports_counts_only_open_ones(): void
    {
        $question = Question::factory()->create()->refresh();
        foreach ([ReportStatus::Open, ReportStatus::Open, ReportStatus::Accepted, ReportStatus::Rejected] as $status) {
            QuestionReport::query()->create([  // one report per student and question version
                'question_id' => $question->id, 'user_id' => User::factory()->create()->id, 'question_version' => $question->version,
                'reason' => 'wrong_answer', 'status' => $status,
            ]);
        }

        $this->assertSame(2, $this->value('open-reports'));
    }

    public function test_ai_spend_shows_this_month_in_dollars(): void
    {
        $question = Question::factory()->create()->refresh();
        foreach ([[2_500_000, now()], [700_000, now()->subDays(2)], [9_000_000, now()->subMonth()]] as [$cost, $when]) {
            $row = AiExplanation::query()->create([
                'question_id' => $question->id, 'question_version' => $question->version, 'body' => 'Because…',
                'model' => 'claude-haiku-5-5', 'input_tokens' => 100, 'output_tokens' => 100, 'cost_micro_usd' => $cost,
            ]);
            $row->forceFill(['created_at' => $when])->save();
        }

        $json = $this->metric('ai-spend');
        $this->assertIsArray($json);
        $this->assertEqualsWithDelta(3.2, $json['value'], 0.0001);
        $this->assertSame('$', $json['prefix']);
        $this->assertSame('of $20 this month', $json['suffix']);
    }

    public function test_system_health_lists_problems_or_all_good(): void
    {
        config(['guul.backups.required' => false, 'guul.watchdog.min_free_disk_percent' => 0]);

        $rows = $this->metric('system-health');
        $this->assertIsArray($rows);
        $this->assertSame(['All good'], array_column($rows, 'title'));

        config(['guul.backups.required' => true, 'guul.backups.path' => storage_path('framework/testing/no-backups-'.Str::random(8))]);
        $rows = $this->metric('system-health');
        $this->assertIsArray($rows);
        $this->assertSame(['There is no database backup yet.'], array_column($rows, 'title'));
    }
}
