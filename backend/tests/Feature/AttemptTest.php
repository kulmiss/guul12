<?php

namespace Tests\Feature;

use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttemptTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->student = User::factory()->create();
        Sanctum::actingAs($this->student);
    }

    /**
     * @param  list<array<string, mixed>>  $answers
     * @return array<string, mixed>
     */
    private function payload(array $answers, ?string $uuid = null): array
    {
        return [
            'client_uuid' => $uuid ?? (string) Str::uuid(),
            'mode' => 'quick10',
            'started_at' => now()->subMinutes(5)->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'answers' => $answers,
        ];
    }

    public function test_server_scores_the_answers_and_ignores_what_the_phone_claims(): void
    {
        $q1 = Question::factory()->withOptions()->create();
        $q2 = Question::factory()->create(['type' => QuestionType::Numeric, 'answer_key' => ['value' => 2, 'tolerance' => 0]]);

        $response = $this->postJson('/api/v1/attempts', $this->payload([
            ['question_id' => $q1->id, 'version' => 1, 'response' => 'B', 'time_ms' => 5000, 'is_correct' => false],
            ['question_id' => $q2->id, 'version' => 1, 'response' => '3', 'time_ms' => 5000, 'is_correct' => true],
        ]));

        $response->assertCreated()
            ->assertJsonPath('attempt.total', 2)
            ->assertJsonPath('attempt.correct', 1)
            ->assertJsonPath('attempt.answers.0.is_correct', true)
            ->assertJsonPath('attempt.answers.1.is_correct', false);

        $this->assertSame(1, $q1->refresh()->times_answered);
        $this->assertSame(1, $q1->times_correct);
        $this->assertSame(1, $q2->refresh()->times_answered);
        $this->assertSame(0, $q2->times_correct);
    }

    public function test_times_with_an_offset_are_stored_in_utc(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-25 10:00:00', 'UTC'));
        $q = Question::factory()->withOptions()->create();
        $payload = [
            ...$this->payload([['question_id' => $q->id, 'version' => 1, 'response' => 'B', 'time_ms' => 5000]]),
            'started_at' => '2026-11-25T12:30:00+03:00',
            'finished_at' => '2026-11-25T12:45:00+03:00',
        ];
        $this->postJson('/api/v1/attempts', $payload)->assertCreated();
        Carbon::setTestNow();

        $row = DB::table('attempts')->where('client_uuid', $payload['client_uuid'])->first();
        $this->assertNotNull($row);
        $this->assertSame('2026-11-25 09:30:00', (string) $row->started_at);
        $this->assertSame('2026-11-25 09:45:00', (string) $row->finished_at);
    }

    public function test_uploading_the_same_attempt_twice_counts_once(): void
    {
        $question = Question::factory()->withOptions()->create();
        $payload = $this->payload([['question_id' => $question->id, 'version' => 1, 'response' => 'B', 'time_ms' => 4000]]);

        $this->postJson('/api/v1/attempts', $payload)->assertCreated();
        $this->postJson('/api/v1/attempts', $payload)->assertOk()->assertJsonPath('attempt.correct', 1);

        $this->assertDatabaseCount('attempts', 1);
        $this->assertSame(1, $question->refresh()->times_answered);
    }

    public function test_hidden_unknown_and_repeated_questions_are_ignored(): void
    {
        $visible = Question::factory()->withOptions()->create();
        $draft = Question::factory()->status(QuestionStatus::Draft)->create();

        $this->postJson('/api/v1/attempts', $this->payload([
            ['question_id' => $visible->id, 'version' => 1, 'response' => 'B', 'time_ms' => 3000],
            ['question_id' => $visible->id, 'version' => 1, 'response' => 'B', 'time_ms' => 3000],
            ['question_id' => $draft->id, 'version' => 1, 'response' => 'B', 'time_ms' => 3000],
            ['question_id' => 999999, 'version' => 1, 'response' => 'B', 'time_ms' => 3000],
        ]))->assertCreated()->assertJsonPath('attempt.total', 1)->assertJsonPath('attempt.correct', 1);

        $this->assertSame(0, $draft->refresh()->times_answered);
    }

    public function test_too_fast_answers_are_flagged_and_time_is_capped(): void
    {
        app(Settings::class)->set('min_answer_ms', 800);
        app(Settings::class)->set('max_answer_ms', 60000);
        $question = Question::factory()->withOptions()->create();
        $other = Question::factory()->withOptions()->create();

        $this->postJson('/api/v1/attempts', $this->payload([
            ['question_id' => $question->id, 'version' => 1, 'response' => 'B', 'time_ms' => 120],
            ['question_id' => $other->id, 'version' => 1, 'response' => 'B', 'time_ms' => 999999999],
        ]))->assertCreated();

        $this->assertDatabaseHas('attempts', ['user_id' => $this->student->id, 'too_fast' => 1]);
        $this->assertDatabaseHas('attempt_answers', ['question_id' => $question->id, 'too_fast' => true, 'time_ms' => 120]);
        $this->assertDatabaseHas('attempt_answers', ['question_id' => $other->id, 'too_fast' => false, 'time_ms' => 60000]);
    }

    public function test_skipped_answer_counts_as_wrong(): void
    {
        $question = Question::factory()->withOptions()->create();

        $this->postJson('/api/v1/attempts', $this->payload([
            ['question_id' => $question->id, 'version' => 1, 'response' => null, 'time_ms' => 3000],
        ]))->assertCreated()->assertJsonPath('attempt.correct', 0)->assertJsonPath('attempt.total', 1);
    }

    public function test_attempt_validation(): void
    {
        $this->postJson('/api/v1/attempts', [
            'client_uuid' => 'x',
            'mode' => 'cheat',
            'started_at' => now()->toIso8601String(),
            'finished_at' => now()->addDay()->toIso8601String(),
            'answers' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors(['client_uuid', 'mode', 'finished_at', 'answers']);

        $this->postJson('/api/v1/attempts', $this->payload([['question_id' => 1, 'version' => 0, 'time_ms' => -1]]))
            ->assertUnprocessable()->assertJsonValidationErrors(['answers.0.version', 'answers.0.time_ms', 'answers.0.response']);
    }

    public function test_every_mode_the_app_sends_is_accepted(): void
    {
        $q = Question::factory()->withOptions()->create();
        // Must match app/lib/domain/practice.dart (PracticeMode) and exam.dart (ExamKind).
        foreach (['onboarding', 'quick10', 'chapter', 'unit', 'mixed', 'timed', 'wrong_only', 'quick_revise', 'chapter_quiz', 'review', 'smart', 'exam_random', 'exam_weak', 'exam_mistakes'] as $mode) {
            $payload = [...$this->payload([['question_id' => $q->id, 'version' => 1, 'response' => 'B', 'time_ms' => 5000]]), 'mode' => $mode];
            $this->postJson('/api/v1/attempts', $payload)->assertCreated();
        }
    }

    public function test_attempts_of_two_students_with_the_same_uuid_are_separate(): void
    {
        $question = Question::factory()->withOptions()->create();
        $uuid = (string) Str::uuid();
        $payload = $this->payload([['question_id' => $question->id, 'version' => 1, 'response' => 'A', 'time_ms' => 3000]], $uuid);

        $this->postJson('/api/v1/attempts', $payload)->assertCreated();
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/attempts', $payload)->assertCreated();

        $this->assertDatabaseCount('attempts', 2);
    }

    public function test_bookmarks_toggle(): void
    {
        $question = Question::factory()->create();
        $draft = Question::factory()->status(QuestionStatus::Draft)->create();

        $this->putJson("/api/v1/bookmarks/{$question->id}")->assertOk();
        $this->putJson("/api/v1/bookmarks/{$question->id}")->assertOk();
        $this->getJson('/api/v1/bookmarks')->assertJsonPath('question_ids', [$question->id]);

        $this->putJson("/api/v1/bookmarks/{$draft->id}")->assertNotFound();

        $this->deleteJson("/api/v1/bookmarks/{$question->id}")->assertOk();
        $this->getJson('/api/v1/bookmarks')->assertJsonPath('question_ids', []);
    }
}
