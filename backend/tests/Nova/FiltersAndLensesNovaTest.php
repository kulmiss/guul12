<?php

namespace Tests\Nova;

use App\Enums\PremiumPlan;
use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Enums\UserRole;
use App\Models\Attempt;
use App\Models\AuditLog;
use App\Models\Battle;
use App\Models\Chapter;
use App\Models\NicknameReport;
use App\Models\Question;
use App\Models\QuestionReport;
use App\Models\Referral;
use App\Models\Subject;
use App\Models\Unit;
use App\Models\User;
use App\Services\PaymentRequests;
use App\Services\PremiumService;
use App\Services\Quality\QualityGate;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Filters and lenses on the Nova lists: each one returns the right rows, follows the roles, and the
 * staff member's last filter is remembered by our script.
 * Runs only where the licensed Nova package is installed (php artisan test --testsuite=Nova).
 */
class FiltersAndLensesNovaTest extends TestCase
{
    use RefreshDatabase;
    use ResetsNovaPageData;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        // Monday 23 Nov 2026, 10:00 in Addis Ababa.
        Carbon::setTestNow(Carbon::parse('2026-11-23 10:00:00', 'Africa/Addis_Ababa'));
        $this->admin = User::factory()->staff(UserRole::SuperAdmin)->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Ids in the list after applying one filter.
     *
     * @return list<int>
     */
    private function filtered(string $uri, string $key, string $value, ?User $as = null): array
    {
        $filters = base64_encode((string) json_encode([[$key => $value]]));
        $rows = $this->actingAs($as ?? $this->admin)->getJson("/nova-api/{$uri}?filters={$filters}&perPage=100")->assertOk()->json('resources');
        $this->assertIsArray($rows);
        $ids = array_map(fn (array $row): int => (int) $row['id']['value'], $rows);
        sort($ids);

        return $ids;
    }

    /** @return list<int> ids shown by a lens */
    private function lens(string $uri, string $lens, ?User $as = null): array
    {
        $rows = $this->actingAs($as ?? $this->admin)->getJson("/nova-api/{$uri}/lens/{$lens}?perPage=100")->assertOk()->json('resources');
        $this->assertIsArray($rows);

        return array_values(array_map(fn (array $row): int => (int) $row['id']['value'], $rows));
    }

    /** @return array<string, mixed> one field of the first row, as Nova sends it */
    private function firstRowField(string $uri, string $name): array
    {
        $fields = $this->actingAs($this->admin)->getJson($uri)->assertOk()->json('resources.0.fields');
        $this->assertIsArray($fields);
        foreach ($fields as $field) {
            if (is_array($field) && ($field['name'] ?? null) === $name) {
                return $field;
            }
        }
        $this->fail("No field {$name} on {$uri}");
    }

    /** @return list<string> filter keys offered on a list */
    private function filterKeys(string $uri, ?User $as = null): array
    {
        $filters = $this->actingAs($as ?? $this->admin)->getJson("/nova-api/{$uri}/filters")->assertOk()->json();
        $this->assertIsArray($filters);

        return array_column($filters, 'class');
    }

    /** @param  array<string, mixed>  $attributes */
    private function student(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    private function answers(User $user, int $count, string $when): void
    {
        Attempt::query()->create([
            'user_id' => $user->id, 'client_uuid' => (string) Str::uuid(), 'mode' => 'quick10',
            'started_at' => Carbon::parse($when)->subMinutes(5), 'finished_at' => Carbon::parse($when), 'total' => $count, 'correct' => 0,
        ]);
    }

    /** @return list<int> */
    private function sortedIds(User|Question|Chapter|int ...$items): array
    {
        $ids = array_map(fn ($i): int => is_int($i) ? $i : (int) $i->id, $items);
        sort($ids);

        return $ids;
    }

    // ---- filters --------------------------------------------------------------------------------

    public function test_student_filters(): void
    {
        $nine = $this->student(['grade' => 9, 'stream' => null, 'region' => 'somali']);
        $twelve = $this->student(['grade' => 12, 'stream' => 'natural', 'region' => 'addis_ababa']);
        $premium = $this->student(['grade' => 12, 'stream' => 'social', 'region' => 'somali']);
        app(PremiumService::class)->grant($premium, PremiumPlan::Term, $this->admin, 'TX-F1');
        $blocked = $this->student(['grade' => 11, 'stream' => null]);
        $blocked->forceFill(['leaderboard_blocked_at' => now()])->save();
        $old = $this->student(['grade' => 10, 'stream' => null]);
        $old->forceFill(['created_at' => now()->subDays(40), 'last_active_at' => now()->subDays(20)])->save();
        $nine->forceFill(['last_active_at' => now()->subHour()])->save();

        $this->assertSame($this->sortedIds($nine), $this->filtered('students', 'student-grade', '9'));
        $this->assertSame($this->sortedIds($twelve, $premium), $this->filtered('students', 'student-grade', '12'));
        $this->assertSame($this->sortedIds($twelve), $this->filtered('students', 'student-stream', 'natural'));
        $this->assertSame($this->sortedIds($nine, $premium), $this->filtered('students', 'student-region', 'somali'));
        $this->assertSame($this->sortedIds($premium), $this->filtered('students', 'student-plan', 'premium'));
        $this->assertNotContains($premium->id, $this->filtered('students', 'student-plan', 'free'));
        $this->assertContains($nine->id, $this->filtered('students', 'student-plan', 'free'));
        $this->assertSame($this->sortedIds($blocked), $this->filtered('students', 'student-leaderboard', 'blocked'));
        $this->assertNotContains($old->id, $this->filtered('students', 'student-joined', '30'));
        $this->assertContains($nine->id, $this->filtered('students', 'student-joined', '1'));
        $this->assertSame($this->sortedIds($nine), $this->filtered('students', 'student-active', '1'));
        $this->assertSame($this->sortedIds($old), $this->filtered('students', 'student-active', 'inactive14'));

        // Region choices come from the data, shown in easy words; unknown values change nothing.
        $filters = $this->actingAs($this->admin)->getJson('/nova-api/students/filters')->json();
        $this->assertIsArray($filters);
        $region = array_values(array_filter($filters, fn (mixed $f): bool => is_array($f) && $f['class'] === 'student-region'));
        $this->assertCount(1, $region);
        $this->assertSame(['Addis ababa', 'Somali'], array_column($region[0]['options'], 'label'));
        $this->assertCount(5, $this->filtered('students', 'student-grade', 'nonsense'));
    }

    public function test_question_filters(): void
    {
        $bio = Subject::factory()->create(['name' => 'Biology']);
        $unit9 = Unit::query()->create(['subject_id' => $bio->id, 'grade' => 9, 'number' => 1, 'title' => 'Cells']);
        $chapter9 = Chapter::query()->create(['unit_id' => $unit9->id, 'number' => 1, 'title' => 'Cell parts']);
        $draft = Question::factory()->create(['status' => QuestionStatus::Draft, 'chapter_id' => $chapter9->id, 'difficulty' => 5]);
        $numeric = Question::factory()->create(['type' => QuestionType::Numeric, 'answer_key' => ['value' => 2]]);
        $reported = Question::factory()->create();
        QuestionReport::query()->create(['question_id' => $reported->id, 'user_id' => $this->student()->id, 'question_version' => 1, 'reason' => 'typo']);

        $this->assertSame($this->sortedIds($draft), $this->filtered('questions', 'question-status', 'draft'));
        $this->assertSame($this->sortedIds($numeric), $this->filtered('questions', 'question-type', 'numeric'));
        $this->assertSame($this->sortedIds($draft), $this->filtered('questions', 'question-difficulty', '5'));
        $this->assertSame($this->sortedIds($draft), $this->filtered('questions', 'question-subject', (string) $bio->id));
        $this->assertSame($this->sortedIds($draft), $this->filtered('questions', 'question-grade', '9'));
        $this->assertSame($this->sortedIds($reported), $this->filtered('questions', 'question-reports', 'open'));
    }

    public function test_filters_on_the_other_lists(): void
    {
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        $student = $this->student();
        $payments = app(PaymentRequests::class);
        $waiting = $payments->create($this->student(), PremiumPlan::Term, 'TX-W1', null, null, $helper);
        $approved = $payments->create($student, PremiumPlan::Year, 'TX-A1', null, null, $helper);
        $payments->approve($approved, $this->admin);
        $this->assertSame([$waiting->id], $this->filtered('payment-requests', 'payment-status', 'waiting'));
        $this->assertSame([$approved->id], $this->filtered('payment-requests', 'payment-plan', 'year'));

        $grant = app(PremiumService::class)->grant($this->student(), PremiumPlan::Term, $helper, 'TX-G1');
        $undone = app(PremiumService::class)->grant($this->student(), PremiumPlan::Term, $helper, 'TX-G2');
        app(PremiumService::class)->revoke($undone, $this->admin, 'mistake');
        $this->assertSame([$undone->id], $this->filtered('premium-grants', 'grant-state', 'undone'));
        $this->assertContains($grant->id, $this->filtered('premium-grants', 'grant-state', 'active'));
        $this->assertSame($this->sortedIds($grant->id, $undone->id), $this->filtered('premium-grants', 'grant-given-by', (string) $helper->id));

        $unit = Unit::query()->create(['subject_id' => Subject::factory()->create()->id, 'grade' => 11, 'number' => 1, 'title' => 'U']);
        $video = Chapter::query()->create(['unit_id' => $unit->id, 'number' => 1, 'title' => 'With video', 'video_youtube_id' => 'abc123', 'video_access' => 'premium']);
        $noVideo = Chapter::query()->create(['unit_id' => $unit->id, 'number' => 2, 'title' => 'No video']);
        $this->assertSame([$video->id], $this->filtered('chapters', 'chapter-video', 'premium'));
        $this->assertSame([$noVideo->id], $this->filtered('chapters', 'chapter-video', 'none'));
        $this->assertSame($this->sortedIds($video, $noVideo), $this->filtered('chapters', 'chapter-grade', '11'));

        $question = Question::factory()->create();
        $open = QuestionReport::query()->create(['question_id' => $question->id, 'user_id' => $student->id, 'question_version' => 1, 'reason' => 'typo']);
        $this->assertSame([$open->id], $this->filtered('question-reports', 'report-status', 'open'));
        $this->assertSame([], $this->filtered('question-reports', 'report-reason', 'wrong_answer'));

        $rude = NicknameReport::query()->create(['reporter_id' => $student->id, 'reported_user_id' => $this->student()->id, 'nickname' => 'X', 'reason' => 'rude']);
        $this->assertSame([$rude->id], $this->filtered('nickname-reports', 'nickname-report-reason', 'rude'));
        $this->assertSame([$rude->id], $this->filtered('nickname-reports', 'nickname-report-status', 'open'));

        AuditLog::query()->delete();
        $log = AuditLog::write($helper, 'premium.granted');
        AuditLog::write(null, 'backup.done');
        $this->assertSame([$log->id], $this->filtered('audit-logs', 'log-action', 'premium.granted'));
        $this->assertSame([$log->id], $this->filtered('audit-logs', 'log-staff', (string) $helper->id));

        $battle = Battle::query()->create(['code' => 'ZZ1234', 'key' => 'g12-natural', 'is_random' => true, 'question_ids' => [1], 'creator_id' => $student->id, 'expires_at' => now()->subHour()]);
        $this->assertSame([$battle->id], $this->filtered('battles', 'battle-status', 'expired'));
        $this->assertSame([], $this->filtered('battles', 'battle-status', 'waiting'));

        $referral = Referral::query()->create(['inviter_id' => $student->id, 'invitee_id' => $this->student()->id, 'status' => Referral::VALID, 'valid_at' => now()]);
        $this->assertSame([$referral->id], $this->filtered('referrals', 'referral-status', 'valid'));
    }

    public function test_filters_follow_the_roles(): void
    {
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        $checker = User::factory()->staff(UserRole::ContentChecker)->create();

        $this->assertContains('student-plan', $this->filterKeys('students', $helper));
        $this->assertContains('question-status', $this->filterKeys('questions', $checker));
        $this->actingAs($helper)->getJson('/nova-api/questions/filters')->assertForbidden();
        $this->actingAs($checker)->getJson('/nova-api/students/filters')->assertForbidden();
        $this->actingAs($checker)->getJson('/nova-api/audit-logs/filters')->assertForbidden();
        // The lens and card lists of a resource are refused the same way.
        $this->actingAs($helper)->getJson('/nova-api/questions/lenses')->assertForbidden();
        $this->actingAs($checker)->getJson('/nova-api/students/lenses')->assertForbidden();
        $this->actingAs($checker)->getJson('/nova-api/students/cards')->assertForbidden();
        $this->actingAs($helper)->getJson('/nova-api/students/lenses')->assertOk();
    }

    // ---- lenses ---------------------------------------------------------------------------------

    public function test_student_lenses(): void
    {
        $ending = $this->student();
        $grant = app(PremiumService::class)->grant($ending, PremiumPlan::Term, $this->admin, 'TX-L1');
        $grant->forceFill(['ends_at' => now()->addDays(3)])->save();
        $later = $this->student();
        app(PremiumService::class)->grant($later, PremiumPlan::Year, $this->admin, 'TX-L2');
        $ended = $this->student();
        $old = app(PremiumService::class)->grant($ended, PremiumPlan::Term, $this->admin, 'TX-L3');
        $old->forceFill(['starts_at' => now()->subDays(100), 'ends_at' => now()->subDay()])->save();
        $this->assertSame([$ending->id], $this->lens('students', 'premium-ends-in-7-days'));

        // This week started Monday 00:00 Addis time; Sunday's answers belong to last week.
        $busy = $this->student();
        $this->answers($busy, 30, '2026-11-23 08:00:00');
        $some = $this->student();
        $this->answers($some, 10, '2026-11-23 09:00:00');
        $this->answers($some, 50, '2026-11-22 20:00:00');
        $this->assertSame([$busy->id, $some->id], $this->lens('students', 'most-active-this-week'));
        $this->assertSame(30, $this->firstRowField('/nova-api/students/lens/most-active-this-week', 'Answers this week')['value']);

        $quiet = $this->student();
        $quiet->forceFill(['last_active_at' => now()->subDays(15), 'created_at' => now()->subDays(60)])->save();
        $never = $this->student();
        $never->forceFill(['last_active_at' => null, 'created_at' => now()->subDays(20)])->save();
        $fresh = $this->student();
        $fresh->forceFill(['last_active_at' => null, 'created_at' => now()->subDays(2)])->save();
        $inactive = $this->lens('students', 'inactive-14-days');
        $this->assertContains($quiet->id, $inactive);
        $this->assertContains($never->id, $inactive);
        $this->assertNotContains($fresh->id, $inactive);

        // "Today" is the Addis Ababa day: 22:30 UTC yesterday is already today there.
        $today = $this->student();
        $today->forceFill(['created_at' => Carbon::parse('2026-11-22 22:30:00', 'UTC')])->save();
        $yesterday = $this->student();
        $yesterday->forceFill(['created_at' => Carbon::parse('2026-11-22 20:30:00', 'UTC')])->save();
        $new = $this->lens('students', 'new-today');
        $this->assertContains($today->id, $new);
        $this->assertNotContains($yesterday->id, $new);
        $this->assertNotContains($this->admin->id, $this->lens('students', 'new-today'), 'staff are not students');
    }

    public function test_premium_grant_lenses(): void
    {
        $today = app(PremiumService::class)->grant($this->student(), PremiumPlan::Term, $this->admin, 'TX-T1');
        $earlier = app(PremiumService::class)->grant($this->student(), PremiumPlan::Term, $this->admin, 'TX-T2');
        $earlier->forceFill(['created_at' => now()->subDays(2)])->save();
        app(PremiumService::class)->revoke($earlier, $this->admin, 'wrong student');

        $this->assertSame([$today->id], $this->lens('premium-grants', 'given-today'));
        $this->assertSame([$earlier->id], $this->lens('premium-grants', 'undone-grants'));
    }

    public function test_question_lenses(): void
    {
        $hard = Question::factory()->create(['times_answered' => 40, 'times_correct' => 4]);
        $easy = Question::factory()->create(['times_answered' => 40, 'times_correct' => 38]);
        $few = Question::factory()->create(['times_answered' => 5, 'times_correct' => 0]);
        $this->assertSame([$hard->id, $easy->id], $this->lens('questions', 'hardest-questions'));
        $this->assertSame([$easy->id, $hard->id], $this->lens('questions', 'easiest-questions'));

        $many = Question::factory()->create(['reports_count' => 5]);
        $one = Question::factory()->create(['reports_count' => 1]);
        $this->assertSame([$many->id, $one->id], $this->lens('questions', 'most-reported'));

        $thin = Question::factory()->create(['explanation' => 'B.']);
        $noPicture = Question::factory()->create(['stem' => 'Look at the diagram below. Which part is the nucleus?']);
        $withPicture = Question::factory()->create(['stem' => 'Look at the diagram below.', 'media_path' => 'diagrams/cell.svg']);
        $missing = $this->lens('questions', 'missing-explanation-or-diagram');
        $this->assertContains($thin->id, $missing);
        $this->assertContains($noPicture->id, $missing);
        $this->assertNotContains($withPicture->id, $missing);
        $this->assertNotContains($few->id, $missing);
    }

    public function test_ready_to_publish_matches_the_quality_gate(): void
    {
        $ready = Question::factory()->create(['status' => QuestionStatus::Beta, 'times_answered' => 30, 'times_correct' => 20]);
        $fixed = Question::factory()->create(['status' => QuestionStatus::Fixed, 'times_answered' => 31, 'times_correct' => 10]);
        Question::factory()->create(['status' => QuestionStatus::Beta, 'times_answered' => 29, 'times_correct' => 20]);
        $tooHard = Question::factory()->create(['status' => QuestionStatus::Beta, 'times_answered' => 40, 'times_correct' => 2]);
        $reported = Question::factory()->create(['status' => QuestionStatus::Beta, 'times_answered' => 40, 'times_correct' => 20]);
        QuestionReport::query()->create(['question_id' => $reported->id, 'user_id' => $this->student()->id, 'question_version' => 1, 'reason' => 'typo']);
        Question::factory()->create(['status' => QuestionStatus::Published, 'times_answered' => 99, 'times_correct' => 50]);

        $lens = $this->lens('questions', 'ready-to-publish');
        sort($lens);
        $this->assertSame($this->sortedIds($ready, $fixed), $lens);

        // The gate publishes exactly those.
        app(QualityGate::class)->promote();
        $this->assertSame(QuestionStatus::Published, $ready->refresh()->status);
        $this->assertSame(QuestionStatus::Published, $fixed->refresh()->status);
        $this->assertSame(QuestionStatus::Reported, $tooHard->refresh()->status);
        $this->assertSame(QuestionStatus::Beta, $reported->refresh()->status);
    }

    public function test_chapter_lenses(): void
    {
        $unit = Unit::query()->create(['subject_id' => Subject::factory()->create()->id, 'grade' => 12, 'number' => 1, 'title' => 'U']);
        $complete = Chapter::query()->create(['unit_id' => $unit->id, 'number' => 1, 'title' => 'Done', 'video_youtube_id' => 'v1', 'textbook_url' => 'https://example.org/b.pdf']);
        DB::table('summaries')->insert(['chapter_id' => $complete->id, 'key_points' => '["One"]', 'formulas' => '[]', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('solved_exercises')->insert(['chapter_id' => $complete->id, 'sort' => 1, 'problem' => 'P', 'steps' => '[]', 'final_answer' => 'A', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('flashcards')->insert(['chapter_id' => $complete->id, 'sort' => 1, 'term' => 'T', 'definition' => 'D', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        Question::factory()->count(30)->create(['chapter_id' => $complete->id]);
        $empty = Chapter::query()->create(['unit_id' => $unit->id, 'number' => 2, 'title' => 'Empty']);

        $this->assertSame([$empty->id], $this->lens('chapters', 'incomplete-content'));
        $this->assertSame('summary, solved exercises, flashcards, textbook link, questions (0 of 30)',
            $this->firstRowField('/nova-api/chapters/lens/incomplete-content', 'Missing')['value']);
        $this->assertSame([$empty->id], $this->lens('chapters', 'no-video-yet'));
    }

    public function test_report_lenses(): void
    {
        $student = $this->student();
        $question = Question::factory()->create();
        $new = QuestionReport::query()->create(['question_id' => $question->id, 'user_id' => $student->id, 'question_version' => 1, 'reason' => 'typo']);
        $old = QuestionReport::query()->create(['question_id' => $question->id, 'user_id' => $this->student()->id, 'question_version' => 1, 'reason' => 'unclear']);
        $old->forceFill(['created_at' => now()->subHours(49)])->save();
        $done = QuestionReport::query()->create(['question_id' => $question->id, 'user_id' => $this->student()->id, 'question_version' => 1, 'reason' => 'other', 'status' => 'rejected']);
        $done->forceFill(['created_at' => now()->subDays(5)])->save();

        $this->assertSame([$old->id, $new->id], $this->lens('question-reports', 'open-reports'));
        $this->assertSame([$old->id], $this->lens('question-reports', 'open-48-hours'));

        $nickOld = NicknameReport::query()->create(['reporter_id' => $student->id, 'reported_user_id' => $this->student()->id, 'nickname' => 'Y', 'reason' => 'rude']);
        $nickOld->forceFill(['created_at' => now()->subDays(3)])->save();
        $this->assertSame([$nickOld->id], $this->lens('nickname-reports', 'open-48-hours'));
    }

    public function test_lenses_follow_the_roles(): void
    {
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        $checker = User::factory()->staff(UserRole::ContentChecker)->create();

        $this->lens('students', 'new-today', $helper);
        $this->lens('questions', 'hardest-questions', $checker);
        $this->actingAs($helper)->getJson('/nova-api/questions/lens/hardest-questions')->assertForbidden();
        $this->actingAs($checker)->getJson('/nova-api/students/lens/new-today')->assertForbidden();
        $this->actingAs($checker)->getJson('/nova-api/premium-grants/lens/given-today')->assertForbidden();
        $this->actingAs($helper)->getJson('/nova-api/question-reports/lens/open-reports')->assertForbidden();
    }

    public function test_lenses_have_no_n_plus_one_queries(): void
    {
        $count = function (string $uri): int {
            $n = 0;
            DB::listen(function (QueryExecuted $q) use (&$n): void {
                $n++;
            });
            $this->actingAs($this->admin)->getJson($uri)->assertOk();

            return $n;
        };
        foreach (['students/lens/most-active-this-week', 'students/lens/premium-ends-in-7-days', 'chapters/lens/incomplete-content', 'questions/lens/hardest-questions'] as $uri) {
            $make = function (int $n): void {
                $unit = Unit::query()->create(['subject_id' => Subject::factory()->create()->id, 'grade' => 12, 'number' => random_int(1, 60000), 'title' => 'U']);
                for ($i = 0; $i < $n; $i++) {
                    $s = $this->student();
                    $this->answers($s, 5, '2026-11-23 08:00:00');
                    $g = app(PremiumService::class)->grant($s, PremiumPlan::Term, $this->admin, 'TX-N'.Str::random(8));
                    $g->forceFill(['ends_at' => now()->addDays(2)])->save();
                    Chapter::query()->create(['unit_id' => $unit->id, 'number' => $i + 1, 'title' => 'C'.$i]);
                    Question::factory()->create(['times_answered' => 40, 'times_correct' => $i]);
                }
            };
            $make(2);
            // One request first: caches (settings, branding) are filled once, as in normal use.
            $count("/nova-api/{$uri}");
            $few = $count("/nova-api/{$uri}");
            $make(10);
            $many = $count("/nova-api/{$uri}");
            $this->assertSame($few, $many, "{$uri}: {$few} queries with a few rows, {$many} with more rows (N+1)");
        }
    }

    // ---- remembered filters -----------------------------------------------------------------------

    public function test_our_script_remembers_the_last_filter_per_staff_member(): void
    {
        $this->actingAs($this->admin)->get('/nova-api/scripts/guul-nova')->assertOk()
            ->assertSee("Nova.\$on('filter-changed'", false)
            ->assertSee("Nova.\$on('filter-reset'", false)
            ->assertSee("'guul.filters.' + Nova.config('userId')", false)
            ->assertSee("document.addEventListener('inertia:navigate', restore)", false);
    }
}
