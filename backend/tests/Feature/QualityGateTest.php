<?php

namespace Tests\Feature;

use App\Enums\QuestionStatus;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Question;
use App\Models\QuestionReport;
use App\Models\User;
use App\Services\Quality\QualityGate;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class QualityGateTest extends TestCase
{
    use RefreshDatabase;

    private function fresh(Question $question): Question
    {
        return Question::query()->findOrFail($question->id);
    }

    /**
     * @return TestResponse<Response>
     */
    private function report(Question $question, string $reason = 'wrong_answer', ?User $user = null): TestResponse
    {
        Sanctum::actingAs($user ?? User::factory()->create());

        return $this->postJson("/api/v1/questions/{$question->id}/reports", ['reason' => $reason, 'note' => '  I think C is right  ']);
    }

    public function test_a_student_reports_once_per_question_version(): void
    {
        $question = Question::factory()->withOptions()->status(QuestionStatus::Beta)->create();
        $student = User::factory()->create();

        $this->report($question, user: $student)->assertCreated()->assertJsonPath('report.reason', 'wrong_answer');
        $this->report($question, user: $student)->assertOk();

        $this->assertSame(1, QuestionReport::query()->count());
        $this->assertSame('I think C is right', QuestionReport::query()->firstOrFail()->note);
        $this->assertSame(1, $this->fresh($question)->reports_count);

        // After the question changes (new version) the same student may report again.
        $question->stem = 'A clearer stem';
        $question->save();
        $this->report($question, user: $student)->assertCreated();
    }

    public function test_reports_need_a_visible_question_and_a_known_reason(): void
    {
        $draft = Question::factory()->status(QuestionStatus::Draft)->create();
        $this->report($draft)->assertNotFound();

        $beta = Question::factory()->status(QuestionStatus::Beta)->create();
        $this->report($beta, 'boring')->assertUnprocessable()->assertJsonValidationErrors('reason');

        $this->postJson('/api/v1/questions/999999/reports', ['reason' => 'typo'])->assertNotFound();
    }

    public function test_enough_reports_send_a_question_to_review(): void
    {
        $this->app->make(Settings::class)->set('report_threshold', 2);
        $question = Question::factory()->status(QuestionStatus::Published)->create();

        $this->report($question);
        $this->assertSame(QuestionStatus::Published, $this->fresh($question)->status);
        $this->report($question, 'unclear');
        $this->assertSame(QuestionStatus::Reported, $this->fresh($question)->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'question.reported', 'subject_id' => $question->id]);
    }

    public function test_daily_report_limit(): void
    {
        $student = User::factory()->create();
        $questions = Question::factory()->count(30)->status(QuestionStatus::Beta)->create();
        $oneMore = Question::factory()->status(QuestionStatus::Beta)->create();
        foreach ($questions as $q) {
            $this->report($q, user: $student)->assertCreated();
        }
        $this->report($oneMore, user: $student)->assertStatus(429)->assertJsonPath('code', 'report_limit');
    }

    public function test_daily_report_limit_follows_the_addis_ababa_day(): void
    {
        // 02:30 in Addis Ababa = 23:30 UTC the day before.
        Carbon::setTestNow(Carbon::parse('2026-11-09 23:30', 'UTC'));
        $student = User::factory()->create();
        foreach (Question::factory()->count(30)->status(QuestionStatus::Beta)->create() as $q) {
            $this->report($q, user: $student)->assertCreated();
        }
        // 04:00 in Addis Ababa: still the same day for the student (a new day only in UTC).
        Carbon::setTestNow(Carbon::parse('2026-11-10 01:00', 'UTC'));
        $this->report(Question::factory()->status(QuestionStatus::Beta)->create(), user: $student)->assertStatus(429);
        // Midnight in Addis Ababa: a new day.
        Carbon::setTestNow(Carbon::parse('2026-11-10 21:00', 'UTC'));
        $this->report(Question::factory()->status(QuestionStatus::Beta)->create(), user: $student)->assertCreated();
        Carbon::setTestNow();
    }

    public function test_promotion_publishes_clean_questions_and_flags_suspicious_ones(): void
    {
        $gate = $this->app->make(QualityGate::class);
        $good = Question::factory()->status(QuestionStatus::Beta)->create(['times_answered' => 30, 'times_correct' => 18]);
        $tooNew = Question::factory()->status(QuestionStatus::Beta)->create(['times_answered' => 29, 'times_correct' => 20]);
        $suspicious = Question::factory()->status(QuestionStatus::Beta)->create(['times_answered' => 40, 'times_correct' => 2]);
        $reported = Question::factory()->status(QuestionStatus::Beta)->create(['times_answered' => 50, 'times_correct' => 30]);
        QuestionReport::query()->create([
            'question_id' => $reported->id, 'user_id' => User::factory()->create()->id, 'question_version' => 1,
            'reason' => 'typo', 'status' => ReportStatus::Open,
        ]);

        $this->assertSame(['published' => 1, 'sent_to_review' => 1], $gate->promote());

        $this->assertSame(QuestionStatus::Published, $this->fresh($good)->status);
        $this->assertNotNull($this->fresh($good)->published_at);
        $this->assertSame(QuestionStatus::Beta, $this->fresh($tooNew)->status);
        $this->assertSame(QuestionStatus::Reported, $this->fresh($suspicious)->status);
        $this->assertSame(QuestionStatus::Beta, $this->fresh($reported)->status, 'open report blocks publishing');

        $this->command('quality:promote')->expectsOutputToContain('Published: 0')->assertSuccessful();
    }

    public function test_resolving_reports(): void
    {
        $gate = $this->app->make(QualityGate::class);
        $checker = User::factory()->staff(UserRole::ContentChecker)->create();
        $question = Question::factory()->status(QuestionStatus::Reported)->create(['published_at' => now()->subDay()]);
        QuestionReport::query()->create([
            'question_id' => $question->id, 'user_id' => User::factory()->create()->id, 'question_version' => 1,
            'reason' => 'wrong_answer', 'status' => ReportStatus::Open,
        ]);

        $this->assertSame(1, $gate->resolve($question, false, $checker));

        $this->assertSame(QuestionStatus::Published, $this->fresh($question)->status);
        $this->assertSame(ReportStatus::Rejected, QuestionReport::query()->firstOrFail()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'question.reports_rejected', 'actor_id' => $checker->id]);
    }
}
