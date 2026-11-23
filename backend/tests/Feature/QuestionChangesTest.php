<?php

namespace Tests\Feature;

use App\Content\QuestionBulkImport;
use App\Contracts\StaffNotifier;
use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Enums\UserRole;
use App\Jobs\ApplyQuestionChanges;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\AuditLog;
use App\Models\Chapter;
use App\Models\ContentChange;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use App\Services\Staff\QuestionChanges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Staff changes to questions: status moves that keep the quality gate (Publish only from Beta, no
 * skipping the second solve), difficulty, chapter, copies, fixing a key with re-scoring, and bulk
 * import as Draft. Every change is logged old → new.
 */
class QuestionChangesTest extends TestCase
{
    use RefreshDatabase;

    private QuestionChanges $changes;

    private User $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->changes = app(QuestionChanges::class);
        $this->checker = User::factory()->staff(UserRole::ContentChecker)->create();
    }

    /** @param  array<string, mixed>  $extra */
    private function question(QuestionStatus $status, array $extra = []): Question
    {
        return Question::factory()->withOptions()->create(['status' => $status, ...$extra]);
    }

    public function test_publish_only_from_beta(): void
    {
        $beta = $this->question(QuestionStatus::Beta);
        $this->assertTrue($this->changes->setStatus($beta, 'publish', $this->checker));
        $this->assertSame(QuestionStatus::Published, $beta->refresh()->status);
        $this->assertNotNull($beta->published_at);
        $log = AuditLog::query()->where('action', 'question.status')->firstOrFail();
        $this->assertSameJson(['old' => ['status' => 'beta'], 'new' => ['status' => 'published']], $log->data);

        foreach ([QuestionStatus::Draft, QuestionStatus::Checked, QuestionStatus::Reported, QuestionStatus::Retired] as $status) {
            $q = $this->question($status);
            $this->assertFalse($this->changes->setStatus($q, 'publish', $this->checker), $status->value);
            $this->assertSame($status, $q->refresh()->status);
        }
    }

    public function test_beta_and_draft_moves_keep_the_second_solve_rule(): void
    {
        $checked = $this->question(QuestionStatus::Checked);
        $this->assertTrue($this->changes->setStatus($checked, 'beta', $this->checker));
        $this->assertSame(QuestionStatus::Beta, $checked->refresh()->status);

        $draft = $this->question(QuestionStatus::Draft);
        $this->assertFalse($this->changes->setStatus($draft, 'beta', $this->checker), 'a draft has not been solved a second time');

        $published = $this->question(QuestionStatus::Published);
        $this->assertTrue($this->changes->setStatus($published, 'draft', $this->checker));
        $this->assertSame(QuestionStatus::Draft, $published->refresh()->status);
        $this->assertFalse($this->changes->setStatus($published, 'draft', $this->checker), 'already a draft');
        $this->assertFalse($this->changes->setStatus($published, 'nonsense', $this->checker));
    }

    public function test_difficulty_and_chapter_changes_are_logged_and_synced(): void
    {
        $question = $this->question(QuestionStatus::Published, ['difficulty' => 2]);
        $old = $question->chapter_id;
        $new = Chapter::factory()->create();

        $this->assertTrue($this->changes->setDifficulty($question, 4, $this->checker));
        $this->assertFalse($this->changes->setDifficulty($question, 4, $this->checker), 'no change');
        $this->assertFalse($this->changes->setDifficulty($question, 9, $this->checker), 'out of range');
        $this->assertSame(4, $question->refresh()->difficulty);

        $before = ContentChange::latestCursor();
        $this->assertTrue($this->changes->moveToChapter($question, $new, $this->checker));
        $this->assertSame($new->id, $question->refresh()->chapter_id);
        $changed = ContentChange::query()->where('id', '>', $before)->pluck('chapter_id')->unique()->sort()->values()->all();
        $this->assertSame([$old, $new->id], $changed, 'phones refresh both the old and the new chapter');
        $log = AuditLog::query()->where('action', 'question.chapter')->firstOrFail();
        $this->assertSameJson(['old' => ['chapter_id' => $old], 'new' => ['chapter_id' => $new->id]], $log->data);
    }

    public function test_duplicate_makes_a_draft_copy_with_options_and_no_history(): void
    {
        $original = $this->question(QuestionStatus::Published, ['uid' => 'g12-x-1-1-q001', 'times_answered' => 50, 'times_correct' => 20, 'reports_count' => 2]);

        $copy = $this->changes->duplicate($original, $this->checker);

        $this->assertNotSame($original->id, $copy->id);
        $this->assertSame(QuestionStatus::Draft, $copy->status);
        $this->assertNull($copy->uid, 'the content import never touches it');
        $this->assertSame([0, 0, 0, 1], [$copy->times_answered, $copy->times_correct, $copy->reports_count, $copy->version]);
        $this->assertSame($original->stem, $copy->stem);
        $this->assertEquals($original->answer_key, $copy->answer_key);
        $this->assertSame(['A', 'B', 'C', 'D'], QuestionOption::query()->where('question_id', $copy->id)->orderBy('sort')->pluck('key')->all());
        $this->assertSameJson(['from' => $original->id], AuditLog::query()->where('action', 'question.duplicated')->firstOrFail()->data);
    }

    public function test_mark_fixed_rescores_old_answers_quietly(): void
    {
        $question = $this->question(QuestionStatus::Reported, ['answer_key' => ['correct' => 'C']]);
        $student = User::factory()->create();
        $attempt = Attempt::query()->create(['user_id' => $student->id, 'client_uuid' => (string) Str::uuid(), 'mode' => 'quick10',
            'started_at' => now()->subMinute(), 'finished_at' => now(), 'total' => 1, 'correct' => 0]);
        AttemptAnswer::query()->create(['attempt_id' => $attempt->id, 'question_id' => $question->id, 'question_version' => 1,
            'response' => 'B', 'is_correct' => false, 'time_ms' => 9000]);

        // The checker fixed the key in Nova (C was wrong, B is right), then marks the question fixed.
        $question->forceFill(['answer_key' => ['correct' => 'B']])->save();
        $changed = $this->changes->markFixed($question->refresh(), $this->checker);

        $this->assertSame(1, $changed);
        $this->assertSame(QuestionStatus::Fixed, $question->refresh()->status);
        $this->assertSame(1, $attempt->refresh()->correct);
        $this->assertSame(1, AuditLog::query()->where('action', 'question.rescored')->count());

        $draft = $this->question(QuestionStatus::Draft);
        $this->assertNull($this->changes->markFixed($draft, $this->checker), 'students never saw a draft: nothing to fix');
    }

    public function test_apply_counts_changed_and_skipped_and_the_job_tells_the_staff_member(): void
    {
        $beta = $this->question(QuestionStatus::Beta);
        $draft = $this->question(QuestionStatus::Draft);
        $result = $this->changes->apply('publish', [$beta->id, $draft->id, 999999], [], $this->checker);
        $this->assertSame(['changed' => 1, 'skipped' => 2], $result);

        $notifier = new class implements StaffNotifier
        {
            /** @var list<string> */
            public array $notes = [];

            public function notify(User $staff, string $message, string $type = 'info', ?string $url = null): void
            {
                $this->notes[] = $staff->id.': '.$message;
            }

            public function notifyAll(string $ability, string $message, string $type = 'info', ?string $url = null, ?User $except = null): void {}
        };
        $more = $this->question(QuestionStatus::Published, ['difficulty' => 2]);
        (new ApplyQuestionChanges('difficulty', [$more->id], ['difficulty' => 5], $this->checker->id))->handle(app(QuestionChanges::class), $notifier);
        $this->assertSame(5, $more->refresh()->difficulty);
        $this->assertSame([$this->checker->id.': Change difficulty finished: 1 changed, 0 skipped.'], $notifier->notes);
    }

    public function test_bulk_import_adds_draft_questions_with_the_content_rules(): void
    {
        $chapter = Chapter::factory()->create();
        $yaml = <<<'YAML'
questions:
  - type: single_choice
    difficulty: 2
    stem: 'What is $2 \times 3$?'
    options: {A: '5', B: '6', C: '8'}
    answer: B
    explanation: 'Two groups of three make six in total.'
  - type: true_false
    difficulty: 1
    stem: 'Water boils at 100 °C at sea level.'
    answer: true
    explanation: 'At normal air pressure pure water boils at 100 degrees.'
YAML;
        $result = app(QuestionBulkImport::class)->import($yaml, $chapter, $this->checker);

        $this->assertSame([], $result['errors']);
        $this->assertCount(2, $result['created']);
        $questions = array_map(fn (int $id): Question => Question::query()->findOrFail($id), $result['created']);
        foreach ($questions as $q) {
            $this->assertSame(QuestionStatus::Draft, $q->status, 'Nova import = Draft only (quality gate)');
            $this->assertNull($q->uid);
            $this->assertSame($chapter->id, $q->chapter_id);
        }
        $this->assertSame(QuestionType::SingleChoice, $questions[0]->type);
        $this->assertSame(['correct' => 'B'], $questions[0]->answer_key);
        $this->assertSame(3, QuestionOption::query()->where('question_id', $questions[0]->id)->count());
        $this->assertSameJson(['chapter_id' => $chapter->id, 'questions' => 2], AuditLog::query()->where('action', 'question.imported')->firstOrFail()->data);
    }

    public function test_bulk_import_is_all_or_nothing(): void
    {
        $chapter = Chapter::factory()->create();
        $bad = <<<'YAML'
- type: single_choice
  difficulty: 2
  stem: 'Fine question about cells?'
  options: {A: 'Yes', B: 'No'}
  answer: A
  explanation: 'This one is written correctly and teaches.'
- type: single_choice
  difficulty: 9
  stem: 'Broken <b>question</b>'
  options: {A: 'x'}
  answer: Z
  explanation: 'short'
- type: passage_choice
  difficulty: 2
  passage: p1
  stem: 'From a passage?'
  options: {A: 'a', B: 'b'}
  answer: A
  explanation: 'Passages come only from the content files.'
YAML;
        $result = app(QuestionBulkImport::class)->import($bad, $chapter, $this->checker);

        $this->assertSame([], $result['created']);
        $this->assertNotEmpty($result['errors']);
        $text = implode("\n", $result['errors']);
        $this->assertStringContainsString('question 2', $text);
        $this->assertStringContainsString('question 3: questions with a passage come from the content files', $text);
        $this->assertSame(0, Question::query()->count());

        foreach (['not: [valid', '42', ''] as $notYaml) {
            $r = app(QuestionBulkImport::class)->import($notYaml, $chapter, $this->checker);
            $this->assertSame([], $r['created']);
            $this->assertNotEmpty($r['errors']);
        }
    }
}
