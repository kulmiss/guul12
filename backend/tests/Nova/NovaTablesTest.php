<?php

namespace Tests\Nova;

use App\Enums\PremiumPlan;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Chapter;
use App\Models\NicknameReport;
use App\Models\Passage;
use App\Models\Question;
use App\Models\QuestionReport;
use App\Models\User;
use App\Services\PaymentRequests;
use App\Services\PremiumService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Nova tables: no N+1 queries on big lists, search on the right fields, counts, readable dates,
 * badges and grouped detail pages.
 * Runs only where the licensed Nova package is installed (php artisan test --testsuite=Nova).
 */
class NovaTablesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->staff(UserRole::SuperAdmin)->create();
    }

    private function queriesFor(string $uri): int
    {
        $count = 0;
        DB::listen(function (QueryExecuted $query) use (&$count): void {
            $count++;
        });
        $this->actingAs($this->admin)->getJson($uri)->assertOk();

        return $count;
    }

    /** @param  \Closure(int): mixed  $make  creates n more rows */
    private function assertNoNPlusOne(string $uri, \Closure $make): void
    {
        $make(3);
        $few = $this->queriesFor($uri);
        $make(20);
        $many = $this->queriesFor($uri);
        $this->assertSame($few, $many, "{$uri}: {$few} queries with a few rows, {$many} with more rows (N+1)");
    }

    public function test_big_lists_have_no_n_plus_one_queries(): void
    {
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        $chapter = Chapter::factory()->create();

        $this->assertNoNPlusOne('/nova-api/questions?perPage=25', fn (int $n) => Question::factory()->count($n)->create(['chapter_id' => Chapter::factory()->create()->id]));
        $this->assertNoNPlusOne('/nova-api/chapters?perPage=25', fn (int $n) => Question::factory()->count($n)->create(['chapter_id' => Chapter::factory()->create()->id]));
        $this->assertNoNPlusOne('/nova-api/students?perPage=25', function (int $n) use ($helper): void {
            foreach (User::factory()->count($n)->create() as $student) {
                app(PremiumService::class)->grant($student, PremiumPlan::Term, $helper, 'TX-'.$student->id);
            }
        });
        $this->assertNoNPlusOne('/nova-api/premium-grants?perPage=25', fn (int $n) => User::factory()->count($n)->create()->each(
            fn (User $s) => app(PremiumService::class)->grant($s, PremiumPlan::Term, $helper, 'PG-'.$s->id)));
        $this->assertNoNPlusOne('/nova-api/payment-requests?perPage=25', fn (int $n) => User::factory()->count($n)->create()->each(
            fn (User $s) => app(PaymentRequests::class)->create($s, PremiumPlan::Term, 'PR-'.$s->id, null, null, $helper)));
        $this->assertNoNPlusOne('/nova-api/question-reports?perPage=25', function (int $n) use ($chapter): void {
            $question = Question::factory()->create(['chapter_id' => $chapter->id])->refresh();
            foreach (User::factory()->count($n)->create() as $student) {
                QuestionReport::query()->create(['question_id' => $question->id, 'user_id' => $student->id, 'question_version' => $question->version, 'reason' => 'typo']);
            }
        });
        $this->assertNoNPlusOne('/nova-api/nickname-reports?perPage=25', function (int $n): void {
            foreach (User::factory()->count($n)->create(['nickname' => 'Bad']) as $student) {
                NicknameReport::query()->create(['reporter_id' => User::factory()->create()->id, 'reported_user_id' => $student->id, 'nickname' => 'Bad', 'reason' => 'rude']);
            }
        });
        $this->assertNoNPlusOne('/nova-api/passages?perPage=25', fn (int $n) => Passage::query()->insert(array_fill(0, $n, ['title' => 'P', 'body' => 'Text', 'created_at' => now(), 'updated_at' => now()])));
        foreach (['units', 'subjects', 'summaries', 'solved-exercises', 'flashcards', 'question-options', 'staff', 'app-settings', 'feature-switches'] as $uri) {
            $this->actingAs($this->admin)->getJson("/nova-api/{$uri}")->assertOk();
        }
        $this->assertNoNPlusOne('/nova-api/audit-logs?perPage=25', function (int $n) use ($helper): void {
            for ($i = 0; $i < $n; $i++) {
                AuditLog::write($helper, 'test.event', $helper);
            }
        });
    }

    /** @return list<int> */
    private function searchIds(string $uri, string $search): array
    {
        $rows = $this->actingAs($this->admin)->getJson($uri.'?search='.urlencode($search))->assertOk()->json('resources');
        $this->assertIsArray($rows);

        return array_values(array_map(fn (array $row): int => (int) $row['id']['value'], $rows));
    }

    public function test_search_looks_in_the_right_fields(): void
    {
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        $optics = Chapter::factory()->create(['title' => 'Geometrical optics']);
        $q = Question::factory()->create(['chapter_id' => $optics->id, 'stem' => 'What is a lens?', 'tags' => ['refraction']]);
        Question::factory()->create(['chapter_id' => Chapter::factory()->create(['title' => 'Waves'])->id]);
        $this->assertSame([$q->id], $this->searchIds('/nova-api/questions', 'optics'), 'by chapter title');
        $this->assertSame([$q->id], $this->searchIds('/nova-api/questions', 'refraction'), 'by tag');

        $signed = User::factory()->create(['email' => 'ayan@example.com']);
        $this->assertSame([$signed->id], $this->searchIds('/nova-api/students', 'ayan@example'));

        $student = User::factory()->create();
        $grant = app(PremiumService::class)->grant($student, PremiumPlan::Term, $helper, 'TX-SEARCH');
        $this->assertSame([$grant->id], $this->searchIds('/nova-api/premium-grants', $student->student_id), 'grant by student ID');

        $log = AuditLog::write($helper, 'premium.granted', $grant);
        $this->assertContains($log->id, $this->searchIds('/nova-api/audit-logs', 'premium.granted'));
    }

    /** @return array<string, mixed> field name => displayed value (or the raw value with $raw) */
    private function fieldsOf(string $uri, bool $raw = false): array
    {
        $json = $this->actingAs($this->admin)->getJson($uri)->assertOk()->json();
        $this->assertIsArray($json);
        $fields = $json['resource']['fields'] ?? $json['resources'][0]['fields'] ?? [];
        $out = [];
        foreach ($fields as $field) {
            $out[(string) $field['name']] = $raw ? $field['value'] : ($field['displayedAs'] ?? $field['value']);
        }

        return $out;
    }

    public function test_chapters_show_question_and_report_counts(): void
    {
        $chapter = Chapter::factory()->create();
        Question::factory()->count(3)->create(['chapter_id' => $chapter->id]);
        Question::factory()->create(['chapter_id' => $chapter->id, 'reports_count' => 2]);

        $fields = $this->fieldsOf('/nova-api/chapters', raw: true);
        $this->assertSame(4, $fields['Questions']);
        $this->assertSame(1, $fields['Reported']);
    }

    public function test_dates_are_easy_to_read_and_badges_have_labels(): void
    {
        Carbon::setTestNow('2026-11-25 10:00:00');
        $student = User::factory()->create();
        $student->forceFill(['created_at' => Carbon::parse('2026-11-23 10:00:00')])->save();

        $index = $this->fieldsOf('/nova-api/students');
        $this->assertSame('2 days ago', $index['Joined']);
        $this->assertSame('Free', $index['Plan']);
        $detail = $this->fieldsOf("/nova-api/students/{$student->id}");
        $this->assertSame('23 Nov 2026, 13:00 (2 days ago)', $detail['Joined']);
        Carbon::setTestNow();
    }

    public function test_student_detail_is_grouped_in_panels(): void
    {
        $student = User::factory()->create(['nickname' => 'Ayan']);
        $json = $this->actingAs($this->admin)->getJson("/nova-api/students/{$student->id}")->assertOk()->json('resource');
        $this->assertIsArray($json);
        $panels = array_values(array_unique(array_column($json['fields'], 'panel')));

        $this->assertSame(['Profile', 'Premium', 'Progress', 'Referrals', 'Reports'], array_slice($panels, 0, 5));
        $names = array_column($json['fields'], 'name');
        foreach (['Answers', 'Right answers', 'Exams finished', 'Invite code', 'Friends invited', 'Reports sent', 'Nickname reports about them'] as $name) {
            $this->assertContains($name, $names);
        }
    }
}
